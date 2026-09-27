<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GisIndexService;
use App\Services\GisManagementService;
use App\Http\Requests\Admin\StoreGisLocationRequest;
use App\Http\Requests\Admin\UpdateGisLocationRequest;
use App\Support\GisErrors;
use Illuminate\Http\JsonResponse;
use Throwable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class GisController extends Controller
{
    public function store(StoreGisLocationRequest $request, GisManagementService $gis): JsonResponse
    {
        try {
            $id = $gis->create($request->validated(), (int) $request->attributes->get('assocmap.actor')->id);
            return response()->json(['id' => $id, 'message' => 'Location added.'], 201);
        } catch (Throwable $error) {
            if (GisErrors::handles($request, $error)) return GisErrors::render($error, $request);
            throw $error; // Keep field errors, missing records, and conflicts distinct.
        }
    }

    public function update(UpdateGisLocationRequest $request, int $location, GisManagementService $gis): JsonResponse
    {
        try {
            $id = $gis->update($location, $request->validated(), (int) $request->attributes->get('assocmap.actor')->id);
            return response()->json(['id' => $id, 'message' => 'Location updated.']);
        } catch (Throwable $error) {
            if (GisErrors::handles($request, $error)) return GisErrors::render($error, $request);
            throw $error;
        }
    }

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
