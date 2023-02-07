<?php

namespace Wang9707\MakeTable;

use Wang9707\MakeTable\Console\MakeAll;
use Wang9707\MakeTable\Console\MakeController;
use Wang9707\MakeTable\Console\MakeFilter;
use Wang9707\MakeTable\Console\MakeModel;
use Wang9707\MakeTable\Console\MakeService;
use Wang9707\MakeTable\Console\ShowAnnotate;
use Wang9707\MakeTable\Console\ShowFillable;

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
                ShowFillable::class,
                ShowAnnotate::class,
            ]);
        }
    }
}
