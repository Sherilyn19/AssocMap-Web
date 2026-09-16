<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\AssociationDeadlineException;
use App\Exceptions\AssociationRuleException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class AssociationErrors
{
    public static function handles(Request $request, Throwable $error): bool
    {
        return $request->is('admin/associations', 'admin/associations/*') || $error instanceof AssociationRuleException;
    }

    public static function render(Throwable $error, Request $request): mixed
    {
        // Preserve framework validation/authorization semantics, including normal 422 JSON.
        if ($error instanceof ValidationException || $error instanceof AuthorizationException) {
            return null;
        }
        if ($error instanceof HttpExceptionInterface && $error->getStatusCode() < 500) {
            return null;
        }
        $state = $error instanceof \PDOException ? (string) ($error->errorInfo[0] ?? $error->getCode()) : '';
        $duplicate = $state === '23505' && str_contains($error->getMessage(), 'associations_');
        $expected = $error instanceof AssociationRuleException;
        $timeout = in_array($state, ['57014', '55P03'], true) || $error instanceof AssociationDeadlineException;
        $reference = (string) Str::uuid();
        $message = match (true) {
            $expected => $error->getMessage(),
            $duplicate => 'An association with this name already exists in the selected municipality.',
            $timeout => 'The database took too long to respond. Your changes were not completed. Please try again shortly.',
            default => 'The request could not be completed. Check the association records before submitting again because the save outcome may be unknown.',
        };
        if (! $expected && ! $duplicate) {
            // Do not log exception messages or bindings: QueryException includes SQL and inputs.
            Log::error('Association request failed', ['reference' => $reference, 'type' => get_class($error), 'sqlstate' => $state, 'route' => $request->route()?->getName()]);
            $message .= ' Reference: '.$reference;
        }
        $status = ($expected || $duplicate) ? 422 : 503;
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'errors' => $duplicate ? ['name' => [$message]] : (object) [], 'outcome_unknown' => ! $expected && ! $duplicate && ! $timeout], $status);
        }
        if (! $request->isMethod('GET') && $request->hasSession()) {
            AssociationFormState::remember($request);

            return redirect()->to(AssociationFormState::returnUrl($request))->withInput(AssociationFormState::input($request))->with('error', $message);
        }

        return response()->view('errors.association-unavailable', ['message' => $message], 503);
    }
}
