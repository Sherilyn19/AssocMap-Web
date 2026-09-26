<?php

namespace App\Services;

use App\Models\AuditLog;
use Carbon\CarbonImmutable;

class AuditLogService
{
    public const DISPLAY_TIMEZONE = 'Asia/Manila';

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
