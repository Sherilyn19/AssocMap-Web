<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PublishGisLocationRequest;
use App\Http\Requests\Admin\StoreGisLocationRequest;
use App\Http\Requests\Admin\UpdateGisLocationRequest;
use App\Services\GisIndexService;
use App\Services\GisManagementService;
use App\Support\GisErrors;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class GisController extends Controller
{
    public function archive(PublishGisLocationRequest $request, int $location, GisManagementService $gis): JsonResponse
    {
        $request->validate(['confirmed' => ['accepted']]);
        try {
            $id = $gis->archive($location, $request->validated('revision'), (int) $request->attributes->get('assocmap.actor')->id);

            return response()->json(['id' => $id, 'message' => 'Location archived and unpublished. Its record and audit history are preserved.']);
        } catch (Throwable $error) {
            if (GisErrors::handles($request, $error)) {
                return GisErrors::render($error, $request);
            }
            throw $error;
        }
    }

    public function publish(PublishGisLocationRequest $request, int $location, GisManagementService $gis): JsonResponse
    {
        return $this->setPublication($request, $location, $gis, true);
    }

    public function unpublish(PublishGisLocationRequest $request, int $location, GisManagementService $gis): JsonResponse
    {
        return $this->setPublication($request, $location, $gis, false);
    }

    private function setPublication(PublishGisLocationRequest $request, int $location, GisManagementService $gis, bool $published): JsonResponse
    {
        try {
            $id = $gis->publication($location, $request->validated('revision'), $published, (int) $request->attributes->get('assocmap.actor')->id);

            return response()->json(['id' => $id, 'message' => $published ? 'Location published.' : 'Location unpublished.']);
        } catch (Throwable $error) {
            if (GisErrors::handles($request, $error)) {
                return GisErrors::render($error, $request);
            }
            throw $error;
        }
    }

    public function store(StoreGisLocationRequest $request, GisManagementService $gis): JsonResponse
    {
        try {
            $id = $gis->create($request->validated(), (int) $request->attributes->get('assocmap.actor')->id);

            return response()->json(['id' => $id, 'message' => 'Location added.'], 201);
        } catch (Throwable $error) {
            if (GisErrors::handles($request, $error)) {
                return GisErrors::render($error, $request);
            }
            throw $error; // Keep field errors, missing records, and conflicts distinct.
        }
    }

    public function update(UpdateGisLocationRequest $request, int $location, GisManagementService $gis): JsonResponse
    {
        try {
            $id = $gis->update($location, $request->validated(), (int) $request->attributes->get('assocmap.actor')->id);

            return response()->json(['id' => $id, 'message' => 'Location updated.']);
        } catch (Throwable $error) {
            if (GisErrors::handles($request, $error)) {
                return GisErrors::render($error, $request);
            }
            throw $error;
        }
    }

    public function index(GisIndexService $gis): View|Response
    {
        try {
            // The route checks administrator access before any internal records are loaded.
            $actor = request()->attributes->get('assocmap.actor');
            $data = $gis->overview($actor?->role?->role_name === 'Field Officer' ? (int) $actor->id : null);
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
