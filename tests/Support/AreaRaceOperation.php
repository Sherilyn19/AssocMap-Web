<?php

namespace Tests\Support;

use App\Models\Association;
use App\Services\AreaManagementService;
use App\Services\AssociationManagementService;
use Illuminate\Validation\ValidationException;

/** The parent and worker execute the same real service operations. */
final class AreaRaceOperation
{
    public static function run(string $operation): void
    {
        $areas = app(AreaManagementService::class);
        $associations = app(AssociationManagementService::class);
        $payload = ['name' => 'Race Association', 'area_unit_id' => 2, 'sub_unit_id' => 2,
            'program_component_id' => 1, 'field_officer_id' => 2, 'status_id' => 4,
            'address' => 'Synthetic race address', 'date_joined' => '2020-01-01'];
        $result = match ($operation) {
            'create_child' => $areas->createBarangay(['name' => 'Race Child', 'area_unit_id' => 3], 1),
            'restore_child' => $areas->setBarangayArchived(3, false, 1),
            'move_child' => $areas->updateBarangay(2, ['name' => 'Barangay B', 'area_unit_id' => 3], 1),
            'archive_parent' => $areas->setMunicipalityArchived(3, true, 1),
            'create_association' => $associations->create($payload, 1),
            'update_association' => $associations->update(Association::findOrFail(1), $payload, 1),
            'restore_association' => $associations->restore(Association::findOrFail(1), 1),
            'archive_child' => $areas->setBarangayArchived(2, true, 1),
            'archive_restore_child' => $areas->setBarangayArchived(1, true, 1),
            default => throw new \LogicException('Unknown race operation'),
        };
        if (is_array($result) && ! $result['ok']) {
            throw ValidationException::withMessages(['archive' => $result['message']]);
        }
    }
}
