<?php

namespace Botble\RealEstate\Importers;

use Botble\ACL\Models\User;
use Botble\Base\Enums\BaseStatusEnum;
use Botble\Base\Events\CreatedContentEvent;
use Botble\Base\Facades\BaseHelper;
use Botble\DataSynchronize\Contracts\Importer\WithMapping;
use Botble\DataSynchronize\DataTransferObjects\ChunkImportResponse;
use Botble\DataSynchronize\DataTransferObjects\ChunkValidateResponse;
use Botble\DataSynchronize\Exporter\ExampleExporter;
use Botble\DataSynchronize\Importer\ImportColumn;
use Botble\DataSynchronize\Importer\Importer;
use Botble\Location\Models\City;
use Botble\Location\Models\Country;
use Botble\Location\Models\State;
use Botble\RealEstate\Enums\ProjectStatusEnum;
use Botble\RealEstate\Facades\RealEstateHelper;
use Botble\RealEstate\Models\Account;
use Botble\RealEstate\Models\Category;
use Botble\RealEstate\Models\Currency;
use Botble\RealEstate\Models\Facility;
use Botble\RealEstate\Models\Feature;
use Botble\RealEstate\Models\Investor;
use Botble\RealEstate\Models\Project;
use Botble\Slug\Facades\SlugHelper;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProjectImporter extends Importer implements WithMapping
{
    /**
     * Header alias map: maps snake_cased kash.xlsx headers to internal column names.
     * The base Importer calls headersToSnakeCase() on the Excel reader, so
     * "Number of Storeys" becomes "number_of_storeys", "Price / sq ft from" becomes
     * "price_/_sq_ft_from", etc. This map normalises those to DB field names.
     *
     * The standard Botble export format columns (e.g. "name", "price_from") pass
     * through unchanged because they already match the keys on the right side.
     */
    protected static array $headerAliases = [
        // Kash xlsx header (after snake_case) => internal column name
        'number_of_storeys'                    => 'number_floor',
        'total_number_of_suites'               => 'number_flat',
        'developers'                           => 'investor',
        'number_of_suites/floor'               => 'number_of_suites_per_floor',
        'number_of_suites_per_floor'           => 'number_of_suites_per_floor',
        'price_/_sq_ft_from'                   => 'price_per_sqft_from',
        'price_per_sqft_from'                  => 'price_per_sqft_from',
        'amenities'                            => 'features',
        'floor_plans'                          => 'floor_plans',
        'est._occupancy'                       => 'date_finish',
        'est_occupancy'                        => 'date_finish',
        'vip_launch'                           => 'date_sell',
        'total_min._deposit'                   => 'total_min_deposit',
        'total_min_deposit'                    => 'total_min_deposit',
        'est._maint'                           => 'est_maint',
        'est_maint'                            => 'est_maint',
        'est._property_tax'                    => 'est_property_tax',
        'est_property_tax'                     => 'est_property_tax',
        'is_featured?'                         => 'is_featured',
        'is_featured'                          => 'is_featured',
        'project_stage'                        => 'status',
        'price_from'                           => 'price_from',
        'price_to'                             => 'price_to',
        's.no.'                                => '_skip_',
        'sno'                                  => '_skip_',
        // Previously discarded — now carried so Excel projects match API ones.
        // "Type" becomes a browsable category; bed/bath become custom fields;
        // "Square" feeds the suite size column (same shape the API sync writes).
        'type'                                 => 'categories',
        'number_bedroom'                       => 'bedrooms',
        'number_bathroom'                      => 'bathrooms',
        'square'                               => 'suite_size_from',
        'author'                               => 'author_id',
        'auto_renew'                           => '_skip_',
        'project'                              => '_skip_',
        'expire_date'                          => '_skip_',
        'never_expired'                        => '_skip_',
        'period'                               => '_skip_',
        'moderation_status'                    => '_skip_',
        'price'                                => '_skip_',
    ];

    /**
     * Structured extras the Buildify API sync stores as named custom fields.
     * Excel mirrors them via dedicated columns so both import paths produce
     * projects of identical shape. Key = spreadsheet column (snake_case),
     * value = the exact custom-field name (must match BuildifyProjectSyncer).
     */
    protected static array $apiCustomFields = [
        'bedrooms'              => 'Bedrooms',
        'bathrooms'             => 'Bathrooms',
        'ceiling_heights'       => 'Ceiling Heights',
        'website'               => 'Website',
        'sales_status'          => 'Sales Status',
        'construction_status'   => 'Construction Status',
        'number_of_floor_plans' => 'Number of Floor Plans',
        'estimated_completion'  => 'Estimated Completion',
    ];

    /**
     * Canadian province abbreviation map for resolving short codes.
     */
    protected static array $stateAbbreviations = [
        'AB' => 'Alberta',
        'BC' => 'British Columbia',
        'MB' => 'Manitoba',
        'NB' => 'New Brunswick',
        'NL' => 'Newfoundland and Labrador',
        'NS' => 'Nova Scotia',
        'NT' => 'Northwest Territories',
        'NU' => 'Nunavut',
        'ON' => 'Ontario',
        'PE' => 'Prince Edward Island',
        'QC' => 'Quebec',
        'SK' => 'Saskatchewan',
        'YT' => 'Yukon',
    ];

    /**
     * Project stage mapping from kash.xlsx values to ProjectStatusEnum values.
     */
    protected static array $stageToStatus = [
        'planning'       => 'selling',
        'pre-con'        => 'pre_sale',
        'pre_con'        => 'pre_sale',
        'preconstruction'=> 'pre_sale',
        'construction'   => 'building',
        'complete'       => 'sold',
        'completed'      => 'sold',
        'selling'        => 'selling',
        'sold'           => 'sold',
        'building'       => 'building',
        'not_available'  => 'not_available',
    ];

    public function getLabel(): string
    {
        return trans('plugins/real-estate::project.projects');
    }

    public function columns(): array
    {
        // Validation rules are kept minimal ('nullable' only) because raw Excel
        // data arrives as mixed types: numbers where we need strings, comma-separated
        // text where we need arrays, "Yes"/"No" where we need booleans, etc.
        // All actual type conversion and data cleaning is handled in map().
        $columns = [
            ImportColumn::make('name')
                ->rules(['required', 'max:255']),
            ImportColumn::make('description')
                ->nullable()
                ->rules(['nullable']),
            ImportColumn::make('content')
                ->nullable()
                ->rules(['nullable']),
            ImportColumn::make('images')
                ->nullable()
                ->rules(['nullable']),
            ImportColumn::make('location')
                ->nullable()
                ->rules(['nullable']),
            ImportColumn::make('investor_id')
                ->nullable()
                ->rules(['nullable']),
            ImportColumn::make('number_block')
                ->nullable()
                ->rules(['nullable']),
            ImportColumn::make('number_floor')
                ->nullable()
                ->rules(['nullable']),
            ImportColumn::make('number_flat')
                ->nullable()
                ->rules(['nullable']),
            ImportColumn::make('is_featured')
                ->nullable()
                ->rules(['nullable']),
            ImportColumn::make('date_finish')
                ->nullable()
                ->rules(['nullable']),
            ImportColumn::make('date_sell')
                ->nullable()
                ->rules(['nullable']),
            ImportColumn::make('price_from')
                ->nullable()
                ->rules(['nullable']),
            ImportColumn::make('price_to')
                ->nullable()
                ->rules(['nullable']),
            ImportColumn::make('currency')
                ->nullable()
                ->rules(['nullable']),
            ImportColumn::make('city')
                ->nullable()
                ->rules(['nullable']),
            ImportColumn::make('country')
                ->nullable()
                ->rules(['nullable']),
            ImportColumn::make('state')
                ->nullable()
                ->rules(['nullable']),
            ImportColumn::make('author_id')
                ->nullable()
                ->rules(['nullable']),
            ImportColumn::make('author_type')
                ->nullable()
                ->rules(['nullable']),
            ImportColumn::make('longitude')
                ->nullable()
                ->rules(['nullable']),
            ImportColumn::make('latitude')
                ->nullable()
                ->rules(['nullable']),
        ];

        if (RealEstateHelper::isEnabledZipCode()) {
            $columns[] = ImportColumn::make('zip_code')
                ->nullable()
                ->rules(['nullable']);
        }

        return [
            ...$columns,
            ImportColumn::make('status')->nullable()->rules(['nullable']),
            ImportColumn::make('categories')->nullable()->rules(['nullable']),
            ImportColumn::make('features')->nullable()->rules(['nullable']),
            ImportColumn::make('facilities')->nullable()->rules(['nullable']),
            ImportColumn::make('custom_fields')->nullable()->rules(['nullable']),
            ImportColumn::make('unique_id')->nullable()->rules(['nullable']),
            ImportColumn::make('video_url')->nullable()->rules(['nullable']),
            ImportColumn::make('video_thumbnail')->nullable()->rules(['nullable']),
            ImportColumn::make('private_notes')->nullable()->rules(['nullable']),
            ImportColumn::make('suites_starting_floor')->nullable()->rules(['nullable']),
            ImportColumn::make('number_of_suites_per_floor')->nullable()->rules(['nullable']),
            ImportColumn::make('suite_size_from')->nullable()->rules(['nullable']),
            ImportColumn::make('suite_size_to')->nullable()->rules(['nullable']),
            ImportColumn::make('price_per_sqft_from')->nullable()->rules(['nullable']),
            ImportColumn::make('parking_price')->nullable()->rules(['nullable']),
            ImportColumn::make('locker_price')->nullable()->rules(['nullable']),
            ImportColumn::make('total_min_deposit')->nullable()->rules(['nullable']),
            ImportColumn::make('deposit_notes')->nullable()->rules(['nullable']),
            ImportColumn::make('development_levies')->nullable()->rules(['nullable']),
            ImportColumn::make('assignment_policy')->nullable()->rules(['nullable']),
            ImportColumn::make('est_maint')->nullable()->rules(['nullable']),
            ImportColumn::make('locker_maint')->nullable()->rules(['nullable']),
            ImportColumn::make('parking_maint')->nullable()->rules(['nullable']),
            ImportColumn::make('est_property_tax')->nullable()->rules(['nullable']),
            ImportColumn::make('maintenance_notes')->nullable()->rules(['nullable']),
            ImportColumn::make('neighbour')->nullable()->rules(['nullable']),
            ImportColumn::make('intersection')->nullable()->rules(['nullable']),
            ImportColumn::make('architects')->nullable()->rules(['nullable']),
            // Kash-specific columns accepted but handled in map()
            ImportColumn::make('investor')->nullable()->rules(['nullable']),
            ImportColumn::make('floor_plans')->nullable()->rules(['nullable']),
            // Dedicated custom-field columns — mirror the named custom fields the
            // Buildify API sync creates, so Excel imports carry the same extras.
            ...array_map(
                fn (string $key) => ImportColumn::make($key)->nullable()->rules(['nullable']),
                array_keys(self::$apiCustomFields)
            ),
        ];
    }

    public function getModel(): string
    {
        return Project::class;
    }

    public function chunkSize(): int
    {
        return 50;
    }

    /**
     * Allow extra columns from kash.xlsx that are not defined in columns()
     * to be passed through to transformRows so they can be remapped.
     */
    public function mergeWithUndefinedColumns(): bool
    {
        return true;
    }

    public function getValidateUrl(): string
    {
        return route('tools.data-synchronize.import.projects.validate');
    }

    public function getImportUrl(): string
    {
        return route('tools.data-synchronize.import.projects.store');
    }

    public function getDownloadExampleUrl(): ?string
    {
        return route('tools.data-synchronize.import.projects.download-example');
    }

    public function getExportUrl(): ?string
    {
        return Auth::user()->hasPermission('project.export')
            ? route('tools.data-synchronize.export.projects.store')
            : null;
    }

    /**
     * Override transformRows to apply header alias mapping before the standard
     * column matching runs. This lets us accept both the standard Botble export
     * format AND the Kash xlsx format from a single importer.
     *
     * NOTE: we intentionally do NOT call parent::transformRows(). The parent
     * captures $row by value inside its column-pull closure, so the "leftover"
     * row it spreads after map() still contains EVERY original column — which
     * silently overwrites all values computed in map() (dates, features,
     * images, status, ...). This implementation pulls defined columns out of
     * the row properly before merging real leftovers.
     */
    public function transformRows(array $rows): array
    {
        $columns = $this->getColumns();

        return array_map(function ($row) use ($columns) {
            // Remap kash.xlsx headers to internal column names
            $remapped = [];
            foreach ($row as $key => $value) {
                $normalised = strtolower(trim((string) $key));
                $target = self::$headerAliases[$normalised] ?? $key;

                // Skip columns explicitly marked for exclusion
                if ($target === '_skip_') {
                    continue;
                }

                // If the target already exists in the remapped array (e.g.
                // the standard column was already set), prefer non-empty values
                if (array_key_exists($target, $remapped) && ! empty($remapped[$target])) {
                    continue;
                }

                $remapped[$target] = $value;
            }

            // Pull defined columns out of the row (dot-safe: plain unset, not Arr::pull)
            $formatted = [];
            foreach ($columns as $column) {
                $heading = $column->getHeading();
                $value = array_key_exists($heading, $remapped) ? $remapped[$heading] : null;
                unset($remapped[$heading]);

                $formatted[$column->getName()] = match (true) {
                    $column->isNullable() && empty($value) => null,
                    $column->isBoolean() && is_string($value) => $value === $column->getTrueValue() ? 1 : 0,
                    default => $value,
                };
            }

            // Real leftovers (undefined columns) first, mapped values win
            return [
                ...($this->mergeWithUndefinedColumns() ? $remapped : []),
                ...$this->map($formatted),
            ];
        }, $rows);
    }

    /**
     * Windows-safe override of the vendor validate().
     *
     * The vendor renames the uploaded file to a new random name on the final
     * (empty) chunk and tells the frontend to import THAT name. On Windows the
     * Excel reader keeps the file handle open, so the rename silently fails
     * (the 'local' disk is configured with 'throw' => false) and the import
     * phase then dies with "File not found". We keep the original filename for
     * the whole run so both phases read the same, existing file. Cleanup is
     * handled best-effort in import().
     */
    public function validate(string $fileName, int $offset = 0, int $limit = 100): ChunkValidateResponse
    {
        $rows = $this->transformRows($this->getRowsByOffset($fileName, $offset, $limit));

        $total = request()->integer('total') ?: $this->getRows($fileName)->count();

        $validator = Validator::make($rows, $this->getValidationRules());

        $errors = [];
        if ($validator->fails()) {
            $errors = array_map(fn ($error) => $error[0], $validator->errors()->toArray());
        }

        return new ChunkValidateResponse(
            offset: $offset,
            count: count($rows),
            total: $total,
            fileName: $fileName,
            errors: array_values($errors),
        );
    }

    /**
     * Windows-safe override of the vendor import(). Identical logic, except the
     * temp-file cleanup on the final chunk is best-effort: we release any open
     * reader handle first and swallow delete failures so a locked temp file
     * can never abort a successful import.
     */
    public function import(string $fileName, int $offset = 0, int $limit = 100): ChunkImportResponse
    {
        $rows = $this->getRowsByOffset($fileName, $offset, $limit);
        $count = count($rows);
        $rows = $this->transformRows($rows);

        $failureRows = $this->failures()->map(fn ($failure) => $failure['row'])->all();
        $rowNumber = 0;
        $rows = array_filter($rows, function () use (&$rowNumber, $failureRows) {
            $rowNumber++;

            return ! in_array($rowNumber, $failureRows);
        });

        $imported = $this->handle($rows);

        if ($count === 0) {
            // Let PHP release the OpenSpout file handle before deleting
            gc_collect_cycles();

            $storageFolder = config('packages.data-synchronize.data-synchronize.storage.path');
            $path = "$storageFolder/$fileName";

            try {
                if ($this->filesystem()->exists($path)) {
                    $this->filesystem()->delete($path);
                }
            } catch (\Throwable) {
                // Temp file is locked; harmless — it will be overwritten later
            }
        }

        return new ChunkImportResponse(
            offset: $offset,
            count: $count,
            imported: $imported,
            failures: $this->failures()->all()
        );
    }

    /**
     * Hide the large "examples" table on the import page — it just clutters the
     * screen. Returning an empty set makes the vendor view skip that section
     * entirely. The "Download sample" buttons still work because
     * downloadExample() below reads from examples() directly.
     */
    public function getExamples(): array
    {
        return [];
    }

    /**
     * Keep the downloadable CSV/Excel sample populated with real rows even
     * though getExamples() is emptied to hide the on-page table.
     */
    public function downloadExample(string $format): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $columns = $this->getColumns();

        return (new ExampleExporter($this->examples(), $columns, $this->getLabel()))
            ->format($format)
            ->acceptedColumns(array_map(fn (ImportColumn $column) => $column->getName(), $columns))
            ->export();
    }

    /**
     * Don't render the default full-width rules table; we surface the column
     * rules in a modal instead (see the custom import view / getView()).
     */
    public function showRulesCheatSheet(): bool
    {
        return false;
    }

    /**
     * Use a custom import view that keeps the standard upload form but shows the
     * column rules in a modal rather than a big table.
     */
    protected function getView(): string
    {
        if ($this->renderWithoutLayout) {
            return 'packages/data-synchronize::partials.importer';
        }

        return 'plugins/real-estate::import.projects-import';
    }

    public function examples(): array
    {
        $projects = Project::query()
            // Prefer API-sourced projects for the sample: they populate every
            // column (incl. the custom-field extras), so the template shows a
            // fully filled-in example rather than sparse manual rows.
            ->orderByRaw("CASE WHEN source = 'buildify' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->take(3)
            ->with(['investor', 'categories', 'features', 'facilities', 'customFields', 'slugable'])
            ->get()
            ->map(function (Project $project) { // @phpstan-ignore-line
                // Custom fields keyed by name so we can split the API-mirrored
                // extras into their own sample columns.
                $cfByName = $project->customFields->keyBy('name');

                $data = [
                    'name' => $project->name,
                    'description' => Str::limit($project->description),
                    'content' => Str::limit($project->content, 200),
                    'images' => is_array($project->images) ? implode(',', $project->images) : '',
                    'location' => $project->location,
                    'investor' => $project->investor?->name ?: $project->investor_id,
                    'number_block' => $project->number_block,
                    'number_floor' => $project->number_floor,
                    'number_flat' => $project->number_flat,
                    'is_featured' => $project->is_featured ? 'Yes' : 'No',
                    'date_finish' => $project->date_finish?->format('Y-m-d'),
                    'date_sell' => $project->date_sell?->format('Y-m-d'),
                    'price_from' => $project->price_from,
                    'price_to' => $project->price_to,
                    'currency' => $project->currency?->title ?: cms_currency()->getDefaultCurrency()->title,
                    'country' => $project->country_name,
                    'state' => $project->state_name,
                    'city' => $project->city_name,
                    'author_id' => $project->author_id,
                    'author_type' => $project->author_type,
                    'longitude' => $project->longitude,
                    'latitude' => $project->latitude,
                ];

                if (RealEstateHelper::isEnabledZipCode()) {
                    $data['zip_code'] = $project->zip_code;
                }

                return [
                    ...$data,
                    'status' => $project->status,
                    'categories' => $project->categories->pluck('name')->implode(', '),
                    'features' => $project->features->pluck('name')->implode(', '),
                    'facilities' => $project->facilities->map(function ($facility) {
                        return $facility->name . ':' . $facility->pivot->distance;
                    })->implode(', '),
                    // Split the API-mirrored extras into their own columns...
                    ...collect(self::$apiCustomFields)
                        ->mapWithKeys(fn (string $label, string $key) => [
                            $key => optional($cfByName->get($label))->value,
                        ])->all(),
                    // ...and keep only the remaining ad-hoc ones in the catch-all.
                    'custom_fields' => $project->customFields
                        ->reject(fn ($field) => in_array($field->name, self::$apiCustomFields, true))
                        ->map(fn ($field) => $field->name . ':' . $field->value)
                        ->implode(', '),
                    'unique_id' => $project->unique_id ?: null,
                    'video_url' => $project->getMetaData('video_url', true),
                    'video_thumbnail' => $project->getMetaData('video_thumbnail', true),
                ];
            });

        if ($projects->isNotEmpty()) {
            return $projects->all();
        }

        $examples = [
            [
                'name' => 'Sunset Heights Residential Complex',
                'description' => 'Modern residential complex with luxury amenities and stunning city views.',
                'content' => 'Sunset Heights offers contemporary living with state-of-the-art facilities, including a fitness center, swimming pool, and landscaped gardens.',
                'images' => 'https://via.placeholder.com/800x600,https://via.placeholder.com/800x600',
                'location' => '789 Sunset Boulevard, Beverly Hills, CA',
                'investor' => 'Premium Developments Inc.',
                'number_block' => 3,
                'number_floor' => 15,
                'number_flat' => 120,
                'is_featured' => 'Yes',
                'date_finish' => '2024-12-31',
                'date_sell' => '2024-06-01',
                'price_from' => 500000,
                'price_to' => 1500000,
                'currency' => 'USD',
                'country' => 'United States',
                'state' => 'California',
                'city' => 'Beverly Hills',
                'author_id' => 1,
                'author_type' => User::class,
                'longitude' => -118.4004,
                'latitude' => 34.0736,
                'status' => BaseStatusEnum::PUBLISHED,
                'categories' => 'Residential, Luxury',
                'features' => 'Swimming Pool, Gym, Parking',
                'facilities' => 'Hospital:1.5km, School:0.8km, Shopping Center:2km',
                'custom_fields' => 'Total Units:120, Completion:95%',
                'unique_id' => 'PROJ-001',
                'video_url' => 'https://www.youtube.com/watch?v=example',
                'video_thumbnail' => 'https://via.placeholder.com/400x300',
                'private_notes' => 'Some internal notes here',
                'suites_starting_floor' => 2,
                'number_of_suites_per_floor' => 10,
                'suite_size_from' => 500,
                'suite_size_to' => 1200,
                'price_per_sqft_from' => 1000,
                'parking_price' => 50000,
                'locker_price' => 5000,
                'total_min_deposit' => '20%',
                'deposit_notes' => '5% on signing, 5% in 30 days',
                'development_levies' => '$10,000 capped',
                'assignment_policy' => 'Free assignment',
                'est_maint' => '$0.60/sqft',
                'locker_maint' => '$20/month',
                'parking_maint' => '$50/month',
                'est_property_tax' => '1% of purchase price',
                'maintenance_notes' => 'Hydro metered separately',
                'neighbour' => 'Downtown',
                'intersection' => 'Main & 1st',
                'architects' => 'ABC Architects',
                // API-mirrored custom-field columns
                'bedrooms' => '1 - 3',
                'bathrooms' => '1 - 2',
                'ceiling_heights' => '9 ft',
                'website' => 'https://sunset-heights.example.com',
                'sales_status' => 'Selling',
                'construction_status' => 'Under Construction',
                'number_of_floor_plans' => 12,
                'estimated_completion' => 'Q4 2025',
            ],
            [
                'name' => 'Green Valley Commercial Center',
                'description' => 'Mixed-use development combining retail, office, and residential spaces.',
                'content' => 'Green Valley is a sustainable development featuring eco-friendly design, energy-efficient systems, and green spaces throughout.',
                'images' => 'https://via.placeholder.com/800x600',
                'location' => '456 Green Valley Road, Austin, TX',
                'investor' => 'EcoBuilders LLC',
                'number_block' => 2,
                'number_floor' => 8,
                'number_flat' => 80,
                'is_featured' => 'No',
                'date_finish' => '2025-06-30',
                'date_sell' => '2024-09-01',
                'price_from' => 300000,
                'price_to' => 800000,
                'currency' => 'USD',
                'country' => 'United States',
                'state' => 'Texas',
                'city' => 'Austin',
                'author_id' => 1,
                'author_type' => User::class,
                'longitude' => -97.7431,
                'latitude' => 30.2672,
                'status' => BaseStatusEnum::PUBLISHED,
                'categories' => 'Commercial, Mixed-Use',
                'features' => 'Solar Panels, Green Roof, Electric Car Charging',
                'facilities' => 'Bus Stop:0.3km, University:2.5km, Park:0.5km',
                'custom_fields' => 'LEED Certified:Gold, Parking Spaces:200',
                'unique_id' => 'PROJ-002',
                'video_url' => '',
                'video_thumbnail' => '',
                'private_notes' => '',
                'suites_starting_floor' => null,
                'number_of_suites_per_floor' => null,
                'suite_size_from' => null,
                'suite_size_to' => null,
                'price_per_sqft_from' => null,
                'parking_price' => null,
                'locker_price' => null,
                'total_min_deposit' => '',
                'deposit_notes' => '',
                'development_levies' => '',
                'assignment_policy' => '',
                'est_maint' => '',
                'locker_maint' => '',
                'parking_maint' => '',
                'est_property_tax' => '',
                'maintenance_notes' => '',
                'neighbour' => '',
                'intersection' => '',
                'architects' => '',
                // API-mirrored custom-field columns
                'bedrooms' => 'Studio - 2',
                'bathrooms' => '1',
                'ceiling_heights' => '',
                'website' => 'https://green-valley.example.com',
                'sales_status' => 'Registration',
                'construction_status' => 'Pre-Construction',
                'number_of_floor_plans' => 6,
                'estimated_completion' => '2026',
            ],
        ];

        if (RealEstateHelper::isEnabledZipCode()) {
            $examples[0]['zip_code'] = '90210';
            $examples[1]['zip_code'] = '78701';
        }

        return $examples;
    }

    public function map(mixed $row): array
    {
        $categories = [];
        $features = [];
        $facilities = [];
        $customFields = [];

        if ($categoriesStr = Arr::get($row, 'categories')) {
            $categories = $this->parseIdsFromString($categoriesStr, Category::class);
        }

        // Support both standard "features" and kash.xlsx "amenities" (remapped to features)
        if ($featuresStr = Arr::get($row, 'features')) {
            $features = $this->parseIdsFromString($featuresStr, Feature::class);
        }

        if ($facilitiesStr = Arr::get($row, 'facilities')) {
            $facilitiesArray = explode(',', $facilitiesStr);
            foreach ($facilitiesArray as $facility) {
                $facilityExplode = explode(':', trim($facility));
                if (count($facilityExplode) === 2) {
                    $facilityName = Str::limit(trim($facilityExplode[0]), 120, '');
                    $distance = Str::limit(trim($facilityExplode[1]), 120, '');
                    $facilityModel = Facility::query()->firstOrCreate(['name' => $facilityName]);
                    if ($facilityModel) {
                        $facilities[$facilityModel->id] = $distance;
                    }
                }
            }
        }

        if ($customFieldsStr = Arr::get($row, 'custom_fields')) {
            $fieldsArray = explode(',', $customFieldsStr);
            foreach ($fieldsArray as $field) {
                $fieldExplode = explode(':', trim($field));
                if (count($fieldExplode) === 2) {
                    $customFields[] = [
                        'name' => Str::limit(trim($fieldExplode[0]), 120, ''),
                        'value' => Str::limit(trim($fieldExplode[1]), 120, ''),
                    ];
                }
            }
        }

        // kash.xlsx "Floor Plans" holds a plain count, not repeater data. The
        // floor_plans DB column is an array cast, so keep the count as a custom
        // field instead and drop it from the row.
        $floorPlans = Arr::get($row, 'floor_plans');
        if ($floorPlans !== null && $floorPlans !== '' && ! is_array($floorPlans)) {
            $customFields[] = [
                'name' => 'Floor Plans',
                'value' => Str::limit(trim((string) $floorPlans), 120, ''),
            ];
        }
        unset($row['floor_plans']);

        // Dedicated custom-field columns mirror the named custom fields created
        // by the Buildify API sync (Bedrooms, Bathrooms, Website, ...), so an
        // Excel import produces the same structured extras. Pull each into a
        // named custom field and drop it from the row so forceFill ignores it.
        foreach (self::$apiCustomFields as $key => $label) {
            $value = Arr::get($row, $key);
            if ($value !== null && trim((string) $value) !== '') {
                $customFields[] = [
                    'name' => $label,
                    'value' => Str::limit(trim((string) $value), 120, ''),
                ];
            }
            unset($row[$key]);
        }

        // De-duplicate by name so a value supplied in both a dedicated column
        // and the generic "custom_fields" catch-all is stored only once
        // (dedicated columns are appended last, so they win).
        if ($customFields) {
            $deduped = [];
            foreach ($customFields as $field) {
                $deduped[$field['name']] = $field;
            }
            $customFields = array_values($deduped);
        }

        // Resolve investor: accept "investor" key (standard) or "developers" (kash, remapped)
        $investorId = null;
        $investorNameRaw = Arr::get($row, 'investor') ?: Arr::get($row, 'investor_id');

        // kash.xlsx separates multiple developers with "||" — the project only
        // supports a single investor, so use the first one listed
        if (is_string($investorNameRaw) && str_contains($investorNameRaw, '||')) {
            $investorNameRaw = Arr::first(array_filter(array_map('trim', explode('||', $investorNameRaw))));
        }

        $investorName = is_string($investorNameRaw) ? Str::limit(trim($investorNameRaw), 120, '') : $investorNameRaw;
        
        if ($investorName && ! is_numeric($investorName)) {
            $investor = Investor::query()->where('name', $investorName)->first();
            if (! $investor) {
                // Auto-create investor from developer name if it doesn't exist
                $investor = Investor::query()->create(['name' => $investorName]);
                if (SlugHelper::isSupportedModel(Investor::class) && $investor->wasRecentlyCreated) {
                    SlugHelper::createSlug($investor);
                }
            }
            $investorId = $investor->id;
        } elseif ($investorName && is_numeric($investorName)) {
            $investorId = (int) $investorName;
        }

        $countryId = null;
        $stateId = null;
        $cityId = null;

        if (is_plugin_active('location')) {
            if ($countryName = Arr::get($row, 'country')) {
                $countryName = Str::limit(trim((string) $countryName), 120, '');
                $country = Country::query()->firstOrCreate(['name' => $countryName]);
                if (SlugHelper::isSupportedModel(Country::class) && $country->wasRecentlyCreated) {
                    SlugHelper::createSlug($country);
                }
                $countryId = $country->id;
            }

            if ($stateName = Arr::get($row, 'state')) {
                $stateName = trim((string) $stateName);
                // Resolve state abbreviation (e.g. "ON" -> "Ontario")
                $fullStateName = self::$stateAbbreviations[strtoupper($stateName)] ?? $stateName;
                $state = State::query()->where('name', $fullStateName)->first();
                // Fallback: try the abbreviation as-is
                if (! $state) {
                    $state = State::query()->where('abbreviation', strtoupper($stateName))->first();
                }
                if (! $state) {
                    $state = State::query()->where('name', $stateName)->first();
                }
                if (! $state) {
                    // Auto-create missing state so location data isn't silently dropped
                    $state = State::query()->create([
                        'name' => Str::limit($fullStateName, 120, ''),
                        'abbreviation' => strlen($stateName) <= 3 ? strtoupper($stateName) : null,
                        'country_id' => $countryId,
                    ]);
                    if (SlugHelper::isSupportedModel(State::class)) {
                        SlugHelper::createSlug($state);
                    }
                }
                $stateId = $state->id;
            }

            if ($cityName = Arr::get($row, 'city')) {
                $cityName = Str::limit(trim((string) $cityName), 120, '');
                $city = City::query()->firstOrCreate(
                    ['name' => $cityName],
                    ['state_id' => $stateId, 'country_id' => $countryId]
                );
                if (SlugHelper::isSupportedModel(City::class) && $city->wasRecentlyCreated) {
                    SlugHelper::createSlug($city);
                }
                $cityId = $city->id;
            }
        }

        $currencyId = null;

        if ($currencyName = Arr::get($row, 'currency')) {
            $currency = Currency::query()->where('title', strtoupper($currencyName))->first();
            if ($currency) {
                $currencyId = $currency->id;
            }
        }

        $images = [];
        if ($imagesStr = Arr::get($row, 'images')) {
            $images = array_filter(explode(',', $imagesStr));
        }

        $authorType = Arr::get($row, 'author_type');
        if ($authorType) {
            if (strtolower($authorType) === 'user') {
                $authorType = User::class;
            } elseif (strtolower($authorType) === 'account') {
                $authorType = Account::class;
            } elseif (! class_exists($authorType)) {
                $authorType = User::class;
            }
        }

        // Resolve status from kash.xlsx "Project Stage" values
        $status = Arr::get($row, 'status');
        if ($status) {
            $normalised = strtolower(trim(str_replace(['-', ' '], '_', $status)));
            $status = self::$stageToStatus[$normalised] ?? $status;
        }
        if (! $status || ! in_array($status, ProjectStatusEnum::values())) {
            $status = ProjectStatusEnum::SELLING;
        }

        unset($row['country']);
        unset($row['state']);
        unset($row['city']);
        unset($row['currency']);
        unset($row['investor']);

        return [
            ...$row,
            'status' => $status,
            'categories' => $categories,
            'features' => $features,
            'facilities' => $facilities,
            'custom_fields' => $customFields,
            'investor_id' => $investorId,
            'country_id' => $countryId,
            'state_id' => $stateId,
            'city_id' => $cityId,
            'currency_id' => $currencyId,
            'images' => $images,
            'is_featured' => $this->convertToBoolean(Arr::get($row, 'is_featured')),
            'author_type' => $authorType,
            // Normalise dates — kash.xlsx delivers "Q2 2025", "Summer 2018",
            // "May, 2017", Excel serials, and native DateTime objects
            'date_finish' => $this->normalizeDate(Arr::get($row, 'date_finish')),
            'date_sell' => $this->normalizeDate(Arr::get($row, 'date_sell')),
            // Guard varchar column limits
            'description' => $this->limitString(Arr::get($row, 'description'), 400),
            'location' => $this->limitString(Arr::get($row, 'location'), 255),
            // Cast all text fields to string — Excel may deliver them as numbers
            'price_from' => $this->castToString(Arr::get($row, 'price_from')),
            'price_to' => $this->castToString(Arr::get($row, 'price_to')),
            'private_notes' => $this->castToString(Arr::get($row, 'private_notes')),
            // Numeric columns: junk like "n/a" or "TBA" becomes null instead of 0
            'number_block' => $this->castToNumber(Arr::get($row, 'number_block')),
            'number_floor' => $this->castToNumber(Arr::get($row, 'number_floor')),
            'number_flat' => $this->castToNumber(Arr::get($row, 'number_flat')),
            'suites_starting_floor' => $this->castToNumber(Arr::get($row, 'suites_starting_floor')),
            'number_of_suites_per_floor' => $this->castToNumber(Arr::get($row, 'number_of_suites_per_floor')),
            'suite_size_from' => $this->castToNumber(Arr::get($row, 'suite_size_from')),
            'suite_size_to' => $this->castToNumber(Arr::get($row, 'suite_size_to')),
            'price_per_sqft_from' => $this->castToNumber(Arr::get($row, 'price_per_sqft_from')),
            'parking_price' => $this->castToNumber(Arr::get($row, 'parking_price')),
            'locker_price' => $this->castToNumber(Arr::get($row, 'locker_price')),
            'total_min_deposit' => $this->castToString(Arr::get($row, 'total_min_deposit')),
            'deposit_notes' => $this->castToString(Arr::get($row, 'deposit_notes')),
            'development_levies' => $this->limitString(Arr::get($row, 'development_levies'), 255),
            'assignment_policy' => $this->limitString(Arr::get($row, 'assignment_policy'), 255),
            'est_maint' => $this->limitString(Arr::get($row, 'est_maint'), 255),
            'locker_maint' => $this->limitString(Arr::get($row, 'locker_maint'), 255),
            'parking_maint' => $this->limitString(Arr::get($row, 'parking_maint'), 255),
            'est_property_tax' => $this->limitString(Arr::get($row, 'est_property_tax'), 255),
            'maintenance_notes' => $this->castToString(Arr::get($row, 'maintenance_notes')),
            'neighbour' => $this->limitString(Arr::get($row, 'neighbour'), 255),
            'intersection' => $this->limitString(Arr::get($row, 'intersection'), 255),
            'architects' => $this->limitString(Arr::get($row, 'architects'), 255),
        ];
    }

    public function handle(array $data): int
    {
        $count = 0;

        foreach ($data as $row) {
            // Skip rows without a name
            if (empty(Arr::get($row, 'name'))) {
                continue;
            }

            // Skip rows without any price (per user directive)
            $priceFrom = Arr::get($row, 'price_from');
            $priceTo = Arr::get($row, 'price_to');
            if (empty($priceFrom) && empty($priceTo)) {
                continue;
            }

            $uniqueId = Arr::get($row, 'unique_id');
            $project = null;

            if ($uniqueId) {
                $project = Project::query()->where('unique_id', $uniqueId)->first();
            }

            if (! $project) {
                $project = Project::query()->where('name', Arr::get($row, 'name'))->first();
            }

            // If the project already exists (matched by unique_id or name), leave
            // it completely untouched. Re-importing must never overwrite existing
            // projects — only brand-new ones are created.
            if ($project) {
                continue;
            }

            $projectData = Arr::except($row, ['categories', 'features', 'facilities', 'custom_fields', 'video_url', 'video_thumbnail']);

            // Clean out any keys that aren't real fillable columns
            $fillable = (new Project())->getFillable();
            $extraKeys = ['country_id', 'state_id', 'city_id', 'currency_id', 'author_id', 'author_type', 'investor_id', 'status', 'is_featured', 'unique_id', 'views'];
            $allowedKeys = array_merge($fillable, $extraKeys);
            $projectData = Arr::only($projectData, $allowedKeys);

            $projectData['unique_id'] = $uniqueId ?: Str::slug(Arr::get($row, 'name')) . '-' . Str::lower(Str::random(5));
            $projectData['source'] = 'excel';

            if (! $project) {
                $project = new Project();
                $count++;
            }

            if (! Arr::get($projectData, 'author_id')) {
                $projectData['author_id'] = Auth::id();
                $projectData['author_type'] = User::class;
            } else {
                if (! Arr::get($projectData, 'author_type') || ! class_exists(Arr::get($projectData, 'author_type'))) {
                    $projectData['author_type'] = User::class;
                }
            }

            // Make sure the referenced author actually exists — kash.xlsx hardcodes
            // author ids that may not be present in this database
            if (Arr::get($projectData, 'author_type') === User::class && Arr::get($projectData, 'author_id')) {
                static $userExists = [];
                $authorId = $projectData['author_id'];
                $userExists[$authorId] ??= User::query()->whereKey($authorId)->exists();
                if (! $userExists[$authorId]) {
                    $projectData['author_id'] = Auth::id() ?: User::query()->orderBy('id')->value('id');
                }
            }

            $project->forceFill($projectData);

            /**
             * @var Project $project
             */
            $project->save();

            // Capture before later relation saves so we know whether this row
            // created a new project or updated an existing one. Used to avoid
            // generating duplicate slugs on re-import (see is_slug_editable below).
            $isNewProject = $project->wasRecentlyCreated;

            if ($categories = Arr::get($row, 'categories')) {
                $project->categories()->sync($categories);
            }

            if ($features = Arr::get($row, 'features')) {
                $project->features()->sync($features);
            }

            if ($facilities = Arr::get($row, 'facilities')) {
                $project->facilities()->detach();
                foreach ($facilities as $facilityId => $distance) {
                    $project->facilities()->attach($facilityId, ['distance' => $distance]);
                }
            }

            if ($customFields = Arr::get($row, 'custom_fields')) {
                $project->customFields()->delete();
                foreach ($customFields as $field) {
                    $project->customFields()->create([
                        'name' => $field['name'],
                        'value' => $field['value'],
                    ]);
                }
            }

            $project->metadata()->whereIn('meta_key', ['video_url', 'video_thumbnail'])->delete();

            if ($videoUrl = Arr::get($row, 'video_url')) {
                $project->metadata()->create([
                    'meta_key' => 'video_url',
                    'meta_value' => $videoUrl,
                ]);
            }

            if ($videoThumbnail = Arr::get($row, 'video_thumbnail')) {
                $project->metadata()->create([
                    'meta_key' => 'video_thumbnail',
                    'meta_value' => $videoThumbnail,
                ]);
            }

            $request = new Request();
            $request->merge([
                ...$row,
                'slug' => Str::slug($project->name),
                // Only let the slug listener create a slug for brand-new projects.
                // The CreatedContentListener always INSERTs a new slug row with no
                // dedup check, so firing it for existing projects on every re-import
                // would pile up duplicate slugs (and shadow the real one -> 404s).
                'is_slug_editable' => $isNewProject,
            ]);

            event(new CreatedContentEvent(PROJECT_MODULE_SCREEN_NAME, $request, $project));

            if (class_exists(\Botble\LanguageAdvanced\Supports\LanguageAdvancedManager::class)) {
                \Botble\LanguageAdvanced\Supports\LanguageAdvancedManager::save($project, $request);
            }
        }

        return $count;
    }

    protected function parseIdsFromString(string $items, string $modelClass): array
    {
        return str($items)
            ->explode(',')
            ->map(fn ($item) => trim($item))
            ->filter(fn ($item) => ! empty($item))
            ->map(function ($item) use ($modelClass) {
                // Truncate to 120 chars to avoid DB column overflow
                $name = Str::limit($item, 120, '');
                $model = $modelClass::query()->firstOrCreate(['name' => $name]);

                if (SlugHelper::isSupportedModel($modelClass) && $model->wasRecentlyCreated) {
                    SlugHelper::createSlug($model);
                }

                return $model->getKey();
            })
            ->all();
    }

    protected function convertToBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (empty($value)) {
            return false;
        }

        return in_array(strtolower((string) $value), ['yes', '1', 'true', 'on']);
    }

    /**
     * Safely cast any value to a string or null.
     * Excel often delivers numeric cells as int/float even when the DB column is varchar.
     */
    protected function castToString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    /**
     * Cast to string and hard-truncate to fit a varchar column.
     */
    protected function limitString(mixed $value, int $max): ?string
    {
        $value = $this->castToString($value);

        return $value === null ? null : Str::limit($value, $max, '');
    }

    /**
     * Cast to a number for int/decimal columns, or null when the cell holds
     * junk like "n/a" or "TBA". Strips thousand separators and currency signs.
     */
    protected function castToNumber(mixed $value): int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        $clean = str_replace([',', '$', ' '], '', trim((string) $value));

        if (! is_numeric($clean)) {
            return null;
        }

        return str_contains($clean, '.') ? (float) $clean : (int) $clean;
    }

    /**
     * Normalise the many date formats found in import files to Y-m-d (or null).
     *
     * Handles: native DateTime objects (OpenSpout returns these for date-formatted
     * cells), Excel serial numbers, quarter strings ("Q2 2025"), season strings
     * ("Summer 2018"), and anything Carbon can parse ("May, 2017", "2024-06-01").
     */
    protected function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_numeric($value)) {
            $serial = (float) $value;

            // Excel serial date range ~1955-2119; anything else is not a date
            if ($serial > 20000 && $serial < 80000) {
                return Carbon::create(1899, 12, 30)->addDays((int) $serial)->format('Y-m-d');
            }

            return null;
        }

        $value = trim((string) $value);

        // "Q2 2025" => first day of that quarter
        if (preg_match('/^Q([1-4])[\s,]*(\d{4})$/i', $value, $matches)) {
            return Carbon::create((int) $matches[2], ((int) $matches[1] - 1) * 3 + 1, 1)->format('Y-m-d');
        }

        // "Summer 2018", "Fall 2024" => first month of that season
        if (preg_match('/^(spring|summer|fall|autumn|winter)[\s,]*(\d{4})$/i', $value, $matches)) {
            $month = match (strtolower($matches[1])) {
                'spring' => 4,
                'summer' => 7,
                'fall', 'autumn' => 10,
                'winter' => 1,
            };

            return Carbon::create((int) $matches[2], $month, 1)->format('Y-m-d');
        }

        // "May, 2017", "Nov 2018" => first day of that month. Handled explicitly
        // because Carbon::parse('May, 2017') misreads "2017" as a time of day.
        if (preg_match('/^(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*[.,\s]+(\d{4})$/i', $value, $matches)) {
            return Carbon::parse('1 ' . $matches[1] . ' ' . $matches[2])->format('Y-m-d');
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
