<?php

namespace Botble\RealEstate\Commands;

use Botble\RealEstate\Services\Buildify\BuildifyClient;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

/**
 * Diagnostic: samples the Buildify catalogue across one or more API versions and
 * writes an .xlsx showing every field the gateway actually returned, how often
 * each is populated, whether our syncer consumes it, and which versions carry it.
 *
 * Buildify does not publish its plan tiers — pricing is sales-gated — so the only
 * way to establish what a given key buys is to call it and diff the result. The
 * version axis (v1 / v1-lite / v1-sandbox) is the closest observable proxy: if a
 * plan gates fields, the lite response is where that shows up.
 *
 * They advertise 150+ property attributes; our syncer reads roughly 50. The
 * "Used?" column exists to size that gap honestly.
 */
#[AsCommand('cms:buildify:field-report', 'Export Buildify field coverage across API versions to .xlsx')]
class BuildifyFieldReportCommand extends Command
{
    public function handle(): int
    {
        $apiKey = (string) config('plugins.real-estate.buildify.api_key');

        if ($apiKey === '') {
            $this->components->error('BUILDIFY_API_KEY is empty in .env — nothing to call.');

            return self::FAILURE;
        }

        $count = max((int) $this->option('count'), 1);
        $versions = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) $this->option('versions'))
        )));

        $syncerSource = $this->syncerSource();

        /** @var array<string, array{listings: array<int, array<string, mixed>>, error: string|null}> $probes */
        $probes = [];

        foreach ($versions as $version) {
            $this->components->task(sprintf('Sampling %s', $version), function () use (
                $version, $count, $apiKey, &$probes
            ): bool {
                try {
                    $client = new BuildifyClient($apiKey, null, $version);
                    $body = $client->searchListings(0, $count);

                    $probes[$version] = [
                        'listings' => array_values(array_filter(
                            Arr::get($body, 'results', []),
                            fn ($row): bool => is_array($row) && $row !== []
                        )),
                        'error' => null,
                    ];

                    return true;
                } catch (Throwable $e) {
                    // A version the plan does not include fails here — that is a
                    // result, not a crash, so it is recorded and reported.
                    $probes[$version] = ['listings' => [], 'error' => $this->shortError($e)];

                    return false;
                }
            });
        }

        if (! collect($probes)->contains(fn (array $p): bool => $p['listings'] !== [])) {
            $this->components->error('No version returned any listings. Check the key and the province setting.');

            foreach ($probes as $version => $probe) {
                $this->components->twoColumnDetail($version, $probe['error'] ?? 'empty result');
            }

            return self::FAILURE;
        }

        $path = $this->resolveOutputPath();

        $this->writeWorkbook($path, $probes, $syncerSource);

        foreach ($probes as $version => $probe) {
            $this->components->twoColumnDetail(
                $version,
                $probe['error'] ?? sprintf('%d listings, %d fields', count($probe['listings']), count($this->unionFields($probe['listings'])))
            );
        }

        $this->components->success(sprintf('Written to %s', $path));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, array{listings: array<int, array<string, mixed>>, error: string|null}>  $probes
     */
    protected function writeWorkbook(string $path, array $probes, ?string $syncerSource): void
    {
        $book = new Spreadsheet();
        $book->removeSheetByIndex(0);

        $this->buildSummarySheet($book, $probes, $syncerSource);

        foreach ($probes as $version => $probe) {
            if ($probe['listings'] === []) {
                continue;
            }

            $this->buildVersionSheet($book, $version, $probe['listings'], $syncerSource);
        }

        $withData = array_filter($probes, fn (array $p): bool => $p['listings'] !== []);

        if (count($withData) > 1) {
            $this->buildDiffSheet($book, $withData);
        }

        $book->setActiveSheetIndex(0);
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();
    }

    /**
     * @param  array<string, array{listings: array<int, array<string, mixed>>, error: string|null}>  $probes
     */
    protected function buildSummarySheet(Spreadsheet $book, array $probes, ?string $syncerSource): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle('Summary');

        $sheet->fromArray([
            ['Buildify field report'],
            ['Generated', now()->format('Y-m-d H:i')],
            ['Endpoint', rtrim((string) config('plugins.real-estate.buildify.base_url'), '/') . '/{version}/{province}/search_listings'],
            ['Province', (string) config('plugins.real-estate.buildify.province')],
            [],
            ['Buildify does not publish plan tiers. Versions are probed as a proxy: a field gated by plan'],
            ['should appear in one version and not another. A version that errors is very likely not on this key\'s plan.'],
            [],
            ['Version', 'Listings', 'Fields returned', 'Populated somewhere', 'Used by syncer', 'Result'],
        ], null, 'A1');

        $row = 10;

        foreach ($probes as $version => $probe) {
            $fields = $this->unionFields($probe['listings']);
            $populated = $this->populatedCounts($probe['listings']);
            $everPopulated = array_filter($populated, fn (int $n): bool => $n > 0);

            $used = $syncerSource === null ? null : count(array_filter(
                array_keys($everPopulated),
                fn (string $f): bool => $this->isConsumed($syncerSource, $f)
            ));

            $sheet->fromArray([[
                $version,
                count($probe['listings']),
                count($fields),
                count($everPopulated),
                $used ?? 'n/a',
                $probe['error'] ?? 'ok',
            ]], null, 'A' . $row);

            $row++;
        }

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A9:F9')->getFont()->setBold(true);
        $sheet->getStyle('A6:A7')->getFont()->setItalic(true);
        $this->autoSize($sheet, 'F');
    }

    /**
     * @param  array<int, array<string, mixed>>  $listings
     */
    protected function buildVersionSheet(Spreadsheet $book, string $version, array $listings, ?string $syncerSource): void
    {
        $sheet = $book->createSheet();
        // Excel sheet names cap at 31 chars and reject several punctuation marks.
        $sheet->setTitle(substr(preg_replace('/[\\\\\/?*\[\]:]/', '-', $version) ?: 'version', 0, 31));

        $populated = $this->populatedCounts($listings);
        ksort($populated);
        $total = count($listings);

        $header = ['Field', 'Type', 'Populated', 'Fill %', 'Used by syncer', 'Example value'];
        $sheet->fromArray([$header], null, 'A1');

        $row = 2;

        foreach ($populated as $field => $hits) {
            $example = $this->firstNonEmpty($listings, (string) $field);

            $sheet->fromArray([[
                $field,
                $this->describeType($example),
                sprintf('%d/%d', $hits, $total),
                $total > 0 ? round($hits / $total * 100) . '%' : '—',
                $syncerSource === null ? 'n/a' : ($this->isConsumed($syncerSource, (string) $field) ? 'yes' : 'no'),
                $this->truncate($this->formatValue($example), 300),
            ]], null, 'A' . $row);

            if ($hits === 0) {
                $this->shade($sheet, 'A' . $row . ':F' . $row, 'FFF2F2F2');
            }

            $row++;
        }

        $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:F' . max($row - 1, 1));
        $this->autoSize($sheet, 'E');
        $sheet->getColumnDimension('F')->setWidth(70);
        $sheet->getStyle('F2:F' . max($row - 1, 2))->getAlignment()->setWrapText(true);
    }

    /**
     * @param  array<string, array{listings: array<int, array<string, mixed>>, error: string|null}>  $probes
     */
    protected function buildDiffSheet(Spreadsheet $book, array $probes): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle('Version diff');

        $versions = array_keys($probes);

        $union = [];
        foreach ($probes as $probe) {
            $union = array_unique(array_merge($union, $this->unionFields($probe['listings'])));
        }
        sort($union);

        $sheet->fromArray([array_merge(['Field'], $versions, ['Only in'])], null, 'A1');

        $row = 2;

        foreach ($union as $field) {
            $cells = [$field];
            $present = [];

            foreach ($probes as $version => $probe) {
                $populated = $this->populatedCounts($probe['listings']);
                $has = array_key_exists($field, $populated);
                $cells[] = $has ? sprintf('%d/%d', $populated[$field], count($probe['listings'])) : '—';

                if ($has) {
                    $present[] = $version;
                }
            }

            $exclusive = count($present) === 1 && count($versions) > 1;
            $cells[] = $exclusive ? $present[0] : '';

            $sheet->fromArray([$cells], null, 'A' . $row);

            if ($exclusive) {
                // The whole point of the sheet: a field one version has and another doesn't.
                $this->shade($sheet, 'A' . $row . ':' . $this->columnLetter(count($cells)) . $row, 'FFFFF3CD');
            }

            $row++;
        }

        $sheet->getStyle('A1:' . $this->columnLetter(count($versions) + 2) . '1')->getFont()->setBold(true);
        $sheet->freezePane('A2');
        $this->autoSize($sheet, $this->columnLetter(count($versions) + 2));
    }

    /**
     * @param  array<int, array<string, mixed>>  $listings
     * @return array<int, string>
     */
    protected function unionFields(array $listings): array
    {
        $union = [];

        foreach ($listings as $listing) {
            $union = array_unique(array_merge($union, array_keys($listing)));
        }

        sort($union);

        return $union;
    }

    /**
     * Field name => how many of the sampled listings carried a non-empty value.
     *
     * @param  array<int, array<string, mixed>>  $listings
     * @return array<string, int>
     */
    protected function populatedCounts(array $listings): array
    {
        $counts = [];

        foreach ($listings as $listing) {
            foreach ($listing as $field => $value) {
                $counts[$field] ??= 0;

                if (! $this->isEmpty($value)) {
                    $counts[$field]++;
                }
            }
        }

        return $counts;
    }

    /**
     * @param  array<int, array<string, mixed>>  $listings
     */
    protected function firstNonEmpty(array $listings, string $field): mixed
    {
        foreach ($listings as $listing) {
            if (array_key_exists($field, $listing) && ! $this->isEmpty($listing[$field])) {
                return $listing[$field];
            }
        }

        return null;
    }

    protected function syncerSource(): ?string
    {
        $source = __DIR__ . '/../Services/Buildify/BuildifyProjectSyncer.php';

        return is_readable($source) ? (string) file_get_contents($source) : null;
    }

    protected function isConsumed(string $syncerSource, string $field): bool
    {
        return str_contains($syncerSource, "'" . $field . "'");
    }

    protected function resolveOutputPath(): string
    {
        if ($out = $this->option('out')) {
            return (string) $out;
        }

        $directory = storage_path('app/buildify');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return $directory . DIRECTORY_SEPARATOR . sprintf('field-report-%s.xlsx', now()->format('Ymd-His'));
    }

    protected function describeType(mixed $value): string
    {
        return match (true) {
            is_array($value) => sprintf('array[%d]', count($value)),
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
            is_array($value) => $value === [] ? '[ ]' : implode(', ', array_map(
                fn ($item): string => is_scalar($item) ? (string) $item : (string) json_encode($item),
                $value
            )),
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

    protected function shortError(Throwable $e): string
    {
        $message = $e->getMessage();

        return $this->truncate(trim(explode("\n", $message)[0]), 160);
    }

    protected function columnLetter(int $index): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index);
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

        $sheet->getStyle('A1:' . $lastColumn . '1')->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER);
    }

    protected function configure(): void
    {
        $this->addOption('count', null, InputOption::VALUE_REQUIRED, 'Listings to sample per version.', 25);
        $this->addOption('versions', null, InputOption::VALUE_REQUIRED, 'Comma-separated API versions to probe.', 'v1,v1-lite');
        $this->addOption('out', null, InputOption::VALUE_REQUIRED, 'Write the .xlsx to this path.');
    }
}
