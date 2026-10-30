<?php

namespace Wang9707\MakeTable\Console;

use Illuminate\Console\Command;
use Wang9707\MakeTable\Console\Traits\Make;
use Wang9707\MakeTable\Support\Table;

class MakeModel extends Command
{
    use Make;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wang:make:model
                            {table : table name or model class}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '生产模型';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $target = $this->resolveTarget($this->argument('table'));

        $table    = $this->tableName($target);
        $model    = $this->modelName($target);
        $savePath = $this->pathFor('model') . DIRECTORY_SEPARATOR . "{$model}.php";

        if (!$this->check($target, $savePath)) {
            return;
        }

        $columns = Table::columns($target);

        $softDeletes = config('make-table.soft_deletes', true)
            && collect($columns)->contains(fn (array $column) => $column['name'] === 'deleted_at');

        $file = strtr($this->stub('Model'), [
            '{{modelNamespace}}' => $this->namespaceFor('model'),
            '{{model}}'          => $model,
            '{{table}}'          => $table,
            '{{property}}'       => $this->getProperty($columns),
            '{{fillable}}'       => $this->getFillable($columns, 8),
            '{{casts}}'          => $this->getCasts($columns, 8),
            '{{softDeletesUse}}' => $softDeletes ? "use Illuminate\\Database\\Eloquent\\SoftDeletes;\n" : '',
            '{{softDeletes}}'    => $softDeletes ? ', SoftDeletes' : '',
        ]);

        $this->saveFile($savePath, $file);
    }

    /**
     * 获取属性转换
     *
     * @param array $columns
     * @param int $spaces
     * @return string
     */
    public function getCasts(array $columns, int $spaces = 8): string
    {
        $lines = collect($columns)
            ->map(fn (array $column) => [
                'name' => $column['name'],
                'cast' => $this->castType($column['type']),
            ])
            ->filter(fn (array $column) => !is_null($column['cast']))
            ->map(fn (array $column) => "'{$column['name']}' => '{$column['cast']}',")
            ->implode(PHP_EOL);

        return $this->indent($lines, $spaces);
    }

    /**
     * 列类型对应的 cast，不需要转换时返回 null
     *
     * @param string $type
     * @return string|null
     */
    public function castType(string $type)
    {
        return match (strtoupper($type)) {
            'JSON' => 'array',
            'DATE' => 'date',
            'DATETIME', 'TIMESTAMP' => 'datetime',
            'BOOL', 'BOOLEAN' => 'boolean',
            default => null,
        };
    }
}
