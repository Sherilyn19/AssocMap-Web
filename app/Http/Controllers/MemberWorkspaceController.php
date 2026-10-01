<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\{Member, MemberApplication, Project, Training};
use App\Services\{MemberWorkspaceAccess, SessionUserResolver};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Read-only pages; existing operational write permissions remain separate. */
final class MemberWorkspaceController extends Controller
{
    public function __construct(private MemberWorkspaceAccess $access, private SessionUserResolver $resolver) {}

    private function association(Request $request): ?\App\Models\Association
    {
        return $this->access->association($this->resolver->resolve($request));
    }

    public function dashboard(Request $request)
    {
        $association = $this->association($request);
        $applications = $this->access->scope(MemberApplication::with('status'), $association);
        $projects = $this->access->scope(Project::with('status')->where('is_archived', false), $association);
        $trainings = $this->access->scope(Training::query()->where('is_archived', false), $association);
        return view('association-member-user.dashboard', [
            'association' => $association,
            'counts' => $association ? [
                'Members' => $this->access->scope(Member::query(), $association)->where('is_archived', false)->count(),
                'Pending applications' => (clone $applications)->whereHas('status', fn ($q) => $q->where('status_name', 'Pending'))->count(),
                'Current projects' => (clone $projects)->count(), 'Trainings' => (clone $trainings)->count(),
            ] : [],
            'applications' => $applications->orderByDesc('created_at')->orderByDesc('id')->limit(4)->get(),
            'projects' => $projects->orderByDesc('updated_at')->orderByDesc('id')->limit(4)->get(),
            'trainings' => $trainings->orderByDesc('date_conducted')->orderByDesc('id')->limit(4)->get(),
            'production' => $this->access->production($association)->orderByDesc('m.year')->orderByDesc('m.id')->limit(4)->get(),
        ]);
    }

    public function information(Request $request)
    {
        return view('association-member-user.information', ['association' => $this->association($request)]);
    }

    public function members(Request $request)
    {
        $filters = $this->filters($request, ['Current', 'Archived', 'All'], 'Current');
        $association = $this->association($request);
        $query = $this->access->scope(Member::query(), $association);
        if ($filters['status'] !== 'All') $query->where('is_archived', $filters['status'] === 'Archived');
        $query->whereRaw("CONCAT_WS(' ', first_name, middle_name, last_name) ILIKE ?", ['%'.$filters['search'].'%']);
        return view('association-member-user.people', [
            'association' => $association, 'filters' => $filters, 'applicationsPage' => false,
            'records' => $query->orderBy('last_name')->orderBy('id')->paginate(15)->withQueryString(),
        ]);
    }

    public function applications(Request $request)
    {
        $filters = $this->filters($request, ['Pending', 'Approved', 'Rejected']);
        $association = $this->association($request);
        $query = $this->access->scope(MemberApplication::with('status'), $association);
        if ($filters['status'] !== '') $query->whereHas('status', fn ($q) => $q->where('status_name', $filters['status']));
        $query->whereRaw("CONCAT_WS(' ', first_name, middle_name, last_name) ILIKE ?", ['%'.$filters['search'].'%']);
        return view('association-member-user.people', [
            'association' => $association, 'filters' => $filters, 'applicationsPage' => true,
            'records' => $query->orderByDesc('created_at')->orderByDesc('id')->paginate(15)->withQueryString(),
        ]);
    }

    public function projects(Request $request)
    {
        $filters = $this->filters($request, ['Planned', 'Ongoing', 'Completed', 'Archived']);
        $association = $this->association($request);
        $query = $this->access->scope(Project::with(['status', 'programComponent']), $association);
        $query->where('is_archived', $filters['status'] === 'Archived')->where('title', 'ilike', '%'.$filters['search'].'%');
        if ($filters['status'] !== '' && $filters['status'] !== 'Archived') $query->whereHas('status', fn ($q) => $q->where('status_name', $filters['status']));
        return view('association-member-user.projects', compact('association', 'filters') + [
            'projects' => $query->orderBy('title')->orderBy('id')->paginate(12)->withQueryString(),
        ]);
    }

    public function project(Request $request, int $project)
    {
        $association = $this->association($request);
        $project = $this->access->scope(Project::with(['status', 'programComponent', 'materials.status']), $association)->findOrFail($project);
        return view('association-member-user.project', compact('association', 'project'));
    }

    public function trainings(Request $request)
    {
        $filters = $this->filters($request, ['Current', 'Archived', 'All'], 'Current');
        $association = $this->association($request);
        $query = $this->access->scope(Training::with('programComponent'), $association)->where('title', 'ilike', '%'.$filters['search'].'%');
        if ($filters['status'] !== 'All') $query->where('is_archived', $filters['status'] === 'Archived');
        return view('association-member-user.trainings', compact('association', 'filters') + [
            'trainings' => $query->orderByDesc('date_conducted')->orderByDesc('id')->paginate(12)->withQueryString(),
        ]);
    }

    public function training(Request $request, int $training)
    {
        $association = $this->association($request);
        $training = $this->access->scope(Training::with('programComponent'), $association)->findOrFail($training);
        // Aggregate attendance without adding new personal participant disclosures.
        $attendance = $training->participants()->whereHas('member', fn ($q) => $q->where('association_id', $association->id))
            ->join('statuses', 'statuses.id', '=', 'training_participants.attendance_status_id')
            ->selectRaw('statuses.status_name, count(*) as total')->groupBy('statuses.status_name')->pluck('total', 'status_name');
        return view('association-member-user.training', compact('association', 'training', 'attendance'));
    }

    public function production(Request $request)
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:255'], 'year' => ['nullable', 'integer', 'between:1900,9999'], 'page' => ['nullable', 'integer', 'min:1', 'max:100000']]);
        $association = $this->association($request);
        $query = $this->access->production($association);
        $years = (clone $query)->select('m.year')->distinct()->orderByDesc('m.year')->pluck('year');
        if ($filters['year'] ?? null) $query->where('m.year', $filters['year']);
        if ($filters['search'] ?? null) $query->where('p.title', 'ilike', '%'.$filters['search'].'%');
        return view('association-member-user.production', compact('association', 'filters', 'years') + [
            'records' => $query->orderByDesc('m.year')->orderByDesc('m.id')->paginate(15)->withQueryString(),
        ]);
    }

    private function filters(Request $request, array $statuses, string $default = ''): array
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', Rule::in($statuses)], 'page' => ['nullable', 'integer', 'min:1', 'max:100000']]);
        return ['search' => $data['search'] ?? '', 'status' => $data['status'] ?? $default];
    }
}
