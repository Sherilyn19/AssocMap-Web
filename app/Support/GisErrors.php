<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\AssociationDeadlineException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PDOException;
use Throwable;

final class GisErrors
{
    public static function handles(Request $request, Throwable $error): bool
    {
        return $request->is('admin/gis', 'admin/gis/*', 'officer/gis', 'officer/gis/*')
            && ($error instanceof PDOException || $error instanceof AssociationDeadlineException);
    }

    public static function render(Throwable $error, Request $request): mixed
    {
        Log::error('GIS request could not be confirmed.', ['type' => $error::class]);
        if ($request->expectsJson()) {
            return response()->json(['message' => 'The save could not be confirmed. Refresh and check the location before trying again.', 'uncertain' => true], 503);
        }

        return response()->view('admin-pages.admin-gis-mapping.unavailable', [], 503);
    }
}
