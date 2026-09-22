<?php

namespace Botble\RealEstate\Commands;

use Botble\RealEstate\Services\Redbricks\RedbricksClient;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

/**
 * Samples the Redbricks catalogue and writes an .xlsx describing what the API
 * actually returns: every project field with its fill rate and an example, the
 * floor plan and document schemas for one project, and a sheet diffing the live
 * response against their published reference.
 *
 * That last sheet is the point. Their documentation already contradicts itself —
 * transit_score, neighbourhood_id, underground_levels, residential_parking and
 * visitor_parking are each typed as an integer on the list endpoint and a
 * nullable string on the single-project endpoint — so the mapping is written
 * against this report, not against the docs.
 */
#[AsCommand('cms:redbricks:field-report', 'Export Redbricks field coverage to .xlsx and diff it against the published schema')]
class RedbricksFieldReportCommand extends Command
{
    /**
     * Project fields as published in the Redbricks reference, September 2026.
     * Used only to flag drift; the live response is always the source of truth.
     *
     * @var array<int, string>
     */
    protected const DOCUMENTED_PROJECT_FIELDS = [
        'address', 'amenities', 'architect_id', 'architect_name', 'architects',
        'bedrooms', 'bedrooms_info', 'bedrooms_percents', 'bike_parking',
        'building_height_m', 'building_type', 'city_id', 'city_name', 'created_at',
        'current_price_from', 'current_price_to', 'current_sales_status',
        'deposit', 'deposit_data', 'deposit_full', 'deposit_with_time',
        'description', 'developer_id', 'developer_name', 'developers',
        'development_charges', 'district_id', 'district_name', 'finishes',
        'floorplan_premiums', 'gfa', 'google_drive_archive_portal',
        'google_drive_portal', 'google_map_link', 'highlights',
        'historical_documents', 'id', 'industrial_parking',
        'interior_designer_id', 'interior_designer_name', 'interior_designers',
        'latest_documents', 'launch_date', 'launch_price', 'launch_price_project',
        'launch_psf_avg', 'location', 'locker_details', 'locker_price',
        'low_rise_storeys', 'low_rise_types', 'maintenance_fees',
        'maintenance_fees_details', 'media', 'name', 'neighbourhood_id',
        'neighbourhood_name', 'occupancy_date', 'office_parking',
        'ownership_types', 'parking_details', 'parking_ratio', 'parking_price',
        'price_per_sqft', 'residential_parking', 'residential_parking_ratio',
        'retail_parking', 'sales_marketing_companies', 'size', 'slug',
        'special_incentives', 'status', 'storeys', 'suites', 'total_parking',
        'townhouse_types', 'transit_score', 'underground_levels', 'units_affordable_rental',
        'units_condo', 'units_hotel', 'units_market_rate_rental', 'units_rental',
        'units_rental_replacement', 'updated_at', 'visitor_parking', 'walk_score',
    ];

