<?php

namespace App\Http\Controllers\FieldOfficerUser;

use App\Http\Controllers\Controller;
use App\Http\Requests\FieldOfficerUser\UpdateTrainingRequest;
use App\Models\Member;
use App\Models\Status;
use App\Models\Training;
use App\Services\FieldOfficerUserAccess;
use App\Services\SessionUserResolver;
use App\Services\TrainingManagementService;
use Illuminate\Http\Request;

final class TrainingController extends Controller
{
    public function __construct(private SessionUserResolver $resolver, private FieldOfficerUserAccess $access, private TrainingManagementService $service) {}

    public function index(Request $request)
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:255'], 'association_id' => ['nullable', 'integer', 'min:1'], 'page' => ['nullable', 'integer', 'min:1']]);
        $actor = $this->resolver->resolve($request);
        $association = empty($filters['association_id']) ? null : $this->access->associations($actor)->findOrFail($filters['association_id']);
        $query = $this->access->scope(Training::with('association'), $actor);
        if ($filters['association_id'] ?? null) {
            $query->where('association_id', $filters['association_id']);
        }
        if ($filters['search'] ?? null) {
            $query->where('title', 'ilike', '%'.$filters['search'].'%');
        }

        return view('field-officer-user.trainings.index', ['association' => $association, 'trainings' => $query->orderByDesc('date_conducted')->orderByDesc('id')->paginate(10)->withQueryString()]);
    }

    public function show(Request $request, int $training)
    {
        $training = $this->training($request, $training)->load(['association', 'programComponent']);

        return view('field-officer-user.trainings.show', [
            'training' => $training,
            // The member relationship is checked even for historical participant rows.
            'participants' => $training->participants()->whereHas('member', fn ($q) => $q->where('association_id', $training->association_id))
                ->with(['member', 'attendanceStatus'])->orderBy('id')->paginate(20)->withQueryString(),
            'members' => Member::where('association_id', $training->association_id)->where('is_archived', false)
                ->whereNotIn('id', $training->participants()->select('member_id'))->orderBy('last_name')->get(),
            'statuses' => Status::whereIn('status_name', TrainingManagementService::ATTENDANCE_STATUSES)->get(),
        ]);
    }

    public function update(UpdateTrainingRequest $request, int $training)
    {
        $this->service->updateOfficerInformation($this->training($request, $training), $request->validated(), $this->resolver->resolve($request));

        return redirect()->route('officer.trainings.show', $training)->with('success', 'Training information updated.');
    }

    public function addParticipant(Request $request, int $training)
    {
        $data = $request->validate(['member_id' => ['required', 'integer', 'min:1']]);
        $this->service->addParticipant($this->training($request, $training), (int) $data['member_id'], (int) $this->resolver->resolve($request)->id);

        return redirect()->route('officer.trainings.show', $training)->with('success', 'Participant registered with Pending attendance.');
    }

    public function attendance(Request $request, int $training, int $participant)
    {
        $data = $request->validate(['attendance_status_id' => ['required', 'integer', 'min:1']]);
        $this->service->attendance($this->training($request, $training), $participant, (int) $data['attendance_status_id'], (int) $this->resolver->resolve($request)->id);

        return redirect()->route('officer.trainings.show', $training)->with('success', 'Attendance saved.');
    }

    private function training(Request $request, int $id): Training
    {
        return $this->access->scope(Training::query(), $this->resolver->resolve($request))->findOrFail($id);
    }
}
