<?php

namespace Wang9707\MakeTable\Console\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Wang9707\MakeTable\Support\Table;

trait Make
{
    /**
     * 各类文件的默认命名空间
     *
     * @var array
     */
    protected array $defaultNamespaces = [
        'model'      => 'App\\Models',
        'service'    => 'App\\Services',
        'controller' => 'App\\Http\\Controllers',
        'request'    => 'App\\Http\\Requests',
        'filter'     => 'App\\Models\\Filter',
    ];

    public function check($target, string $path)
    {
        return $this->checkTable($target) && $this->checkFile($path);
    }

    /**
     * 检查要生成的文件是否存在
     *
     * @param string $savePath
     * @return bool
     */
    public function checkFile(string $savePath)
    {
        if (file_exists($savePath)) {
            $this->error("file already exist --{$savePath}");
            return false;
        }

        return true;
    }

    /**
     * 检查表是否存在
     *
     * @param string|\Illuminate\Database\Eloquent\Model $target
     * @return bool
     */
    public function checkTable($target)
    {
        if (!Table::hasTable($target)) {
            $this->error("table does not exist --" . $this->tableName($target));
            return false;
        }

        return true;
    }

    /**
     * 解析命令参数：表名或模型类（如 App\Models\User）
     *
     * @param string $argument
     * @return string|\Illuminate\Database\Eloquent\Model
     */
    protected function resolveTarget(string $argument)
    {
        if (class_exists($argument) && is_subclass_of($argument, Model::class)) {
            return new $argument;
        }

        return $argument;
    }

    /**
     * 参数对应的表名
     *
     * @param string|Model $target
     * @return string
     */
    protected function tableName($target): string
    {
        return $target instanceof Model ? $target->getTable() : (string) $target;
    }

    /**
     * 参数对应的模型名
     *
     * @param string|Model $target
     * @return string
     */
    protected function modelName($target): string
    {
        return $target instanceof Model ? class_basename($target) : Str::studly((string) $target);
    }

    /**
     * 保存文件
     *
     * @param string $savePath
     * @param string $file
     * @return bool
     */
    public function saveFile(string $savePath, string $file)
    {
        if (!file_exists(dirname($savePath))) {
            mkdir(dirname($savePath), 0755, true);
        }

        if (file_put_contents($savePath, $file) === false) {
            $this->error("file create failed --{$savePath}");
            return false;
        }

        $this->info("file create success --{$savePath}");
        return true;
    }

    /**
     * 读取 stub 模板
     *
     * @param string $name
     * @return string
     */
    protected function stub(string $name): string
    {
        $path = dirname(__DIR__, 3) . '/stubs/' . $name . '.stub';

        if (!is_file($path)) {
            throw new \RuntimeException("stub file not found --{$path}");
        }

        return file_get_contents($path);
    }

    /**
     * 生成目录，读取 make-table.{type}.path，缺省使用默认值
     *
     * @param string $type model|service|controller|filter
     * @return string
     */
    protected function pathFor(string $type): string
    {
        $path = config("make-table.{$type}.path");

        if (!$path) {
            $path = [
                'model'      => app_path('Models'),
                'service'    => app_path('Services'),
                'controller' => app_path('Http/Controllers'),
                'request'    => app_path('Http/Requests'),
                'filter'     => app_path('Models/Filter'),
            ][$type] ?? app_path();
        }

        return rtrim($path, '/\\');
    }

    /**
     * 生成命名空间，读取 make-table.{type}.namespace，缺省使用默认值
     *
     * @param string $type model|service|controller|filter
     * @return string
     */
    protected function namespaceFor(string $type): string
    {
        return trim(config("make-table.{$type}.namespace") ?: ($this->defaultNamespaces[$type] ?? 'App'), '\\');
    }

    /**
     * 拼接完整的类名，如 App\Models\User
     *
     * @param string $type
     * @param string $class
     * @return string
     */
    protected function fqcn(string $type, string $class): string
    {
        return $this->namespaceFor($type) . '\\' . $class;
    }

    /**
     * 去掉配置中忽略的列
     *
     * @param array $columns
     * @return array
     */
    protected function appliedColumns(array $columns): array
    {
        $ignore = (array) config('make-table.ignore_columns', []);

        if (empty($ignore)) {
            return $columns;
        }

        return array_values(array_filter($columns, fn (array $column) => !in_array($column['name'], $ignore, true)));
    }

    /**
     * 给每一行加缩进
     *
     * @param string $code
     * @param int $spaces
     * @return string
     */
    protected function indent(string $code, int $spaces = 0): string
    {
        if ($code === '') {
            return '';
        }

        return collect(explode(PHP_EOL, $code))
            ->map(fn (string $line) => $line === '' ? $line : str_repeat(' ', $spaces) . $line)
            ->implode(PHP_EOL);
    }

    /**
     * 获取模型的 fillable 内容
     *
     * @param array $columns
     * @param int $spaces
     * @return string
     */
    public function getFillable(array $columns, int $spaces = 8): string
    {
        $lines = collect($this->appliedColumns($columns))
            ->map(fn (array $column) => $column['comment'] === ''
                ? "'{$column['name']}',"
                : "'{$column['name']}', //{$column['comment']}")
            ->implode(PHP_EOL);

        return $this->indent($lines, $spaces);
    }

    /**
     * 获取模型注释
     *
     * @param array $columns
     * @return string
     */
    public function getProperty(array $columns): string
    {
        return collect($columns)
            ->map(fn (array $column) => ' * @property ' . $this->convertType($column['type']) . ' $' . $column['name'])
            ->implode(PHP_EOL);
    }

    /**
     * 列类型转换
     *
     * @param string $type
     * @return string
     */
    public function convertType(string $type): string
    {
        return match (strtoupper($type)) {
            'CHAR', 'VARCHAR', 'TINYBLOB', 'TINYTEXT', 'BLOB', 'TEXT', 'MEDIUMBLOB', 'MEDIUMTEXT', 'LONGBLOB', 'LONGTEXT', 'BINARY', 'VARBINARY', 'TIME', 'UUID', 'ENUM', 'SET' => 'string',
            'DATE', 'DATETIME', 'TIMESTAMP' => 'Carbon',
            'YEAR', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'INT', 'INTEGER', 'BIGINT' => 'int',
            'FLOAT', 'DOUBLE', 'DECIMAL', 'NUMERIC', 'MONEY' => 'float',
            'BOOL', 'BOOLEAN' => 'bool',
            'JSON' => 'array',
            default => 'string',
        };
    }
}
