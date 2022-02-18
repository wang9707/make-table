<?php

namespace Wang9707\MakeTable;

use Wang9707\MakeTable\Console\MakeModel;
use Wang9707\MakeTable\Console\MakeService;
use Wang9707\MakeTable\Console\MakeController;
use Wang9707\MakeTable\Console\MakeFilter;
use Wang9707\MakeTable\Console\MakeAll;


class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeModel::class,
                MakeService::class,
                MakeController::class,
                MakeFilter::class,
                MakeAll::class,
            ]);
        }
    }
}
