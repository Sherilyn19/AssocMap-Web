<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AreaIndexRequest;
use App\Http\Requests\Admin\StoreAreaUnitRequest;
use App\Http\Requests\Admin\StoreSubUnitRequest;
use App\Http\Requests\Admin\UpdateAreaUnitRequest;
use App\Http\Requests\Admin\UpdateSubUnitRequest;
use App\Services\AreaManagementService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class AreaManagementController extends Controller
{
    public function __construct(private readonly AreaManagementService $areas) {}

    public function index(AreaIndexRequest $request): View|Response
    {
        $filters = $request->validated();
        try {
            return view('admin-pages.admin-area-management.admin-area-index', [
                'municipalities' => $this->areas->listMunicipalities($filters),
                'barangays' => $this->areas->listBarangays($filters),
                'summary' => $this->areas->summaryCounts(),
                'activeMunicipalities' => $this->areas->activeMunicipalitiesForDropdown(),
                'filterMunicipalities' => $this->areas->municipalitiesForFilter(),
                'filters' => $filters,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->view('admin-pages.admin-area-management.unavailable', [], 503);
        }
    }

    public function showMunicipality(int $areaUnit): JsonResponse
    {
        return $this->detail(fn () => $this->areas->viewMunicipality($areaUnit));
    }

    public function showBarangay(int $subUnit): JsonResponse
    {
        return $this->detail(fn () => $this->areas->viewBarangay($subUnit));
    }

    private function detail(callable $read): JsonResponse
    {
        try {
            return response()->json($read());
        } catch (ModelNotFoundException $exception) {
            return response()->json(['message' => 'This area record no longer exists. Refresh the list.'], 404);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Details could not be loaded. Please try again.'], 503);
        }
    }

    public function storeMunicipality(StoreAreaUnitRequest $request): RedirectResponse
    {
        return $this->mutation($request, fn () => $this->areas->createMunicipality($request->validated(), session('auth_user.id')), 'Municipality created successfully.');
    }

    public function updateMunicipality(UpdateAreaUnitRequest $request, int $areaUnit): RedirectResponse
    {
        return $this->mutation($request, fn () => $this->areas->updateMunicipality($areaUnit, $request->validated(), session('auth_user.id')), 'Municipality updated successfully.');
    }

    public function storeBarangay(StoreSubUnitRequest $request): RedirectResponse
    {
        return $this->mutation($request, fn () => $this->areas->createBarangay($request->validated(), session('auth_user.id')), 'Barangay created successfully.');
    }

    public function updateBarangay(UpdateSubUnitRequest $request, int $subUnit): RedirectResponse
    {
        return $this->mutation($request, fn () => $this->areas->updateBarangay($subUnit, $request->validated(), session('auth_user.id')), 'Barangay updated successfully.');
    }

    public function archiveMunicipality(Request $request, int $areaUnit): RedirectResponse
    {
        return $this->changeArchive($request, 'municipality', $areaUnit, true);
    }

    public function restoreMunicipality(Request $request, int $areaUnit): RedirectResponse
    {
        return $this->changeArchive($request, 'municipality', $areaUnit, false);
    }

    public function archiveBarangay(Request $request, int $subUnit): RedirectResponse
    {
        return $this->changeArchive($request, 'barangay', $subUnit, true);
    }

    public function restoreBarangay(Request $request, int $subUnit): RedirectResponse
    {
        return $this->changeArchive($request, 'barangay', $subUnit, false);
    }

    private function changeArchive(Request $request, string $entity, int $id, bool $archived): RedirectResponse
    {
        return $this->mutation($request, function () use ($entity, $id, $archived): void {
            $result = $entity === 'municipality'
                ? $this->areas->setMunicipalityArchived($id, $archived, session('auth_user.id'))
                : $this->areas->setBarangayArchived($id, $archived, session('auth_user.id'));
            if (! $result['ok']) {
                throw ValidationException::withMessages(['archive' => $result['message']]);
            }
        }, ucfirst($entity).($archived ? ' archived successfully.' : ' restored successfully.'), false);
    }

    private function mutation(Request $request, callable $write, string $success, bool $draft = true): RedirectResponse
    {
        try {
            $write();

            return back()->with('success', $success);
        } catch (ValidationException $exception) {
            // Defense: expected rule failures are actionable, while SQL details stay private.
            $response = back()->withErrors($exception->errors(), $draft ? $request->input('_area_form', 'default') : 'default');

            return $draft ? $response->withInput($request->except('_token')) : $response->with('error', collect($exception->errors())->flatten()->first());
        } catch (ModelNotFoundException $exception) {
            return back()->with('error', 'This record no longer exists. Refresh the list before trying again.');
        } catch (QueryException $exception) {
            report($exception);
            if (($exception->errorInfo[0] ?? '') === '23505' && $draft) {
                return back()->withInput($request->except('_token'))->withErrors(['name' => 'This name already exists in the selected geographic scope.'], $request->input('_area_form', 'default'));
            }

            return $this->saveFailure($request, $draft);
        } catch (Throwable $exception) {
            report($exception);

            return $this->saveFailure($request, $draft);
        }
    }

    private function saveFailure(Request $request, bool $draft): RedirectResponse
    {
        // A lost response is not proof that no commit occurred: avoid automatic retries.
        $response = back()->with('error', 'The request could not be confirmed. Your draft is retained. Refresh and check the record before retrying.');

        return $draft ? $response->withInput($request->except('_token')) : $response;
    }
}
