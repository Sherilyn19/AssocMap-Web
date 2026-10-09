<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ReportsService;
use App\Services\SessionUserResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

final class MemberReportsController extends Controller
{
    public function index(Request $request, ReportsService $reports): Response
    {
        return response()
            ->view('association-member-user.reports', $this->data($request, $reports))
            ->header('Cache-Control', 'no-store, private');
    }

    public function export(Request $request, ReportsService $reports): Response
    {
        // The screen and download use the same validated filters and server scope.
        $data = $this->data($request, $reports);

        $rows = [
            ['AssocMap Association Report'],
            ['Association', $data['rows']->first()?->name ?? 'No current association'],
            ['Reporting year', $data['year']],
            ['Generated (Asia/Manila)', $data['generatedAt']->format('Y-m-d H:i:s')],
            ['Scope', 'Current members and projects; trainings and monitoring in the selected year.'],
            [],
            ['Summary', 'Count'],
            ['Current members', $data['counts']['members']],
            ['Current projects', $data['counts']['projects']],
            ['Trainings in selected year', $data['counts']['trainings']],
            ['Recorded gross income (PHP)', $data['incomeTotal']],
            [],
            ['Month', 'Recorded gross income (PHP)', 'Income records'],
        ];

        foreach ($data['months'] as $month) {
            $rows[] = [
                $month['label'],
                $month['records'] ? $month['total'] : 'No records',
                $month['records'],
            ];
        }

        $rows[] = [];
        $rows[] = ['Project status', 'Current projects'];

        foreach ($data['projectStatuses'] as $status) {
            $rows[] = [$status->status_name ?? 'Not recorded', $status->total];
        }

        $rows[] = [];
        $rows[] = ['Project', 'Quarter', 'Target output', 'Actual output', 'Achievement (%)'];

        foreach ($data['production'] as $record) {
            $rows[] = [
                $record->title,
                $record->quarter_name,
                $record->target_output,
                $record->actual_output,
                (float) $record->target_output > 0
                    ? round(
                        (float) $record->actual_output
                        / (float) $record->target_output * 100,
                        1
                    )
                    : 'N/A (zero target)',
            ];
        }

        $stream = fopen('php://temp', 'w+');

        if ($stream === false) {
            return $this->unavailable();
        }

        try {
            foreach ($rows as $row) {
                // Treat formula-like names as text when the CSV opens in a spreadsheet.
                $cells = array_map(
                    static fn ($value) =>
                        is_string($value) && preg_match('/^[\s]*[=+@\-]/u', $value)
                            ? "'".$value
                            : $value,
                    $row
                );

                if (fputcsv($stream, $cells, ',', '"', '') === false) {
                    throw new RuntimeException('Unable to write report CSV.');
                }
            }

            if (!rewind($stream)) {
                throw new RuntimeException('Unable to read report CSV.');
            }

            $content = stream_get_contents($stream);

            if ($content === false) {
                throw new RuntimeException('Unable to read report CSV.');
            }

            return response("\xEF\xBB\xBF".$content, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' =>
                    'attachment; filename="association-report-'.$data['year'].'.csv"',
                'Cache-Control' => 'no-store, private',
            ]);
        } catch (RuntimeException $exception) {
            report($exception);

            return $this->unavailable();
        } finally {
            fclose($stream);
        }
    }

    private function data(Request $request, ReportsService $reports): array
    {
        $actor = app(SessionUserResolver::class)->resolve($request);

        $filters = $request->validate([
            'year' => ['nullable', 'integer', 'between:1900,2100'],
            'association_id' => ['prohibited'],
            'area_unit_id' => ['prohibited'],
        ]);

        $year = (int) ($filters['year'] ?? now('Asia/Manila')->year);

        return $reports->forMember($actor, $year) + ['year' => $year];
    }

    private function unavailable(): Response
    {
        return response()
            ->view('association-member-user.unavailable', [], 503)
            ->header('Cache-Control', 'no-store, private');
    }
}