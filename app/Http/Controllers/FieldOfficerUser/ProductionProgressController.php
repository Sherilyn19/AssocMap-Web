<?php

declare(strict_types=1);

namespace App\Http\Controllers\FieldOfficerUser;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MonitoringService;
use App\Services\Shared\ProductionProgressService;
use App\Support\MonitoringErrors;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ProductionProgressController extends Controller
{
    public function __construct(
        private readonly MonitoringService $monitoring,
        private readonly ProductionProgressService $progress
    ) {}

    private function actor(Request $request): User
    {
        $actor = $request->attributes->get('assocmap.actor');

        abort_unless(
            $actor instanceof User
            && $actor->is_active
            && $actor->role?->role_name === 'Field Officer',
            403
        );

        return $actor;
    }

    public function create(Request $request)
    {
        $actor = $this->actor($request);

        return view('field-officer-user.monitoring.progress-create', [
            'projects' => $this->monitoring->projects($actor)
                ->where('p.is_archived', false)
                ->where('a.is_archived', false)
                ->orderBy('p.title')
                ->get([
                    'p.id',
                    'p.title',
                    'a.name as association_name',
                ]),
            'quarters' => DB::table('quarters')
                ->orderBy('id')->pluck('quarter_name', 'id'),
        ]);
    }

    public function store(Request $request)
    {
        try {
            $this->progress->createQuarter(
                $this->actor($request),
                $request->all()
            );
        } catch (QueryException $error) {
            return MonitoringErrors::render($error, $request);
        }

        return $this->saved($request);
    }

    public function update(Request $request, int $record)
    {
        try {
            $this->progress->change(
                $this->actor($request),
                $record,
                $request->all()
            );
        } catch (QueryException $error) {
            return MonitoringErrors::render($error, $request);
        }

        return $this->saved($request);
    }

    private function saved(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Production progress saved.']);
        }

        return redirect()->route('monitoring.index', ['type' => 'production'])
            ->with('success', 'Production progress saved.');
    }

    public function show(Request $request, int $record)
    {
        $actor = $this->actor($request);

        $request->validate([
            'entries_page' => ['nullable', 'integer', 'min:1'],
            'audit_page' => ['nullable', 'integer', 'min:1'],
        ]);

        // All related history is reached through an authorized parent.
        $selected = $this->monitoring->records('production', $actor)
            ->where('m.id', $record)
            ->first();

        abort_unless($selected, 404);

        [$start, $end] = $this->progress->period(
            (int) $selected->year,
            (int) $selected->quarter_id
        );

        $quarters = $this->monitoring->records('production', $actor)
            ->where('p.id', $selected->project_id)
            ->where('m.year', $selected->year)
            ->orderBy('q.quarter_name')
            ->get()
            ->keyBy('quarter_name');

        $entries = DB::table('production_progress_entries as e')
            ->leftJoin('users as u', 'u.id', '=', 'e.created_by')
            ->where('e.monitoring_production_id', $selected->id)
            ->select('e.*', 'u.name as author_name')
            ->orderByDesc('e.produced_on')
            ->orderByDesc('e.id')
            ->paginate(8, ['*'], 'entries_page')
            ->withQueryString();

        $audit = DB::table('audit_logs as l')
            ->leftJoin('users as u', 'u.id', '=', 'l.user_id')
            ->where('l.module', 'Production Progress')
            ->where('l.record_id', $selected->id)
            ->select('l.*', 'u.name as actor_name')
            ->orderByDesc('l.performed_at')
            ->orderByDesc('l.id')
            ->paginate(10, ['*'], 'audit_page')
            ->withQueryString();

        // Legacy monitoring logs may share numeric IDs across monitoring types.
        // Filter decoded metadata before displaying those older records.
        $legacyAudit = DB::table('audit_logs as l')
            ->leftJoin('users as u', 'u.id', '=', 'l.user_id')
            ->where('l.module', 'Monitoring')
            ->where('l.record_id', $selected->id)
            ->select('l.*', 'u.name as actor_name')
            ->orderByDesc('l.performed_at')
            ->limit(100)
            ->get()
            ->filter(function ($log) use ($selected): bool {
                $details = json_decode($log->details ?? '', true);

                return is_array($details)
                    && ($details['type'] ?? null) === 'production'
                    && (int) ($details['project_id'] ?? 0)
                        === (int) $selected->project_id;
            })
            ->take(10);

        return view('field-officer-user.monitoring.achievement', [
            'record' => $selected,
            'quarters' => $quarters,
            'entries' => $entries,
            'audit' => $audit,
            'legacyAudit' => $legacyAudit,
            'start' => $start,
            'end' => $end,
            'readOnly' => $selected->project_archived
                || $selected->association_archived,
        ]);
    }
}