<?php

namespace Wang9707\MakeTable\Console;

use Illuminate\Console\Command;
use Wang9707\MakeTable\Console\Traits\Make;
use Wang9707\MakeTable\Support\Table;

class ShowAnnotate extends Command
{
    use Make;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wang:show:annotate
                            {table : table name or model class}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '查看注释';

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

        $this->info($this->getProperty(Table::columns($target)));
    }
}
