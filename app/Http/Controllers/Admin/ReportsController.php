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
            $data = $reports->overview($filters);

            return response()->view('admin-pages.reports', [
                ...$data, 'filters' => $filters,
                'areas' => DB::table('area_units')->orderBy('name')->get(['id', 'name']),
                'associations' => DB::table('associations')->where('is_archived', false)->orderBy('name')->get(['id', 'name']),
            ])->header('Cache-Control', 'no-store, private');
        } catch (QueryException $exception) {
            return $this->unavailable($exception);
        }
    }

    public function export(Request $request, ReportsService $reports): Response
    {
        try {
            $filters = $this->filters($request);
            $data = $reports->overview($filters);
            // Build the file before sending headers so a failed export can show a useful error page.
            $stream = fopen('php://temp', 'w+');
            if ($stream === false) {
                throw new RuntimeException('The report file could not be opened.');
            }
            try {
                $write = function (array $cells) use ($stream): void {
                    // Treat names and remarks as text, even when a spreadsheet sees a formula prefix.
                    $cells = array_map(static fn ($value) => is_string($value) && preg_match('/^[\s]*[=+@\-]/u', $value)
                        ? "'".$value : $value, $cells);
                    if (fputcsv($stream, $cells, ',', '"', '') === false) {
                        throw new RuntimeException('The report file could not be written.');
                    }
                };
                $write(['AssocMap Reports & Analytics', 'Year', $filters['year']]);
                $write(['Generated (Asia/Manila)', $data['generatedAt']->format('Y-m-d H:i:s')]);
                $write(['Municipality ID', $filters['area_unit_id'] ?? 'All', 'Association ID', $filters['association_id'] ?? 'All']);
                $write(['Scope', 'Current associations, members and projects. Training dates and monitoring periods use the selected year.']);
                $write(['Summary', 'Count']);
                foreach ($data['counts'] as $label => $count) {
                    $write([ucfirst($label), $count]);
                }
                $write(['Recorded gross income (PHP)', $data['incomeTotal']]);
                $write(['Income records', $data['incomeRecords']]);
                $write([]);
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
                rewind($stream);
                $content = stream_get_contents($stream);
                if ($content === false) {
                    throw new RuntimeException('The report file could not be read.');
                }
            } finally {
                fclose($stream);
            }

            return response("\xEF\xBB\xBF".$content, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="assocmap-report-'.$filters['year'].'.csv"',
                'Cache-Control' => 'no-store, private',
            ]);
        } catch (QueryException|RuntimeException $exception) {
            return $this->unavailable($exception);
        }
    }

    private function filters(Request $request): array
    {
        // Validate filters before using them in queries or export filenames.
        $filters = $request->validate([
            'year' => ['nullable', 'integer', 'between:1900,2100'],
            'area_unit_id' => ['bail', 'nullable', 'integer', Rule::exists('area_units', 'id')],
            // A query callback keeps the PostgreSQL boolean from becoming an empty rule string.
            'association_id' => ['bail', 'nullable', 'integer', Rule::exists('associations', 'id')->where(fn ($query) => $query->where('is_archived', false))],
        ]);
        $filters['year'] = $filters['year'] ?? now('Asia/Manila')->year;

        return $filters;
    }

    private function unavailable(\Throwable $exception): Response
    {
        report($exception);

        // Missing data is a service error, not a report with zero activity.
        return response()->view('admin-pages.reports', ['unavailable' => true], 503)
            ->header('Cache-Control', 'no-store, private');
    }
}
