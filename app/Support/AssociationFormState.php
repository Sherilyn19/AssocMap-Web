<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Association;
use Illuminate\Http\Request;

final class AssociationFormState
{
    public const FIELDS = ['name', 'area_unit_id', 'sub_unit_id', 'program_component_id', 'field_officer_id', 'status_id', 'address', 'date_joined', 'representative_member_id'];

    public const FILTERS = ['search', 'area_unit_id', 'sub_unit_id', 'program_component_id', 'field_officer_id', 'status_id', 'archive_state', 'sort', 'page', 'per_page'];

    public static function input(Request $request): array
    {
        // Old input never includes tokens, arbitrary payload fields, or malformed arrays.
        return array_filter($request->only(self::FIELDS), fn ($value) => is_scalar($value) || $value === null);
    }

    public static function filters(Request $request): array
    {
        return array_filter($request->query->all(), fn ($value, $key) => in_array($key, self::FILTERS, true) && is_scalar($value) && strlen((string) $value) <= 255, ARRAY_FILTER_USE_BOTH);
    }

    public static function remember(Request $request): void
    {
        $id = $request->route('association');
        $id = $id instanceof Association ? $id->id : $id;
        $mode = $request->routeIs('admin.associations.store') ? 'create' : ($request->routeIs('admin.associations.update') ? 'edit' : null);
        if ($mode) {
            $request->session()->flash('association_form', ['mode' => $mode, 'id' => is_numeric($id) ? (int) $id : null]);
        }
    }

    public static function returnUrl(Request $request): string
    {
        $id = $request->route('association');
        if ($request->routeIs('admin.associations.representative') && $id) {
            return route('admin.associations.show', ['association' => $id, ...self::filters($request)]);
        }

        return route('admin.associations.index', self::filters($request));
    }
}
