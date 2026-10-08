<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\GisLocation;
use App\Models\User;
use App\Services\AssociationDatabase;
use App\Support\GisErrors;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

final class GisHistoryController extends Controller
{
    private function locations(Request $request): Builder
    {
        $actor = $request->attributes->get('assocmap.actor');

        abort_unless(
            $actor instanceof User
            && $actor->is_active
            && in_array(
                $actor->role?->role_name,
                ['System Administrator', 'Field Officer'],
                true
            ),
            403
        );

        $query = GisLocation::query();

        if ($actor->role->role_name === 'Field Officer') {
            // History remains restricted after reassignment, including archives.
            $query->whereHas(
                'association',
                fn ($association) => $association
                    ->where('field_officer_id', $actor->id)
            );
        }

        return $query;
    }

    public function archived(Request $request)
    {
        $request->validate([
            'page' => ['nullable', 'integer', 'between:1,10000'],
        ]);

        return $this->respond($request, function () use ($request) {
            $records = $this->locations($request)
                ->whereNotNull('archived_at')
                ->with('association:id,name')
                ->orderByDesc('archived_at')
                ->orderByDesc('id')
                // Previous/Next navigation does not require a separate total-count query.
                ->simplePaginate(15)
                ->withQueryString();

            return view('shared.gis.history', [
                'records' => $records,
                'location' => null,
                'events' => null,
                'historyRoute' => $request->routeIs('gis.officer.*')
                    ? 'gis.officer.history'
                    : 'gis.history',
            ]);
        });
    }

    public function show(Request $request, int $location)
    {
        $request->validate([
            'page' => ['nullable', 'integer', 'between:1,10000'],
        ]);

        return $this->respond($request, function () use ($request, $location) {
            // Authorize the location before querying any audit information.
            $record = $this->locations($request)
                ->with('association:id,name')
                ->whereKey($location)
                ->firstOrFail();

            $events = DB::table('audit_logs as audit')
                ->leftJoin('users as actor', 'actor.id', '=', 'audit.user_id')
                ->where('audit.module', 'GIS')
                ->where('audit.record_id', $record->id)
                ->select(
                    'audit.id',
                    'audit.action_type',
                    'audit.details',
                    'audit.performed_at',
                    'actor.name as actor_name'
                )
                ->orderByDesc('audit.performed_at')
                ->orderByDesc('audit.id')
                // Previous/Next navigation does not require a separate total-count query.
                ->simplePaginate(15)
                ->withQueryString();

            return view('shared.gis.history', [
                'records' => null,
                'location' => $record,
                'events' => $events,
                'historyRoute' => null,
            ]);
        });
    }

    private function respond(Request $request, callable $read)
    {
        try {
            $view = app(AssociationDatabase::class)->run($read);

            // Internal coordinates and audit history must not be cached publicly.
            return response($view->render())
                ->header('Cache-Control', 'private, no-store');
        } catch (Throwable $error) {
            if (GisErrors::handles($request, $error)) {
                return GisErrors::render($error, $request);
            }

            throw $error;
        }
    }
}