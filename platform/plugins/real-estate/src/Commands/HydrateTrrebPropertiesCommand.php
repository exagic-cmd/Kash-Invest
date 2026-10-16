<?php

namespace Botble\RealEstate\Commands;

use Botble\RealEstate\Models\Property;
use Botble\RealEstate\Services\Trreb\TrrebPropertySyncer;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

#[AsCommand('cms:trreb:hydrate', 'Download media for TRREB properties into S3', aliases: ['cms:trreb:hydrate-media'])]
class HydrateTrrebPropertiesCommand extends Command
{
    public function handle(TrrebPropertySyncer $syncer): int
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '1G');

        $query = Property::query()
            ->whereIn('source', TrrebPropertySyncer::SOURCES);

        if (! $this->option('force')) {
            $query->where(function ($q) {
                $q->whereNull('images')
                    ->orWhere('images', '[]')
                    ->orWhere('images', '');
            });
        }

        if ($listingKey = $this->option('listing-key')) {
            $query->where('unique_id', $listingKey);
        }

        $shards = max((int) $this->option('shards'), 1);
        $shard = (int) $this->option('shard');

        if ($shards > 1 && $shard >= 1 && $shard <= $shards) {
            $remainder = $shard - 1;
            $query->whereRaw('MOD(id, ?) = ?', [$shards, $remainder]);
            $this->components->info(sprintf('Parallel Worker: Shard %d of %d active.', $shard, $shards));
        }

        $total = $query->count();

        if ($total === 0) {
            $this->components->info('All TRREB properties already have media hydrated.');

            return self::SUCCESS;
        }

        $this->components->info(sprintf('Hydrating media for %d TRREB propert(ies) into S3...', $total));

        $count = 0;
        $failed = 0;
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        foreach ($query->cursor() as $property) {
            $count++;
            $key = $property->unique_id;

            try {
                $images = $syncer->hydrateMedia($property, $limit);
                $imageCount = count($images);

                if ($imageCount > 0) {
                    $this->components->twoColumnDetail(
                        sprintf('[%d/%d] %s (%s)', $count, $total, $property->name, $key),
                        sprintf('<fg=green>%d image(s) uploaded to S3</>', $imageCount)
                    );
                } else {
                    $this->components->twoColumnDetail(
                        sprintf('[%d/%d] %s (%s)', $count, $total, $property->name, $key),
                        '<fg=yellow>No media found</>'
                    );
                }
            } catch (Throwable $e) {
                $failed++;
                $this->components->twoColumnDetail(
                    sprintf('[%d/%d] %s (%s)', $count, $total, $property->name, $key),
                    sprintf('<fg=red>Failed: %s</>', $e->getMessage())
                );
                report($e);
            }
        }

        $this->newLine();
        $this->components->success(sprintf('Hydration finished: %d processed, %d failed.', $count, $failed));

        return self::SUCCESS;
    }

    protected function configure(): void
    {
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Re-fetch media for all TRREB properties even if they already have images.'
        );

        $this->addOption(
            'listing-key',
            'k',
            InputOption::VALUE_REQUIRED,
            'Hydrate media for a specific listing key only.'
        );

        $this->addOption(
            'limit',
            'l',
            InputOption::VALUE_OPTIONAL,
            'Max number of images to download per property.'
        );

        $this->addOption(
            'shard',
            null,
            InputOption::VALUE_OPTIONAL,
            'Shard number (1-based index) for parallel processing.'
        );

        $this->addOption(
            'shards',
            null,
            InputOption::VALUE_OPTIONAL,
            'Total number of parallel shards/workers.',
            1
        );
    }
}
