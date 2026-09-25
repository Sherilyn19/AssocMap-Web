<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PDOException;
use Throwable;

final class TrainingManagementErrors
{
    public static function handles(Request $request, Throwable $error): bool
    {
        return $request->is('admin/trainings', 'admin/trainings/*') && $error instanceof PDOException;
    }

    public static function render(Throwable $error, Request $request): mixed
    {
        // Database messages may contain submitted names and SQL bindings. Log only diagnostics.
        Log::error('Training Management request failed', [
            'type' => get_class($error),
            'sqlstate' => $error instanceof PDOException ? (string) ($error->errorInfo[0] ?? $error->getCode()) : '',
            'route' => $request->route()?->getName(),
        ]);
        $message = 'Training Management is temporarily unavailable. Check the training record before submitting a change again.';
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 503);
        }
        if ($request->isMethod('GET') || ! $request->hasSession() || ! $request->session()->isStarted()) {
            // This standalone view needs neither database queries nor a working dashboard session.
            return response()->view('errors.training-management-unavailable', [], 503);
        }

        $input = [];
        foreach (['association_id', 'title', 'program_component_id', 'training_type', 'venue', 'date_conducted', 'training_cost', 'conducted_by', 'remarks', 'member_id', 'attendance_status_id'] as $field) {
            $value = $request->input($field);
            if (is_scalar($value) || $value === null) {
                $input[$field] = $value;
            }
        }

        return back()->withInput($input)->with('error', $message);
    }
}
