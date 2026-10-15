<?php

namespace App\Support;

use Illuminate\Http\Request;
use Throwable;

final class AssociationTerminationErrors
{
    public static function render(Throwable $error, Request $request): mixed
    {
        $state = null;
        for ($cause = $error; $cause; $cause = $cause->getPrevious()) {
            if ($cause instanceof \PDOException) {
                $code = (string) ($cause->errorInfo[0] ?? $cause->getCode());
                if (in_array($code, ['P7501', 'P7502', 'P7503'], true)) { $state = $code; break; }
            }
        }
        if ($state === null) return null;
        $message = match ($state) {
            'P7501' => 'Assistance terminated. This association and its related records are read-only.',
            'P7502' => 'Termination blocked. Resolve pending applications, drafts and attendance first.',
            default => 'Enter a valid assessment date and reason before terminating assistance.',
        };
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'errors' => ['assistance_status' => [$message]]], 422);
        }
        // Do not flash unrelated passwords, passphrases or private member form data.
        return back()->with('error', $message);
    }
}