    public function handle(RedbricksClient $client): int
    {
        if (! $client->hasCredentials()) {
            $this->components->error('REDBRICKS_API_KEY is not set in .env — nothing to call.');

            return self::FAILURE;
        }

        $count = max((int) $this->option('count'), 1);

        try {
            $body = $client->projects(1, $count, $this->filters());
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $projects = array_values(array_filter(
            Arr::get($body, 'data', []),
            fn ($row): bool => is_array($row) && $row !== []
        ));

        if ($projects === []) {
            $this->components->error('The API returned no projects for that filter.');

            return self::FAILURE;
        }

        $meta = Arr::get($body, 'meta', []);
        $firstId = Arr::get($projects, '0.id');

        $floorPlans = $this->safeFetch(
            fn (): array => $client->floorPlansFor($firstId),
            'floor plans'
        );

        $documents = $this->safeFetch(
            fn (): array => $client->documentsFor($firstId),
            'documents'
        );

        $path = $this->resolveOutputPath();

        $this->writeWorkbook($path, $projects, $meta, $floorPlans, $documents, $firstId);

        $this->components->twoColumnDetail('Catalogue size', (string) (Arr::get($meta, 'total') ?? 'unknown'));
        $this->components->twoColumnDetail('Projects sampled', (string) count($projects));
        $this->components->twoColumnDetail('Floor plans (project ' . $firstId . ')', (string) count($floorPlans['rows']));
        $this->components->twoColumnDetail('Documents (project ' . $firstId . ')', (string) count($documents['rows']));
        $this->components->success(sprintf('Written to %s', $path));

        return self::SUCCESS;
    }

    /**
     * Sub-resources may be tier-gated, so a failure here is reported in the
     * workbook rather than aborting a run that already has useful project data.
     *
     * @param  callable(): array<int, array<string, mixed>>  $fetch
     * @return array{rows: array<int, array<string, mixed>>, error: string|null}
     */
    protected function safeFetch(callable $fetch, string $label): array
    {
        try {
            return ['rows' => $fetch(), 'error' => null];
        } catch (Throwable $e) {
            $this->components->warn(sprintf('Could not fetch %s: %s', $label, $e->getMessage()));

            return ['rows' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function filters(): array
    {
        $filters = [];

        if ($cities = config('plugins.real-estate.redbricks.cities')) {
            $filters['city'] = $cities;
        }

        if ($extra = config('plugins.real-estate.redbricks.extra_query')) {
            parse_str((string) $extra, $parsed);
            $filters = array_merge($filters, $parsed);
        }

        return $filters;
    }

    /**
     * @param  array<int, array<string, mixed>>  $projects
     * @param  array<string, mixed>  $meta
     * @param  array{rows: array<int, array<string, mixed>>, error: string|null}  $floorPlans
     * @param  array{rows: array<int, array<string, mixed>>, error: string|null}  $documents
     */
    protected function writeWorkbook(
        string $path,
        array $projects,
        array $meta,
        array $floorPlans,
        array $documents,
        mixed $firstId
    ): void {
        $book = new Spreadsheet();
        $book->removeSheetByIndex(0);

        $this->buildSummarySheet($book, $projects, $meta, $floorPlans, $documents);
        $this->buildFieldSheet($book, 'Projects', $projects);
        $this->buildDriftSheet($book, $projects);

        if ($floorPlans['rows'] !== []) {
            $this->buildFieldSheet($book, 'Floor plans', $floorPlans['rows']);
        }

        if ($documents['rows'] !== []) {
            $this->buildFieldSheet($book, 'Documents', $documents['rows']);
        }

        $book->setActiveSheetIndex(0);
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();
    }

    /**
     * @param  array<int, array<string, mixed>>  $projects
     * @param  array<string, mixed>  $meta
     * @param  array{rows: array<int, array<string, mixed>>, error: string|null}  $floorPlans
     * @param  array{rows: array<int, array<string, mixed>>, error: string|null}  $documents
     */
    protected function buildSummarySheet(
        Spreadsheet $book,
        array $projects,
        array $meta,
        array $floorPlans,
        array $documents
    ): void {
        $sheet = $book->createSheet();
        $sheet->setTitle('Summary');

        $fields = $this->populatedCounts($projects);
        $everPopulated = array_filter($fields, fn (int $n): bool => $n > 0);

        $rows = [
            ['Redbricks field report'],
            ['Generated', now()->format('Y-m-d H:i')],
            ['Endpoint', rtrim((string) config('plugins.real-estate.redbricks.base_url'), '/') . '/projects'],
            [],
            ['Catalogue size (meta.total)', Arr::get($meta, 'total') ?? 'unknown'],
            ['Pages at this page size', Arr::get($meta, 'last_page') ?? 'unknown'],
            ['Projects sampled', count($projects)],
            ['Distinct project fields returned', count($fields)],
            ['Populated on at least one', count($everPopulated)],
            ['Empty on every sampled project', count($fields) - count($everPopulated)],
            [],
            ['Floor plans returned', $floorPlans['error'] ?? count($floorPlans['rows'])],
            ['Documents returned', $documents['error'] ?? count($documents['rows'])],
            [],
            ['The "Schema drift" sheet compares this response against the published reference.'],
            ['Where they disagree, the live response wins — write the mapping from these sheets.'],
        ];

        $sheet->fromArray($rows, null, 'A1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A15:A16')->getFont()->setItalic(true);
        $this->autoSize($sheet, 'B');
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function buildFieldSheet(Spreadsheet $book, string $title, array $rows): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle(substr($title, 0, 31));

        $populated = $this->populatedCounts($rows);
        ksort($populated);
        $total = count($rows);

        $sheet->fromArray([['Field', 'Type', 'Populated', 'Fill %', 'Example value']], null, 'A1');

        $line = 2;

        foreach ($populated as $field => $hits) {
            $example = $this->firstNonEmpty($rows, (string) $field);

            $sheet->fromArray([[
                $field,
                $this->describeType($example),
                sprintf('%d/%d', $hits, $total),
                $total > 0 ? round($hits / $total * 100) . '%' : '—',
                $this->truncate($this->formatValue($example), 300),
            ]], null, 'A' . $line);

            if ($hits === 0) {
                $this->shade($sheet, 'A' . $line . ':E' . $line, 'FFF2F2F2');
            }

            $line++;
        }

        $sheet->getStyle('A1:E1')->getFont()->setBold(true);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:E' . max($line - 1, 1));
        $this->autoSize($sheet, 'D');
        $sheet->getColumnDimension('E')->setWidth(70);
        $sheet->getStyle('E2:E' . max($line - 1, 2))->getAlignment()->setWrapText(true);
    }

    /**
     * @param  array<int, array<string, mixed>>  $projects
     */
    protected function buildDriftSheet(Spreadsheet $book, array $projects): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle('Schema drift');

        $live = array_keys($this->populatedCounts($projects));
        $documented = self::DOCUMENTED_PROJECT_FIELDS;

        $missing = array_values(array_diff($documented, $live));
        $undocumented = array_values(array_diff($live, $documented));

        $sheet->fromArray([
            ['Live response vs published reference'],
            ['Documented fields', count($documented)],
            ['Live fields', count($live)],
            [],
            ['Documented but NOT returned', count($missing)],
            ['Returned but NOT documented', count($undocumented)],
            [],
            ['Field', 'Status'],
        ], null, 'A1');

        $line = 9;

        foreach ($missing as $field) {
            $sheet->fromArray([[$field, 'documented, not returned']], null, 'A' . $line);
            $this->shade($sheet, 'A' . $line . ':B' . $line, 'FFF8D7DA');
            $line++;
        }

        foreach ($undocumented as $field) {
            $sheet->fromArray([[$field, 'returned, not documented']], null, 'A' . $line);
            $this->shade($sheet, 'A' . $line . ':B' . $line, 'FFFFF3CD');
            $line++;
        }

        if ($missing === [] && $undocumented === []) {
            $sheet->setCellValue('A9', 'No drift — the live response matches the published reference exactly.');
        }

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A8:B8')->getFont()->setBold(true);
        $this->autoSize($sheet, 'B');
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    protected function populatedCounts(array $rows): array
    {
        $counts = [];

        foreach ($rows as $row) {
            foreach ($row as $field => $value) {
                $counts[$field] ??= 0;

                if (! $this->isEmpty($value)) {
                    $counts[$field]++;
                }
            }
        }

        return $counts;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function firstNonEmpty(array $rows, string $field): mixed
    {
        foreach ($rows as $row) {
            if (array_key_exists($field, $row) && ! $this->isEmpty($row[$field])) {
                return $row[$field];
            }
        }

        return null;
    }

    protected function resolveOutputPath(): string
    {
        if ($out = $this->option('out')) {
            return (string) $out;
        }

        $directory = storage_path('app/redbricks');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return $directory . DIRECTORY_SEPARATOR . sprintf('field-report-%s.xlsx', now()->format('Ymd-His'));
    }

    protected function describeType(mixed $value): string
    {
        return match (true) {
            is_array($value) => array_is_list($value)
                ? sprintf('array[%d]', count($value))
                : sprintf('object{%s}', implode(',', array_slice(array_keys($value), 0, 4))),
            is_bool($value) => 'bool',
            is_int($value) => 'int',
            is_float($value) => 'float',
            is_null($value) => 'null / empty',
            default => 'string',
        };
    }

    protected function formatValue(mixed $value): string
    {
        return match (true) {
            is_null($value) => '—',
            is_bool($value) => $value ? 'true' : 'false',
            is_array($value) => (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            default => (string) $value,
        };
    }

    protected function truncate(string $value, int $limit): string
    {
        return mb_strlen($value) > $limit ? mb_substr($value, 0, $limit) . '…' : $value;
    }

    protected function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    protected function shade(mixed $sheet, string $range, string $argb): void
    {
        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB($argb);
    }

    protected function autoSize(mixed $sheet, string $lastColumn): void
    {
        foreach (range('A', $lastColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    protected function configure(): void
    {
        $this->addOption('count', null, InputOption::VALUE_REQUIRED, 'Projects to sample (max 200).', 25);
        $this->addOption('out', null, InputOption::VALUE_REQUIRED, 'Write the .xlsx to this path.');
    }
}
