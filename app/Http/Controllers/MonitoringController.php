<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\MonitoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class MonitoringController extends Controller
{
    public function __construct(private readonly MonitoringService $service) {}

    private function actor(Request $request): User
    {
        $actor = $request->attributes->get('assocmap.actor');
        $this->service->authorize($actor);

        return $actor;
    }

    public function index(Request $request)
    {
        $actor = $this->actor($request);
        $filters = $request->validate([
            'type' => ['nullable', Rule::in(array_keys(MonitoringService::TYPES))],
            'search' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'between:1900,2100'],
            'project_id' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $type = $filters['type'] ?? 'production';
        $query = $this->service->records($type, $actor);
        if ($filters['search'] ?? null) {
            $query->where(fn ($q) => $q->where('p.title', 'ilike', '%'.$filters['search'].'%')->orWhere('a.name', 'ilike', '%'.$filters['search'].'%'));
        }
        if ($filters['project_id'] ?? null) {
            $query->where('p.id', $filters['project_id']);
        }
        if ($type !== 'materials' && ($filters['year'] ?? null)) {
            $query->where('m.year', $filters['year']);
        }

        return view('monitoring.index', [
            'type' => $type, 'filters' => $filters, 'types' => MonitoringService::TYPES,
            'records' => $query->orderByDesc('m.updated_at')->orderByDesc('m.id')->paginate(10)->withQueryString(),
            'projects' => $this->service->projects($actor)->select('p.id', 'p.title', 'a.name as association_name')->orderBy('p.title')->get(),
        ]);
    }

    public function create(Request $request, string $type)
    {
        return $this->form($request, $type);
    }

    public function edit(Request $request, string $type, int $record)
    {
        return $this->form($request, $type, $record);
    }

    private function form(Request $request, string $type, ?int $id = null)
    {
        $actor = $this->actor($request);
        abort_unless(isset(MonitoringService::TYPES[$type]), 404);
        $record = $id === null ? null : $this->service->records($type, $actor)->where('m.id', $id)->first();
        abort_if($id !== null && ! $record, 404);
        abort_if($record && ($record->project_archived || $record->association_archived), 404);
        $projects = $this->service->projects($actor)->where('p.is_archived', false)->where('a.is_archived', false)
            ->select('p.id', 'p.title', 'a.name as association_name')->orderBy('p.title')->get();
        $materials = DB::table('project_materials')->whereIn('project_id', $projects->pluck('id'))->orderBy('item_name')->get(['id', 'project_id', 'item_name']);

        return view('monitoring.form', [
            'record' => $record, 'type' => $type, 'label' => MonitoringService::TYPES[$type], 'projects' => $projects,
            'materials' => $materials, 'quarters' => DB::table('quarters')->orderBy('id')->pluck('quarter_name', 'id'),
            'conditions' => DB::table('statuses')->whereIn('status_name', MonitoringService::CONDITIONS)->pluck('status_name', 'id'),
        ]);
    }

    public function store(Request $request, string $type)
    {
        return $this->save($request, $type);
    }

    public function update(Request $request, string $type, int $record)
    {
        return $this->save($request, $type, $record);
    }

    private function save(Request $request, string $type, ?int $id = null)
    {
        $actor = $this->actor($request);
        abort_unless(isset(MonitoringService::TYPES[$type]), 404);
        $rules = ['project_id' => ['required', 'integer', 'min:1'], 'remarks' => ['nullable', 'string', 'max:5000']];
        $number = ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'];
        if ($type === 'materials') {
            $rules += [
                'project_material_id' => ['required', 'integer', 'min:1'],
                'material_description' => ['nullable', 'string', 'max:255'],
                'condition_status_id' => ['required', 'integer', Rule::exists('statuses', 'id')->whereIn('status_name', MonitoringService::CONDITIONS)],
                'scheduled_maintenance' => ['nullable', 'date_format:Y-m-d'],
                'actual_maintenance' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Manila')->toDateString()],
            ];
        } else {
            $rules['year'] = ['required', 'integer', 'between:1900,'.now('Asia/Manila')->year];
            if ($type === 'production') {
                $rules += ['quarter_id' => ['required', 'integer', Rule::exists('quarters', 'id')], 'target_output' => $number, 'actual_output' => $number];
            } else {
                $rules += ['month' => ['required', 'integer', 'between:1,12'], 'gross_income' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2']];
            }
        }
        $data = $request->validate($rules);
        if ($type === 'income' && (int) $data['year'] === now('Asia/Manila')->year && (int) $data['month'] > now('Asia/Manila')->month) {
            throw ValidationException::withMessages(['month' => 'Income cannot be recorded for a future month.']);
        }
        $this->service->save($type, $data, $actor, $id);

        return redirect()->route('monitoring.index', ['type' => $type])->with('success', MonitoringService::TYPES[$type].' monitoring record saved.');
    }
}
