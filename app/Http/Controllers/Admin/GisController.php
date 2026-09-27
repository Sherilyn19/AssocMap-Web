<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GisIndexService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class GisController extends Controller
{
    public function index(GisIndexService $gis): View|Response
    {
        try {
            // The route checks administrator access before any internal records are loaded.
            $data = $gis->overview();
        } catch (QueryException $exception) {
            // Log the error code without exposing database details or location data.
            Log::error('GIS locations could not be loaded.', [
                'code' => $exception->getCode(),
            ]);

            // Show a clear error instead of making a failed query look like an empty map.
            return response()->view('admin-pages.admin-gis-mapping.unavailable', [], 503);
        }

        return view('admin-pages.admin-gis-mapping.index', $data);
    }
}
