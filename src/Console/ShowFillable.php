<?php

namespace Wang9707\MakeTable\Console;

use Illuminate\Console\Command;
use Wang9707\MakeTable\Console\Traits\Make;
use Wang9707\MakeTable\Support\Table;

class ShowFillable extends Command
{
    use Make;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wang:show:fillable
                            {table : table name or model class}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '查看 fillable';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $target = $this->resolveTarget($this->argument('table'));

        if (!$this->checkTable($target)) {
            return;
        }

        $data = $this->getFillable(Table::columns($target), 0);

        $this->info("[\n{$data}\n]");
    }
}
