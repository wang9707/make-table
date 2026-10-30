<?php

namespace Wang9707\MakeTable\Console;

use Illuminate\Console\Command;
use Wang9707\MakeTable\Console\Traits\Make;
use Wang9707\MakeTable\Support\Table;

class MakeRequest extends Command
{
    use Make;

    /**
     * 自动维护、不需要验证的时间戳列
     *
     * @var array
     */
    protected array $timestamps = ['created_at', 'updated_at', 'deleted_at'];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wang:make:request
                            {table : table name or model class}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'create form request';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $target = $this->resolveTarget($this->argument('table'));

        $model    = $this->modelName($target);
        $savePath = $this->pathFor('request') . DIRECTORY_SEPARATOR . "{$model}Request.php";

        if (!$this->check($target, $savePath)) {
            return;
        }

        $file = strtr($this->stub('Request'), [
            '{{requestNamespace}}' => $this->namespaceFor('request'),
            '{{model}}'            => $model,
            '{{rules}}'            => $this->getRules(Table::columns($target)),
        ]);

        $this->saveFile($savePath, $file);
    }

    /**
     * 生成验证规则
     *
     * @param array $columns
     * @return string
     */
    public function getRules(array $columns): string
    {
        $lines = collect($this->appliedColumns($columns))
            ->reject(fn (array $column) => $column['auto_increment'] || in_array($column['name'], $this->timestamps, true))
            ->map(fn (array $column) => [
                'name'    => $column['name'],
                'rules'   => $this->rulesFor($column),
                'comment' => $column['comment'],
            ])
            ->map(fn (array $column) => $column['comment'] === ''
                ? "'{$column['name']}' => '{$column['rules']}',"
                : "'{$column['name']}' => '{$column['rules']}', //{$column['comment']}")
            ->implode(PHP_EOL);

        return $this->indent($lines, 12);
    }

    /**
     * 根据是否可空和数据类型生成单条列的验证规则
     *
     * @param array $column
     * @return string
     */
    public function rulesFor(array $column): string
    {
        $rules = [$column['nullable'] ? 'nullable' : 'required'];

        $type = strtoupper($column['type']);

        if (in_array($type, ['TINYINT', 'SMALLINT', 'MEDIUMINT', 'INT', 'INTEGER', 'BIGINT', 'YEAR'], true)) {
            $rules[] = 'integer';
        } elseif (in_array($type, ['DECIMAL', 'FLOAT', 'DOUBLE', 'NUMERIC', 'MONEY'], true)) {
            $rules[] = 'numeric';
        } elseif (in_array($type, ['BOOL', 'BOOLEAN'], true)) {
            $rules[] = 'boolean';
        } elseif (in_array($type, ['DATE', 'DATETIME', 'TIMESTAMP', 'TIME'], true)) {
            $rules[] = 'date';
        } elseif ($type === 'JSON') {
            $rules[] = 'array';
        } elseif ($type === 'ENUM') {
            $rules[] = 'string';
            $rules = array_merge($rules, $this->enumRules($column['type_raw']));
        } else {
            $rules[] = 'string';

            if (preg_match('/\((\d+)\)/', (string) $column['type_raw'], $match)) {
                $rules[] = 'max:' . $match[1];
            }
        }

        return implode('|', $rules);
    }

    /**
     * 从 enum('a','b') 中解析可选值
     *
     * @param string $typeRaw
     * @return array
     */
    protected function enumRules(string $typeRaw): array
    {
        if (!preg_match_all("/'((?:[^']|'')*)'/", $typeRaw, $matches)) {
            return [];
        }

        return $matches[1] ? ['in:' . implode(',', $matches[1])] : [];
    }
}
