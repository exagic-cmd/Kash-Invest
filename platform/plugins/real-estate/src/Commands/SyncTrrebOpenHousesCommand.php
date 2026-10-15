<?php

namespace Botble\RealEstate\Commands;

use Botble\RealEstate\Models\Property;
use Botble\RealEstate\Models\PropertyOpenHouse;
use Botble\RealEstate\Services\Trreb\TrrebClient;
use Botble\RealEstate\Services\Trreb\TrrebPropertySyncer;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand('cms:trreb:sync-open-houses', 'Sync upcoming open houses from TRREB API', aliases: ['cms:trreb:open-houses'])]
class SyncTrrebOpenHousesCommand extends Command
{
    public function handle(TrrebClient $client): int
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '1G');

        $listingKey = $this->option('listing-key');

        if ($listingKey) {
            $this->components->info(sprintf('Fetching open houses for listing %s...', $listingKey));
            $syncer = app(TrrebPropertySyncer::class);
            $property = Property::query()
                ->whereIn('source', TrrebPropertySyncer::SOURCES)
                ->where('unique_id', $listingKey)
                ->first();

            if (! $property) {
                $this->components->error(sprintf('Property with ListingKey %s not found in database.', $listingKey));
                return self::FAILURE;
            }

            $count = $syncer->syncOpenHousesForProperty($property)->count();
            $this->components->success(sprintf('Listing %s: %d active open house(s) synced.', $listingKey, $count));
            return self::SUCCESS;
        }

        $this->components->info('Starting bulk Open House sync from TRREB RESO API...');

        // Purge expired past open houses
        $today = Carbon::now('America/Toronto')->toDateString();
        $purged = PropertyOpenHouse::query()
            ->where('open_house_date', '<', $today)
            ->delete();

        if ($purged > 0) {
            $this->components->info(sprintf('Cleaned up %d past open house record(s).', $purged));
        }

        $top = 100;
        $skip = 0;
        $totalProcessed = 0;
        $syncedCount = 0;

        do {
            try {
                $response = $client->openHouses($top, $skip, "OpenHouseStatus eq 'Active'");
            } catch (\Throwable $e) {
                $this->components->error('Error connecting to TRREB OpenHouse API: ' . $e->getMessage());
                return self::FAILURE;
            }

            $items = Arr::get($response, 'value', []);

            if (empty($items)) {
                break;
            }

            $listingKeys = array_unique(array_filter(array_column($items, 'ListingKey')));

            $properties = Property::query()
                ->whereIn('source', TrrebPropertySyncer::SOURCES)
                ->whereIn('unique_id', $listingKeys)
                ->pluck('id', 'unique_id');

            foreach ($items as $oh) {
                $key = Arr::get($oh, 'ListingKey');
                $openHouseKey = Arr::get($oh, 'OpenHouseKey');

                if (! $key || ! $openHouseKey || ! isset($properties[$key])) {
                    continue;
                }

                $propertyId = $properties[$key];
                $date = Arr::get($oh, 'OpenHouseDate');
                $startTime = Arr::get($oh, 'OpenHouseStartTime');
                $endTime = Arr::get($oh, 'OpenHouseEndTime');
                $timeRange = null;

                if ($startTime && $endTime) {
                    try {
                        $start = Carbon::parse($startTime)->timezone('America/Toronto');
                        $end = Carbon::parse($endTime)->timezone('America/Toronto');
                        $startFmt = $start->minute === 0 ? $start->format('g') : $start->format('g:i');
                        $endFmt = $end->minute === 0 ? $end->format('g a') : $end->format('g:i a');
                        $timeRange = sprintf('%s-%s', $startFmt, $endFmt);
                    } catch (\Throwable) {
                        $timeRange = null;
                    }
                }

                PropertyOpenHouse::query()->updateOrCreate(
                    ['open_house_key' => $openHouseKey],
                    [
                        'property_id' => $propertyId,
                        'listing_key' => $key,
                        'open_house_date' => $date,
                        'start_time' => $startTime ? Carbon::parse($startTime) : null,
                        'end_time' => $endTime ? Carbon::parse($endTime) : null,
                        'time_range' => $timeRange,
                        'format' => Arr::get($oh, 'OpenHouseFormat', 'In Person'),
                        'type' => Arr::get($oh, 'OpenHouseType', 'Public'),
                        'status' => Arr::get($oh, 'OpenHouseStatus', 'Active'),
                        'url' => Arr::get($oh, 'OpenHouseURL'),
                    ]
                );

                $syncedCount++;
            }

            $totalProcessed += count($items);
            $this->components->twoColumnDetail(
                sprintf('Batch skip %d to %d', $skip, $skip + count($items)),
                sprintf('<fg=green>%d open houses saved</>', $syncedCount)
            );

            $skip += $top;
        } while (count($items) === $top);

        $this->components->success(sprintf(
            'Completed Open House sync! Processed %d feed records, saved %d upcoming open houses.',
            $totalProcessed,
            $syncedCount
        ));

        return self::SUCCESS;
    }

    protected function configure(): void
    {
        $this->addOption(
            'listing-key',
            'k',
            InputOption::VALUE_REQUIRED,
            'Sync open houses for a specific listing by its ListingKey (e.g. E13060676).'
        );
    }
}
