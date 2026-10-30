<?php

namespace Wang9707\MakeTable\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Wang9707\MakeTable\Console\Traits\Make;
use Wang9707\MakeTable\Support\Table;

class MakeFilter extends Command
{
    use Make;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wang:make:filter
                            {table : table name or model class}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'create filter';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $target = $this->resolveTarget($this->argument('table'));

        $model    = $this->modelName($target);
        $savePath = $this->pathFor('filter') . DIRECTORY_SEPARATOR . "{$model}Filter.php";

        if (!$this->check($target, $savePath)) {
            return;
        }

        $style = (string) config('make-table.filter.style', 'none');

        if (!in_array($style, ['none', 'methods', 'attributes'], true)) {
            $style = 'none';
        }

        $columns = in_array($style, ['methods', 'attributes'], true) ? Table::columns($target) : [];

        $body = match ($style) {
            'attributes' => $this->wrapBody($this->getAttributeBody($columns)),
            'methods'    => $this->wrapBody($this->getMethod($columns)),
            default      => '',
        };

        $useCarbon = $style === 'attributes' && collect($columns)->contains(
            fn (array $column) => $this->convertType($column['type']) === 'Carbon'
        );

        $file = strtr($this->stub('Filter'), [
            '{{filterNamespace}}' => $this->namespaceFor('filter'),
            '{{model}}'           => $model,
            '{{body}}'            => $body,
            '{{filterUse}}'       => $style === 'attributes' ? "use Wang9707\\MakeTable\\Attributes\\Filter;\n" : '',
            '{{carbonUse}}'       => $useCarbon ? "use Illuminate\\Support\\Carbon;\n" : '',
        ]);

        $this->saveFile($savePath, $file);
    }

    /**
     * 包裹类体内容，保证只有空类时不产生多余空行
     *
     * @param string $body
     * @return string
     */
    protected function wrapBody(string $body): string
    {
        return $body === '' ? '' : PHP_EOL . $body . PHP_EOL;
    }

    /**
     * 生成属性注解形式的过滤条件
     *
     * #[Filter('name', 'like')]
     * public ?string $name = null;
     *
     * @param array $columns
     * @return string
     */
    public function getAttributeBody(array $columns): string
    {
        return collect($this->appliedColumns($columns))->map(function (array $column) {
            $operator = $this->convertType($column['type']) === 'string' ? 'like' : '=';
            $property = Str::camel($column['name']);
            $type     = $this->propertyType($column['type']);

            $lines = [];

            if ($column['comment'] !== '') {
                $lines[] = '    /**';
                $lines[] = '     * ' . $column['comment'];
                $lines[] = '     */';
            }

            $lines[] = "    #[Filter('{$column['name']}', '{$operator}')]";
            $lines[] = "    public ?{$type} \${$property} = null;";

            return implode(PHP_EOL, $lines);
        })->implode(PHP_EOL . PHP_EOL);
    }

    /**
     * 注解属性的类型
     *
     * @param string $type
     * @return string
     */
    protected function propertyType(string $type): string
    {
        return match ($this->convertType($type)) {
            'int'    => 'int',
            'float'  => 'float',
            'bool'   => 'bool',
            'array'  => 'array',
            'Carbon' => 'Carbon',
            default  => 'string',
        };
    }

    /**
     * 生成过滤方法
     *
     * @param array $columns
     * @return string
     */
    public function getMethod(array $columns): string
    {
        $template = <<<'TPL'
/**
 * 过滤{comment}
 *
 * @param ${method}
 * @return mixed
 */
public function {method}(${method})
{
    return $this->builder->where('{column}', ${method});
}
TPL;

        return collect($this->appliedColumns($columns))->map(function (array $column) use ($template) {
            $method = Str::camel($column['name']);

            return $this->indent(strtr($template, [
                '{column}'  => $column['name'],
                '{comment}' => $column['comment'],
                '{method}'  => $method,
            ]), 4);
        })->implode(PHP_EOL . PHP_EOL);
    }
}
