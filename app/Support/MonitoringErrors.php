<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PDOException;
use Throwable;

final class MonitoringErrors
{
    public static function handles(Request $request, Throwable $error): bool
    {
        return $request->is('monitoring', 'monitoring/*') && $error instanceof PDOException;
    }

    public static function render(Throwable $error, Request $request): mixed
    {
        Log::error('Monitoring request failed', ['type' => get_class($error), 'route' => $request->route()?->getName()]);
        $message = 'Monitoring is temporarily unavailable. Check the record before submitting again.';
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 503);
        }
        if ($request->isMethod('GET') || ! $request->hasSession() || ! $request->session()->isStarted()) {
            return response()->view('errors.monitoring-unavailable', [], 503);
        }
        $input = [];
        foreach (['project_id', 
                    'year', 
                    'quarter_id', 
                    'month', 
                    'target_output', 
                    'actual_output', 
                    'gross_income', 
                    'project_material_id', 
                    'condition_status_id', 
                    'material_description', 
                    'scheduled_maintenance', 
                    'actual_maintenance', 
                    'remarks',
                    // Preserve the user's event identity and unit choices after a database failure.
                    'observed_on',
                    'submission_token',
                    'output_unit_code',
                    'output_unit_spec'] as $field) {
            $value = $request->input($field);
            if (is_scalar($value) || $value === null) {
                $input[$field] = $value;
            }
        }

        return back()->withInput($input)->with('error', $message);
    }
}
