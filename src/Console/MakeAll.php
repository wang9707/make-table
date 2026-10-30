<?php

namespace Wang9707\MakeTable\Console;

use Illuminate\Console\Command;
use Wang9707\MakeTable\Console\Traits\Make;

class MakeAll extends Command
{
    use Make;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wang:make:all
                            {table : table name or model class}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'create model, request, controller, service and filter';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $table = $this->argument('table');

        if (!$this->checkTable($table)) {
            return;
        }

        $this->call('wang:make:model', ['table' => $table]);
        $this->call('wang:make:request', ['table' => $table]);
        $this->call('wang:make:controller', ['table' => $table]);
        $this->call('wang:make:service', ['table' => $table]);
        $this->call('wang:make:filter', ['table' => $table]);
    }
}
