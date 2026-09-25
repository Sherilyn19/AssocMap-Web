<?php

namespace App\Support;

use App\Exceptions\AssociationDeadlineException;
use App\Exceptions\AssociationRuleException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use PDOException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/** Keep account errors useful without exposing passwords, SQL, or connection details. */
final class UserManagementErrors
{
    public static function handles(Request $request, Throwable $error): bool
    {
        // Limit this recovery policy to account routes; unrelated errors keep their own handling.
        return $request->is('admin/users', 'admin/users/*')
            && ($error instanceof ValidationException || $error instanceof AssociationRuleException
                || $error instanceof PDOException || $error instanceof AssociationDeadlineException
                || $error instanceof ModelNotFoundException || $error instanceof NotFoundHttpException);
    }

    public static function render(Throwable $error, Request $request): mixed
    {
        // Missing or malformed account URLs are ordinary 404s, not database outages.
        // Do not reopen Edit for a record that cannot be found.
        if ($error instanceof ModelNotFoundException || $error instanceof NotFoundHttpException) {
            $message = 'The requested account or account action could not be found. Return to User Management and refresh the list.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 404)
                : response()->view('errors.user-management-unavailable', ['heading' => 'Account unavailable', 'message' => $message], 404);
        }
        $state = $error instanceof PDOException ? (string) ($error->errorInfo[0] ?? $error->getCode()) : '';
        // A competing save may pass form validation first. Convert only email conflicts
        // on the users table into field errors; other database failures stay general.
        $duplicate = $state === '23505' && $error instanceof QueryException
            && preg_match('/(?:insert into|update) "?users"?/i', $error->getSql())
            && str_contains($error->getMessage(), '(email)=');
        $errors = $error instanceof ValidationException ? $error->errors() : [];
        $message = match (true) {
            $error instanceof ValidationException => 'Please correct the highlighted fields.',
            $error instanceof AssociationRuleException => $error->getMessage(),
            (bool) $duplicate => 'This email address is already used by another account.',
            default => 'The account change could not be confirmed. Check User Management before submitting again.',
        };
        if ($duplicate) {
            $errors['email'] = [$message];
        }
        $expected = $error instanceof ValidationException || $error instanceof AssociationRuleException || $duplicate;
        if (! $expected) {
            // QueryException messages and bindings can contain credentials and personal input.
            Log::error('User Management request failed', ['type' => get_class($error), 'sqlstate' => $state, 'route' => $request->route()?->getName()]);
        }
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'errors' => $errors ?: (object) []], $expected ? 422 : 503);
        }
        if ($request->isMethod('GET') && $error instanceof ValidationException && $request->hasSession() && $request->session()->isStarted()) {
            // Remove invalid query values so the redirect cannot repeat the same validation error.
            return redirect()->route('users.index')->withErrors($errors)->with('error', 'Please correct the account filters.');
        }
        if ($request->isMethod('GET') || ! $request->hasSession() || ! $request->session()->isStarted()) {
            // Avoid a redirect loop or another database-dependent layout during an outage.
            return response()->view('errors.user-management-unavailable', [], 503);
        }
        $input = [];
        // Explicitly allow safe form fields instead of copying passwords or unexpected input.
        foreach (['name', 'email', 'role_id', 'association_id'] as $field) {
            $value = $request->input($field);
            if (is_scalar($value) || $value === null) {
                $input[$field] = $value;
            }
        }
        $response = redirect()->route('users.index')->withInput($input)->withErrors($errors)->with('error', $message);
        if ($request->routeIs('users.store', 'users.update')) {
            // The server route identifies the edited account; client input cannot choose it.
            $response->with('user_form', ['mode' => $request->routeIs('users.update') ? 'edit' : 'create', 'id' => (int) $request->route('user')]);
        }

        return $response;
    }
}
