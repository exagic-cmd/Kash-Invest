<?php

namespace Botble\RealEstate\Providers;

use Botble\RealEstate\Commands\BuildifyFieldReportCommand;
use Botble\RealEstate\Commands\PurgeTrrebDataCommand;
use Botble\RealEstate\Commands\RebuildRedbricksProjectsCommand;
use Botble\RealEstate\Commands\RedbricksFieldReportCommand;
use Botble\RealEstate\Commands\RenewPropertiesCommand;
use Botble\RealEstate\Commands\SyncBuildifyProjectsCommand;
use Botble\RealEstate\Commands\SyncRedbricksProjectsCommand;
use Botble\RealEstate\Commands\SyncTrrebPropertiesCommand;
use Botble\RealEstate\Commands\UpdateProjectSlugsCommand;
use Illuminate\Support\ServiceProvider;

class CommandServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->commands([
            BuildifyFieldReportCommand::class,
            PurgeTrrebDataCommand::class,
            RebuildRedbricksProjectsCommand::class,
            RedbricksFieldReportCommand::class,
            RenewPropertiesCommand::class,
            SyncBuildifyProjectsCommand::class,
            SyncRedbricksProjectsCommand::class,
            SyncTrrebPropertiesCommand::class,
            UpdateProjectSlugsCommand::class,
        ]);
    }
}
