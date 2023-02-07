<?php
namespace Wang9707\MakeTable\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Wang9707\MakeTable\Console\Traits\Make;
use Wang9707\MakeTable\Table\Table;

class ShowFillable extends Command
{
    use Make;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wang:show:fillable
                            {table : table name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '查看 fillable';

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

        $db    = Table::getDB();
        $model = Str::studly($table);

        $checkResult = $this->checkTable($table);
        if (!$checkResult) {
            return;
        }

        $arr = DB::select('SELECT * FROM information_schema.columns WHERE table_schema=? AND TABLE_NAME=?', [$db, $table]);

        $data = $this->getFillable($arr);

        $this->info('[
            ' . $data . ']');

    }

    /**
     * 获取模型的 fillable
     *
     * @param array $columns
     * @return string
     */
    public function getFillable(array $columns)
    {
        $template = <<<TPL
'{column}', //{comment}

TPL;

        return collect($columns)->transform(function ($column) {
            return [
                'column'  => data_get($column, 'COLUMN_NAME'),
                'comment' => data_get($column, 'COLUMN_COMMENT'),
            ];
        })->transform(function ($column) use ($template) {
            return strtr($template, [
                '{column}'  => $column['column'],
                '{comment}' => $column['comment'],
            ]);
        })->implode(PHP_EOL);
    }
}
