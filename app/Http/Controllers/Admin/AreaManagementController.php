<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAreaUnitRequest;
use App\Http\Requests\Admin\StoreSubUnitRequest;
use App\Http\Requests\Admin\UpdateAreaUnitRequest;
use App\Http\Requests\Admin\UpdateSubUnitRequest;
use App\Services\AreaManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * AreaManagementController
 * app/Http/Controllers/Admin/AreaManagementController.php
 */
class AreaManagementController extends Controller
{
    public function __construct(private readonly AreaManagementService $areas)
    {
    }

    public function index(Request $request): View
    {
        $filters = $request->only([
            'search', 'status', 'muni_sort',
            'brgy_search', 'area_unit_id', 'brgy_status', 'brgy_sort',
        ]);

        return view('admin-pages.admin-area-management.admin-area-index', [
            'municipalities'       => $this->areas->listMunicipalities($filters),
            'barangays'            => $this->areas->listBarangays($filters),
            'summary'              => $this->areas->summaryCounts(),
            'activeMunicipalities' => $this->areas->activeMunicipalitiesForDropdown(),
            'filters'              => $filters,
        ]);
    }

    public function showMunicipality(int $areaUnit): JsonResponse
    {
        return response()->json($this->areas->viewMunicipality($areaUnit));
    }

    public function showBarangay(int $subUnit): JsonResponse
    {
        return response()->json($this->areas->viewBarangay($subUnit));
    }

    public function storeMunicipality(StoreAreaUnitRequest $request): RedirectResponse
    {
        return $this->mutation(
            fn () => $this->areas->createMunicipality($request->validated(), session('auth_user.id')),
            'Municipality created successfully.'
        );
    }

    public function updateMunicipality(UpdateAreaUnitRequest $request, int $areaUnit): RedirectResponse
    {
        return $this->mutation(
            fn () => $this->areas->updateMunicipality($areaUnit, $request->validated(), session('auth_user.id')),
            'Municipality updated successfully.'
        );
    }

    public function toggleArchiveMunicipality(int $areaUnit): RedirectResponse
    {
        try {
            $result = $this->areas->toggleArchiveMunicipality($areaUnit, session('auth_user.id'));

            if (!$result['ok']) {
                return back()->with('error', $result['message']);
            }

            $status = $result['is_archived'] ? 'archived' : 'restored';

            return back()->with('success', "Municipality {$status} successfully.");
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'The municipality status could not be updated. Please try again or contact the system administrator.');
        }
    }

    public function storeBarangay(StoreSubUnitRequest $request): RedirectResponse
    {
        return $this->mutation(
            fn () => $this->areas->createBarangay($request->validated(), session('auth_user.id')),
            'Barangay created successfully.'
        );
    }

    public function updateBarangay(UpdateSubUnitRequest $request, int $subUnit): RedirectResponse
    {
        return $this->mutation(
            fn () => $this->areas->updateBarangay($subUnit, $request->validated(), session('auth_user.id')),
            'Barangay updated successfully.'
        );
    }

    public function toggleArchiveBarangay(int $subUnit): RedirectResponse
    {
        try {
            $result = $this->areas->toggleArchiveBarangay($subUnit, session('auth_user.id'));

            if (!$result['ok']) {
                return back()->with('error', $result['message']);
            }

            $status = $result['is_archived'] ? 'archived' : 'restored';

            return back()->with('success', "Barangay {$status} successfully.");
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'The barangay status could not be updated. Please try again or contact the system administrator.');
        }
    }

    private function mutation(callable $callback, string $successMessage): RedirectResponse
    {
        try {
            $callback();

            return back()->with('success', $successMessage);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'The Area Management request could not be completed. Please review the information and try again.');
        }
    }
}
