<?php

namespace App\Services;

use App\Models\AuditLog;
use Carbon\CarbonImmutable;

class AuditLogService
{
    public const DISPLAY_TIMEZONE = 'Asia/Manila';

    public function denied(\Illuminate\Http\Request $request): void
    {
        $actor = $request->attributes->get('assocmap.actor');
        if (! $actor instanceof \App\Models\User || $request->attributes->get('assocmap.denial_logged')) {
            return;
        }
        $request->attributes->set('assocmap.denial_logged', true);
        // Record only a server-defined route and numeric resource IDs. Never copy
        // URLs, query strings, request bodies or review credentials into the audit.
        $ids = [];
        foreach ($request->route()?->parameters() ?? [] as $key => $value) {
            $value = $value instanceof \Illuminate\Database\Eloquent\Model ? $value->getKey() : $value;
            if (is_scalar($value) && ctype_digit((string) $value)) {
                $ids[$key] = (int) $value;
            }
        }
        try {
            \Illuminate\Support\Facades\DB::transaction(fn () => \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
                'user_id' => $actor->id, 'action_type' => 'UNAUTHORIZED_ACCESS', 'module' => 'Access Control',
                'record_id' => null, 'performed_at' => now(),
                'details' => json_encode(['method' => $request->method(), 'route' => $request->route()?->getName(), 'resources' => $ids], JSON_THROW_ON_ERROR),
            ]));
        } catch (\Throwable) {
            // A logging outage must not turn a denied request into an allowed one.
            logger()->error('Unauthorized-access audit could not be recorded.', ['actor_id' => $actor->id]);
        }
    }

    public function listing(array $filters): array
    {
        $query = AuditLog::with('user.role')->orderByDesc('performed_at')->orderByDesc('id');
        foreach (['module', 'action_type'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (isset($filters['performed_by']) && $filters['performed_by'] !== '') {
            // Treat percent and underscore as name characters, not search wildcards.
            $name = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($filters['performed_by'])).'%';
            $query->whereHas('user', fn ($users) => $users->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$name]));
        }
        // Existing writers store application time (UTC). Match whole Philippine calendar days.
        foreach (['date_from', 'date_to'] as $field) {
            if (! empty($filters[$field])) {
                $boundary = CarbonImmutable::createFromFormat('!Y-m-d', $filters[$field], self::DISPLAY_TIMEZONE);
                if ($field === 'date_to') {
                    $boundary = $boundary->addDay();
                }
                $query->where('performed_at', $field === 'date_from' ? '>=' : '<', $boundary->setTimezone(config('app.timezone')));
            }
        }

        return [
            'logs' => $query->paginate(15)->appends(array_diff_key($filters, ['page' => true])),
            'modules' => AuditLog::select('module')->distinct()->orderBy('module')->pluck('module'),
            'actionTypes' => AuditLog::select('action_type')->distinct()->orderBy('action_type')->pluck('action_type'),
            'filters' => $filters,
            'timezone' => self::DISPLAY_TIMEZONE,
        ];
    }
}
