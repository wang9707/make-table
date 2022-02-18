<?php

namespace Wang9707\MakeTable\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Wang9707\MakeTable\Console\Traits\Make;
use  Wang9707\MakeTable\Table\Table;

class MakeFilter extends Command
{
    use Make;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wang:make:filter
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

        $db = Table::getDB();

        $model = Str::studly($table);

        $savePath = (app_path("Models\\Filter\\{$model}Filter.php"));

        $checkResult = $this->check($table, $savePath);
        if (!$checkResult) {
            return;
        }

        $file = file_get_contents(dirname(__FILE__) . '../../resources/stubs/Filter.stub');

        $arr = DB::select('SELECT * FROM information_schema.columns WHERE table_schema=? AND TABLE_NAME=?', [$db, $table]);

        $file = strtr($file, [
            '{{Model}}'  => $model,
            '{{method}}' => $this->getMethod($arr),
        ]);

        $this->saveFile($savePath, $file);
    }


    /**
     * 获取模型注释
     *
     * @param array $columns
     * @return string
     */
    public function getMethod(array $columns)
    {
        $template = <<<TPL
    /**
     * 过滤{comment}
     *
     * @param \${method_name}
     * @return mixed
     */
    public function {method_name}(\${method_name})
    {
        return \$this->builder->where('{column}', \${method_name});
    }


TPL;

        return collect($columns)->transform(function ($column) {
            return [
                'method_name' => Str::camel(data_get($column, 'COLUMN_NAME')),
                'column'      => data_get($column, 'COLUMN_NAME'),
                'comment'     => data_get($column, 'COLUMN_COMMENT'),
            ];
        })->transform(function ($column) use ($template) {
            return strtr($template, [
                '{column}'      => $column['column'],
                '{comment}'     => $column['comment'],
                '{method_name}' => $column['method_name'],
            ]);
        })->implode(PHP_EOL);
    }

}
