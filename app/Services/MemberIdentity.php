<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Matches the PostgreSQL normalized indexes, including NULL/blank middle names. */
final class MemberIdentity
{
    public function exists(string $table, int $associationId, array $data): bool
    {
        // Table names are program constants, never request input.
        if (!in_array($table, ['members', 'member_applications'], true)) {
            throw new \InvalidArgumentException('Unsupported membership identity table.');
        }

        $query = DB::table($table)->where('association_id', $associationId)->whereDate('birthday', $data['birthday']);
        foreach (['first_name', 'middle_name', 'last_name'] as $field) {
            $value = mb_strtolower(preg_replace('/\s+/u', ' ', trim($data[$field] ?? '')));
            $query->whereRaw("COALESCE(NULLIF(LOWER(REGEXP_REPLACE(BTRIM({$field}), '\\s+', ' ', 'g')), ''), '') = ?", [$value]);
        }
        return $query->exists();
    }
}
