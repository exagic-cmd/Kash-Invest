<?php

namespace Botble\RealEstate\Providers;

use Botble\RealEstate\Commands\RenewPropertiesCommand;
use Botble\RealEstate\Commands\SyncBuildifyProjectsCommand;
use Botble\RealEstate\Commands\UpdateProjectSlugsCommand;
use Illuminate\Support\ServiceProvider;

class CommandServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->commands([
            RenewPropertiesCommand::class,
            SyncBuildifyProjectsCommand::class,
            UpdateProjectSlugsCommand::class,
        ]);
    }
}
