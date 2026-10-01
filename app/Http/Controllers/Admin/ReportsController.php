<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportsService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

final class ReportsController extends Controller
{
    public function index(Request $request, ReportsService $reports): Response
    {
        try {
            $filters = $this->filters($request);
            $officer = $this->officer($request);
            $data = $reports->overview($filters, $officer);

            return response()->view('shared.reports.index', [
                ...$data, 'filters' => $filters, 'reportRoutes' => $officer ? 'officer.reports' : 'reports',
                'areas' => DB::table('area_units')->when($officer, fn ($q) => $q->whereIn('id', DB::table('associations')->where('field_officer_id', $officer->id)->select('area_unit_id')))->orderBy('name')->get(['id', 'name']),
                'associations' => DB::table('associations')->where('is_archived', false)->when($officer, fn ($q) => $q->where('field_officer_id', $officer->id))->orderBy('name')->get(['id', 'name']),
            ])->header('Cache-Control', 'no-store, private');
        } catch (QueryException $exception) {
            return $this->unavailable($exception);
        }
    }

    public function export(Request $request, ReportsService $reports): Response
    {
        try {
            $filters = $this->filters($request);
            $data = $reports->overview($filters, $this->officer($request));
            $content = $this->csvContent($filters, $data);

            return response("\xEF\xBB\xBF".$content, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="assocmap-report-'.$filters['year'].'.csv"',
                'Cache-Control' => 'no-store, private',
            ]);
        } catch (QueryException|RuntimeException $exception) {
            // HTTP denials are deliberate authorization results, not export outages.
            if ($exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                throw $exception;
            }
            return $this->unavailable($exception);
        }
    }

    /** Build the whole download before headers are sent, so failures remain recoverable. */
    private function csvContent(array $filters, array $data): string
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new RuntimeException('The report file could not be opened.');
        }
        try {
            $write = fn (array $cells) => $this->writeCsvRow($stream, $cells);
            $this->writeCsvSummary($write, $filters, $data);
            $this->writeCsvTables($write, $data);
            rewind($stream);
            $content = stream_get_contents($stream);
            if ($content === false) {
                throw new RuntimeException('The report file could not be read.');
            }
            return $content;
        } finally {
            fclose($stream);
        }
    }

    /** @param resource $stream */
    private function writeCsvRow($stream, array $cells): void
    {
        // Names and remarks remain text even when a spreadsheet sees a formula prefix.
        $cells = array_map(static fn ($value) => is_string($value) && preg_match('/^[\s]*[=+@\-]/u', $value)
            ? "'".$value : $value, $cells);
        if (fputcsv($stream, $cells, ',', '"', '') === false) {
            throw new RuntimeException('The report file could not be written.');
        }
    }

    private function writeCsvSummary(callable $write, array $filters, array $data): void
    {
        $write(['AssocMap Reports & Analytics', 'Year', $filters['year']]);
        $write(['Generated (Asia/Manila)', $data['generatedAt']->format('Y-m-d H:i:s')]);
        $write(['Municipality ID', $filters['area_unit_id'] ?? 'All', 'Association ID', $filters['association_id'] ?? 'All']);
        $write(['Scope', 'Current associations, members and projects. Training dates and monitoring periods use the selected year.']);
        if ($data['rows']->isEmpty()) {
            $write(['No records found for the selected criteria']);
        }
        $write(['Summary', 'Count']);
        foreach ($data['counts'] as $label => $count) {
            $write([ucfirst($label), $count]);
        }
        $write(['Recorded gross income (PHP)', $data['incomeTotal']]);
        $write(['Income records', $data['incomeRecords']]);
        $write([]);
    }

    private function writeCsvTables(callable $write, array $data): void
    {
        $write(['Association', 'Municipality', 'Current members', 'Current projects', 'Trainings in year', 'Recorded gross income (PHP)']);
        foreach ($data['rows'] as $row) {
            $write([$row->name, $row->municipality, $row->members, $row->projects, $row->trainings, $row->income]);
        }
        $write([]);
        $write(['Month', 'Recorded gross income (PHP)', 'Income records']);
        foreach ($data['months'] as $month) {
            $write(array_values($month));
        }
        $write([]);
        $write(['Project status', 'Current projects']);
        foreach ($data['projectStatuses'] as $status) {
            $write([$status->status_name ?? 'Unspecified', $status->total]);
        }
        $write([]);
        $write(['Association', 'Project', 'Quarter', 'Target output', 'Actual output', 'Achievement (%)', 'Remarks / unit context']);
        foreach ($data['production'] as $row) {
            $write([$row->association, $row->title, $row->quarter_name, $row->target_output, $row->actual_output,
                (float) $row->target_output > 0 ? round((float) $row->actual_output / (float) $row->target_output * 100, 1) : 'N/A (zero target)', $row->remarks]);
        }
        if (isset($data['trainedMembers'])) {
            $write([]);
            $write(['Program component', 'Distinct members with Present attendance']);
            foreach ($data['trainedMembers'] as $row) {
                $write([$row->name ?? 'Not recorded', $row->members]);
            }
        }
    }

    private function filters(Request $request): array
    {
        $officer = $this->officer($request);
        // Validate filters before using them in queries or export filenames.
        $filters = $request->validate([
            'year' => ['nullable', 'integer', 'between:1900,2100'],
            'area_unit_id' => ['bail', 'nullable', 'integer', Rule::exists('area_units', 'id')],
            // A query callback keeps the PostgreSQL boolean from becoming an empty rule string.
            'association_id' => $officer ? ['nullable', 'integer', 'min:1'] : ['bail', 'nullable', 'integer', Rule::exists('associations', 'id')->where(fn ($query) => $query->where('is_archived', false))],
        ]);
        $filters['year'] = $filters['year'] ?? now('Asia/Manila')->year;
        if ($officer && ! empty($filters['association_id'])) {
            abort_unless(DB::table('associations')->where('id', $filters['association_id'])->where('field_officer_id', $officer->id)->where('is_archived', false)->exists(), 404);
        }
        if ($officer && ! empty($filters['area_unit_id'])) {
            if (! DB::table('associations')->where('field_officer_id', $officer->id)->where('area_unit_id', $filters['area_unit_id'])->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['area_unit_id' => 'Choose a municipality containing your assigned associations.']);
            }
        }

        return $filters;
    }

    private function officer(Request $request): ?\App\Models\User
    {
        $actor = app(\App\Services\SessionUserResolver::class)->resolve($request);
        abort_unless($actor->is_active && in_array($actor->role?->role_name, ['System Administrator', 'Field Officer'], true), 403);

        return $actor->role->role_name === 'Field Officer' ? $actor : null;
    }

    private function unavailable(\Throwable $exception): Response
    {
        report($exception);

        // Missing data is a service error, not a report with zero activity.
        return response()->view('shared.reports.index', ['unavailable' => true, 'reportRoutes' => request()->routeIs('officer.*') ? 'officer.reports' : 'reports'], 503)
            ->header('Cache-Control', 'no-store, private');
    }
}
