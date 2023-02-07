<?php

namespace Wang9707\MakeTable\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Wang9707\MakeTable\Console\Traits\Make;
use Wang9707\MakeTable\Table\Table;

class MakeModel extends Command
{
    use Make;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wang:make:model
                            {table : table name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '生产模型';

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

        $savePath = app_path("Models" . DIRECTORY_SEPARATOR . "{$model}.php");

        $checkResult = $this->check($table, $savePath);
        if (!$checkResult) {
            return;
        }

        $arr = DB::select('SELECT * FROM information_schema.columns WHERE table_schema=? AND TABLE_NAME=?', [$db, $table]);

        $file = file_get_contents(dirname(__FILE__) . '../../Resources/Stubs/Model.stub');

        $file = strtr($file, [
            '{{Model}}'    => $model,
            '{{property}}' => $this->getProperty($arr),
            '{{table}}'    => $table,
            '{{dates}}'    => $this->getDattes($arr),
            '{{fillable}}' => $this->getFillable($arr),
            '{{casts}}'    => $this->getCasts($arr),
        ]);

        $this->saveFile($savePath, $file);
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

    /**
     * 获取模型注释
     *
     * @param array $columns
     * @return string
     */
    public function getProperty(array $columns)
    {
        $template = <<<TPL
 * @property {type} {name}
TPL;

        return collect($columns)->transform(function ($column) {
            return [
                'type' => data_get($column, 'DATA_TYPE'),
                'name' => data_get($column, 'COLUMN_NAME'),
            ];
        })->transform(function ($column) use ($template) {
            return strtr($template, [
                '{type}' => $this->convertType($column['type']),
                '{name}' => '$' . $column['name'],
            ]);
        })->implode(PHP_EOL);
    }

    /**
     * 获取日期转换字段
     *
     * @param array $columns
     * @return string
     */
    public function getDattes(array $columns)
    {
        $template = <<<TPL
         '{column}',
TPL;

        return collect($columns)->transform(function ($column) {

            if ($this->convertType(data_get($column, 'DATA_TYPE')) == 'Carbon') {
                return [
                    'name' => data_get($column, 'COLUMN_NAME'),
                ];
            }
        })->filter()->transform(function ($column) use ($template) {
            return strtr($template, [
                '{column}' => $column['name'],
            ]);
        })->implode(PHP_EOL);
    }

    public function getCasts(array $columns)
    {
        $template = <<<TPL
         '{column}' => 'array',
TPL;
        return collect($columns)->transform(function ($column) {

            if ($this->convertType(data_get($column, 'DATA_TYPE')) == 'array') {
                return [
                    'name' => data_get($column, 'COLUMN_NAME'),
                ];
            }
        })->filter()->transform(function ($column) use ($template) {
            return strtr($template, [
                '{column}' => $column['name'],
            ]);
        })->implode(PHP_EOL);
    }

    /**
     * 文件类型转换
     *
     * @param string $type
     */
    public function convertType(string $type)
    {
        $type = strtoupper($type);

        return match ($type) {
            'CHAR', 'VARCHAR', 'TINYBLOB', 'TINYTEXT', 'BLOB', 'TEXT', 'MEDIUMBLOB', 'MEDIUMTEXT', 'LONGBLOB', 'LONGTEXT' => 'string',
            'DATE', 'TIME', 'YEAR', 'DATETIME', 'TIMESTAMP' => 'Carbon',
            'TINYINT', 'SMALLINT', 'MEDIUMINT', 'INT', 'INTEGER', 'BIGINT' => 'int',
            'FLOAT', 'DOUBLE', 'DECIMAL' => 'float',
            'JSON' => 'array',
            default => 'string',
        };
    }
}
