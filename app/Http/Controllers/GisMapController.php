<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\AssociationDeadlineException;
use App\Services\AssociationDatabase;
use App\Services\GisReadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PDOException;

final class GisMapController extends Controller
{
    public function index(Request $request, GisReadService $gis): mixed
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'municipality' => ['nullable', 'string', 'max:255'],
            'barangay' => ['nullable', 'string', 'max:255'],
            'component' => ['nullable', 'string', 'max:255'],
            'commodity' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'between:1,10000'],
        ]);
        // Public routes always use public visibility, including for signed-in visitors.
        $internal = $request->routeIs('gis.viewer', 'gis.viewer.data');
        $actor = $internal ? $request->attributes->get('assocmap.actor') : null;
        abort_if($internal && ! $actor, 403);
        try {
            $data = app(AssociationDatabase::class)->run(fn () => $gis->locations($filters, $actor));
        } catch (PDOException|AssociationDeadlineException $error) {
            Log::error('GIS viewing is unavailable.', ['type' => $error::class]);

            return $request->expectsJson() || $request->routeIs('*.data')
                ? response()->json(['message' => 'Locations could not be loaded. Please try again.'], 503)->header('Cache-Control', 'no-store')
                : response()->view('shared.gis.viewer', ['data' => null, 'filters' => $filters, 'internal' => $internal], 503)->header('Cache-Control', 'no-store');
        }

        if ($request->expectsJson() || $request->routeIs('*.data')) {
            return response()->json($data)->header('Cache-Control', 'no-store');
        }

        return response()->view('shared.gis.viewer', compact('data', 'filters', 'internal'))->header('Cache-Control', 'no-store');
    }
}
