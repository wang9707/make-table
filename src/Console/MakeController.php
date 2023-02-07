<?php

namespace Wang9707\MakeTable\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Wang9707\MakeTable\Console\Traits\Make;
use Wang9707\MakeTable\Table\Table;

class MakeController extends Command
{
    use Make;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wang:make:controller
                            {table : table name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'create controller';

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

        $db = Table::getDB();

        $model = Str::studly($table);

        $savePath = (app_path("Http" . DIRECTORY_SEPARATOR . "Controllers" . DIRECTORY_SEPARATOR . "{$model}Controller.php"));

        $checkResult = $this->check($table, $savePath);
        if (!$checkResult) {
            return;
        }

        $file = file_get_contents(dirname(__FILE__) . '../../Resources/Stubs/Controller.stub');

        $file = strtr($file, [
            '{{Model}}' => $model,
        ]);

        $this->saveFile($savePath, $file);
    }

}
