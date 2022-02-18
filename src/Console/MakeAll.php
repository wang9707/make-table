<?php

namespace Wang9707\MakeTable\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Wang9707\MakeTable\Console\Traits\Make;
use  Wang9707\MakeTable\Table\Table;

class MakeAll extends Command
{
    use Make;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wang:make:all
                            {table : table name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'create filter';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     */
    public function handle()
    {
        $table = $this->argument('table');
        $this->checkTable($table);


        $this->call('wang:make:model', ['table' => $table]);
        $this->call('wang:make:controller', ['table' => $table]);
        $this->call('wang:make:service', ['table' => $table]);
        $this->call('wang:make:filter', ['table' => $table]);
    }

}
