<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveTrainingRequest;
use App\Models\Association;
use App\Models\Member;
use App\Models\ProgramComponent;
use App\Models\Status;
use App\Models\Training;
use App\Services\SessionUserResolver;
use App\Services\TrainingManagementService;
use App\Support\TrainingCalendar;
use App\Support\TrainingManagementErrors;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

final class TrainingManagementController extends Controller
{
    public function __construct(private readonly TrainingManagementService $service) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'scope' => ['nullable', 'in:active,archived'],
            'association_id' => ['nullable', 'integer', 'min:1'],
            'period' => ['nullable', 'in:upcoming,past,undated'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $scope = $filters['scope'] ?? 'active';
        $query = Training::query()->with(['association:id,name', 'programComponent:id,name'])
            ->withCount('participants')->where('is_archived', $scope === 'archived');
        $search = trim($filters['search'] ?? '');
        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('title', 'ilike', '%'.$search.'%')
                    ->orWhere('venue', 'ilike', '%'.$search.'%')
                    ->orWhereHas('association', fn ($association) => $association->where('name', 'ilike', '%'.$search.'%'));
            });
        }
        if ($filters['association_id'] ?? null) {
            $query->where('association_id', $filters['association_id']);
        }
        $period = $filters['period'] ?? '';
        if ($period === 'upcoming') {
            $query->whereDate('date_conducted', '>', TrainingCalendar::today());
        } elseif ($period === 'past') {
            $query->whereDate('date_conducted', '<=', TrainingCalendar::today());
        } elseif ($period === 'undated') {
            $query->whereNull('date_conducted');
        }

        return view('admin-pages.admin-training-management.index', [
            'trainings' => $query->orderByDesc('updated_at')->orderByDesc('id')->paginate(10)->withQueryString(),
            'filters' => $filters,
            'scope' => $scope,
            'associations' => Association::query()->orderBy('name')->get(['id', 'name']),
            'summary' => Training::query()->selectRaw('COUNT(*) AS total, COUNT(CASE WHEN is_archived = false THEN 1 END) AS active, COUNT(CASE WHEN is_archived = true THEN 1 END) AS archived')->first(),
        ]);
    }

    public function create()
    {
        return view('admin-pages.admin-training-management.form', $this->formData());
    }

    public function edit(Training $training)
    {
        abort_if($training->is_archived, 404);

        return view('admin-pages.admin-training-management.form', $this->formData($training));
    }

    public function show(Request $request, Training $training)
    {
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);
        $training->load(['association', 'programComponent']);
        $counts = $training->participants()->join('statuses', 'statuses.id', '=', 'training_participants.attendance_status_id')
            ->selectRaw('statuses.status_name, COUNT(*) AS total')->groupBy('statuses.status_name')->pluck('total', 'status_name');

        return view('admin-pages.admin-training-management.show', [
            'training' => $training,
            'participants' => $training->participants()->with(['member:id,first_name,middle_name,last_name,is_archived', 'attendanceStatus:id,status_name'])
                ->orderBy('id')->paginate(10),
            'eligibleMembers' => Member::query()->where('association_id', $training->association_id)->where('is_archived', false)
                ->whereNotIn('id', $training->participants()->select('member_id'))->orderBy('last_name')->orderBy('first_name')
                ->get(['id', 'first_name', 'middle_name', 'last_name']),
            'attendanceStatuses' => Status::query()->whereIn('status_name', TrainingManagementService::ATTENDANCE_STATUSES)->orderBy('id')->get(['id', 'status_name']),
            'counts' => $counts,
            'writable' => ! $training->is_archived && $training->association && ! $training->association->is_archived,
        ]);
    }

    public function store(SaveTrainingRequest $request)
    {
        return $this->write($request, function (int $actorId) use ($request) {
            $training = $this->service->save($request->validated(), $actorId);

            return redirect()->route('trainings.show', $training)->with('success', 'Training created. You can now register participants.');
        });
    }

    public function update(SaveTrainingRequest $request, Training $training)
    {
        return $this->write($request, function (int $actorId) use ($request, $training) {
            $this->service->save($request->validated(), $actorId, $training);

            return redirect()->route('trainings.show', $training)->with('success', 'Training updated successfully.');
        });
    }

    public function archive(Request $request, Training $training)
    {
        return $this->setArchive($request, $training, true);
    }

    public function restore(Request $request, Training $training)
    {
        return $this->setArchive($request, $training, false);
    }

    private function setArchive(Request $request, Training $training, bool $archived)
    {
        return $this->write($request, function (int $actorId) use ($training, $archived) {
            $this->service->archive($training, $archived, $actorId);

            return redirect()->route('trainings.show', $training)->with('success', $archived ? 'Training archived. Participant and attendance records are retained.' : 'Training restored.');
        });
    }

    public function addParticipant(Request $request, Training $training)
    {
        $data = $request->validate(['member_id' => ['required', 'integer', 'min:1']]);

        return $this->write($request, function (int $actorId) use ($training, $data) {
            $this->service->addParticipant($training, (int) $data['member_id'], $actorId);

            return redirect()->route('trainings.show', $training)->with('success', 'Participant registered. Attendance is Pending.');
        });
    }

    public function attendance(Request $request, Training $training, int $participant)
    {
        $data = $request->validate(['attendance_status_id' => ['required', 'integer', 'min:1']]);

        return $this->write($request, function (int $actorId) use ($training, $participant, $data) {
            $this->service->attendance($training, $participant, (int) $data['attendance_status_id'], $actorId);

            return back()->with('success', 'Attendance updated.');
        });
    }

    public function removeParticipant(Request $request, Training $training, int $participant)
    {
        return $this->write($request, function (int $actorId) use ($training, $participant) {
            $this->service->removeParticipant($training, $participant, $actorId);

            return redirect()->route('trainings.show', $training)->with('success', 'Participant removed from this training.');
        });
    }

    private function formData(?Training $training = null): array
    {
        return [
            'training' => $training,
            'associations' => Association::query()->where(function ($query) use ($training): void {
                $query->where('is_archived', false);
                if ($training) {
                    $query->orWhere('id', $training->association_id);
                }
            })->orderBy('name')->get(['id', 'name']),
            'programComponents' => ProgramComponent::query()->orderBy('name')->get(['id', 'name']),
        ];
    }

    private function write(Request $request, callable $operation)
    {
        $actorId = (int) app(SessionUserResolver::class)->resolve($request)->id;
        try {
            return $operation($actorId);
        } catch (QueryException $exception) {
            return TrainingManagementErrors::render($exception, $request);
        }
    }
}
