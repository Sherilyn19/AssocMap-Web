<?php

namespace App\Http\Controllers\FieldOfficerUser;

use App\Http\Controllers\Controller;
use App\Http\Requests\FieldOfficerUser\UpdateTrainingRequest;
use App\Models\Member;
use App\Models\ProgramComponent;
use App\Models\Status;
use App\Models\Training;
use App\Services\FieldOfficerUserAccess;
use App\Services\SessionUserResolver;
use App\Services\TrainingManagementService;
use App\Services\TrainingWorkspaceDetails;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

final class TrainingController extends Controller
{
    public function __construct(
        private SessionUserResolver $resolver,
        private FieldOfficerUserAccess $access,
        private TrainingManagementService $service
    ) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'association_id' => ['nullable', 'integer', 'min:1'],
            'scope' => ['nullable', 'in:current,archived,all'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $actor = $this->resolver->resolve($request);
        $scope = $filters['scope'] ?? 'current';

        $association = empty($filters['association_id'])
            ? null
            : $this->access->associations($actor)
                ->findOrFail($filters['association_id']);

        $query = $this->access->scope(
            Training::with(['association', 'programComponent'])
                ->withAttendanceSummary(),
            $actor
        );

        if ($association) {
            $query->where('association_id', $association->id);
        }

        if ($scope !== 'all') {
            $query->where('is_archived', $scope === 'archived');
        }

        if ($search = trim($filters['search'] ?? '')) {
            $query->where('title', 'ilike', '%'.$search.'%');
        }

        return view('field-officer-user.trainings.index', [
            'association' => $association,
            'scope' => $scope,
            'associations' => $this->access->associations($actor)
                ->orderBy('name')->get(['id', 'name']),
            'trainings' => $query->orderByDesc('date_conducted')
                ->orderByDesc('id')->paginate(10)->withQueryString(),
        ]);
    }

    public function create(Request $request)
    {
        return $this->panelView($request, [
            'panel' => 'edit',
            'training' => null,
            'datesEditable' => true,
            'associations' => $this->access
                ->associations($this->resolver->resolve($request))
                ->where('is_archived', false)->orderBy('name')->get(['id', 'name']),
            'components' => ProgramComponent::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Request $request, int $training)
    {
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);

        $training = $this->training($request, $training)
            ->load(['association', 'programComponent']);

        return view(
            $request->boolean('details')
                ? 'field-officer-user.trainings.details'
                : 'field-officer-user.trainings.show',
            ['training' => $training]
                + app(TrainingWorkspaceDetails::class)->forTraining($training)
        );
    }

    public function panel(Request $request, int $training, string $panel)
    {
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);

        $training = $this->training($request, $training)
            ->load(['association', 'programComponent']);

        $editable = ! $training->is_archived
            && ! $training->association->is_archived;

        if ($panel !== 'attendance') {
            abort_unless($editable, 403, 'Archived records cannot be changed.');
        }

        $data = [
            'training' => $training,
            'panel' => $panel,
            'editable' => $editable,
            'datesEditable' => $editable
                && $training->schedule_locked_at === null
                && ! $training->participants()->exists(),
        ];

        if ($panel === 'attendance') {
            $data += [
                'participants' => $training->participants()
                    ->whereHas('member', fn ($query) => $query
                        ->where('association_id', $training->association_id))
                    ->with(['member', 'attendanceStatus'])
                    ->orderBy('id')->paginate(20),
                // Archived training needs history, not a list of registration choices.
                'members' => $editable
                    ? Member::where('association_id', $training->association_id)
                        ->where('is_archived', false)
                        ->whereNotIn('id', $training->participants()->select('member_id'))
                        ->orderBy('last_name')->get()
                    : collect(),
                'statuses' => Status::whereIn(
                    'status_name',
                    TrainingManagementService::ATTENDANCE_STATUSES
                )->get(),
            ];
        }

        return $this->panelView($request, $data);
    }

    public function store(UpdateTrainingRequest $request)
    {
        return $this->write($request, function () use ($request): void {
            $this->service->createOfficerTraining(
                $request->validated(),
                $this->resolver->resolve($request)
            );
        }, 'Training recorded successfully.');
    }

    public function update(UpdateTrainingRequest $request, int $training)
    {
        return $this->write($request, function () use ($request, $training): void {
            $this->service->updateOfficerInformation(
                $this->training($request, $training),
                $request->validated(),
                $this->resolver->resolve($request)
            );
        }, 'Training updated successfully.');
    }

    public function archive(Request $request, int $training)
    {
        $request->validate(['confirm' => ['required', 'accepted']]);

        return $this->write($request, function () use ($request, $training): void {
            $this->service->archiveOfficerTraining(
                $this->training($request, $training),
                $this->resolver->resolve($request)
            );
        }, 'Training archived. Attendance history has been retained.');
    }

    public function addParticipant(Request $request, int $training)
    {
        $data = $request->validate([
            'member_id' => ['required', 'integer', 'min:1'],
        ]);

        return $this->write($request, function () use ($request, $training, $data): void {
            $this->service->addParticipant(
                $this->training($request, $training),
                (int) $data['member_id'],
                (int) $this->resolver->resolve($request)->id
            );
        }, 'Participant registered with Pending attendance.');
    }

    public function attendance(Request $request, int $training, int $participant)
    {
        $data = $request->validate([
            'attendance_status_id' => ['required', 'integer', 'min:1'],
        ]);

        return $this->write($request, function () use (
            $request, $training, $participant, $data
        ): void {
            $this->service->attendance(
                $this->training($request, $training),
                $participant,
                (int) $data['attendance_status_id'],
                (int) $this->resolver->resolve($request)->id
            );
        }, 'Attendance saved.');
    }

    private function training(Request $request, int $id): Training
    {
        return $this->access->scope(
            Training::query(),
            $this->resolver->resolve($request)
        )->findOrFail($id);
    }

    private function panelView(Request $request, array $data)
    {
        // AJAX receives a fragment; ordinary links retain a usable fallback page.
        return view(
            $request->boolean('fragment')
                ? 'field-officer-user.trainings.panel'
                : 'field-officer-user.trainings.panel-page',
            $data
        );
    }

    private function write(Request $request, callable $operation, string $message)
    {
        try {
            $operation();

            if ($request->expectsJson()) {
                return response()->json(['message' => $message]);
            }

            return redirect()->route('officer.trainings.index')
                ->with('success', $message);
        } catch (QueryException $error) {
            // Keep SQL details private. Validation and authorization errors retain
            // their normal Laravel responses and are not converted into success.
            report($error);

            $message = 'The change could not be confirmed. Check the record before retrying.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 503)
                : back()->withInput()->with('error', $message);
        }
    }
}