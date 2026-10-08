<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Exceptions\MembershipRuleException;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

trait MemberPanelResponses
{
    /**
     * Preserve normal form redirects while supporting forms inside dialogs.
     * Authorization, validation, and workflow transactions remain unchanged.
     */
    private function memberPanelWrite(Request $request, \Closure $operation)
    {
        try {
            $response = $operation();

            if ($request->expectsJson() && $response instanceof RedirectResponse) {
                return response()->json([
                    'message' => $request->session()->pull('success', 'Changes saved.'),
                    'url' => $response->getTargetUrl(),
                ]);
            }

            return $response;
        } catch (MembershipRuleException $exception) {
            return $this->memberPanelError($request, $exception->getMessage(), 422);
        } catch (QueryException $exception) {
            report($exception);

            return $this->memberPanelError(
                $request,
                'The change could not be confirmed. Reload the record before trying again.',
                503
            );
        }

        // Laravel handles authorization and validation exceptions.
        // JSON validation responses include field errors without discarding form input.
    }

    private function memberPanelError(Request $request, string $message, int $status)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], $status);
        }

        return back()
            ->withInput($request->except(['review_passphrase', 'review_passphrase_confirmation']))
            ->with('error', $message);
    }
}