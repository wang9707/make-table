<?php

namespace Wang9707\MakeTable;

use Wang9707\MakeTable\Console\CreateModel;

class ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CreateModel::class
            ]);
        }
    }
}