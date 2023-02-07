<?php
namespace Wang9707\MakeTable\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Wang9707\MakeTable\Console\Traits\Make;
use Wang9707\MakeTable\Table\Table;

class ShowAnnotate extends Command
{
    use Make;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wang:show:annotate
                            {table : table name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '查看注释';

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

        $data = $this->annotate($arr);

        $this->info($data);

    }

    /**
     * 获取模型注释
     *
     * @param array $columns
     * @return string
     */
    public function annotate(array $columns)
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
     * 文件类型转换
     *
     * @param string $type
     */
    public function convertType(string $type)
    {
        $type = strtoupper($type);

        return match($type) {
            'CHAR', 'VARCHAR', 'TINYBLOB', 'TINYTEXT', 'BLOB', 'TEXT', 'MEDIUMBLOB', 'MEDIUMTEXT', 'LONGBLOB', 'LONGTEXT' => 'string',
            'DATE', 'TIME', 'YEAR', 'DATETIME', 'TIMESTAMP' => 'Carbon',
            'TINYINT', 'SMALLINT', 'MEDIUMINT', 'INT', 'INTEGER', 'BIGINT' => 'int',
            'FLOAT', 'DOUBLE', 'DECIMAL' => 'float',
            'JSON' => 'array',
        default=> 'string',
        };
    }
}
