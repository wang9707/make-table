<?php

namespace Wang9707\MakeTable;

use Wang9707\MakeTable\Console\MakeAll;
use Wang9707\MakeTable\Console\MakeController;
use Wang9707\MakeTable\Console\MakeFilter;
use Wang9707\MakeTable\Console\MakeModel;
use Wang9707\MakeTable\Console\MakeRequest;
use Wang9707\MakeTable\Console\MakeService;
use Wang9707\MakeTable\Console\ShowAnnotate;
use Wang9707\MakeTable\Console\ShowFillable;

class MakeTableServiceProvider extends \Illuminate\Support\ServiceProvider
{
    /**
     * 注册配置
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/make-table.php', 'make-table');
    }

    /**
     * 引导扩展
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/make-table.php' => config_path('make-table.php'),
        ], 'make-table');

        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeModel::class,
                MakeRequest::class,
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
