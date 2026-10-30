<?php

namespace Wang9707\MakeTable\Support;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Table
{
    /**
     * 获取数据库名称
     *
     * @param string|null $connection
     * @return string
     */
    public static function getDB(?string $connection = null): string
    {
        return (string) DB::connection($connection)->getDatabaseName();
    }

    /**
     * 判断表是否存在，支持表名或模型
     *
     * @param string|Model $table
     * @return bool
     */
    public static function hasTable($table): bool
    {
        [$name, $connection] = static::resolve($table);

        return $connection->getSchemaBuilder()->hasTable($name);
    }

    /**
     * 获取表的列信息，支持表名或模型（模型会使用自己的连接）
     *
     * 统一返回：
     * [['name' => 'id', 'type' => 'int', 'type_raw' => 'int', 'comment' => '',
     *   'nullable' => false, 'auto_increment' => true, 'default' => null], ...]
     *
     * @param string|Model $table
     * @return array
     */
    public static function columns($table): array
    {
        [$name, $connection] = static::resolve($table);

        $builder = $connection->getSchemaBuilder();

        // Laravel 10.30+，支持 MySQL / PostgreSQL / SQLite
        if (method_exists($builder, 'getColumns')) {
            return collect($builder->getColumns($name))->map(function (array $column) {
                return [
                    'name'           => $column['name'],
                    'type'           => $column['type_name'] ?? $column['type'] ?? '',
                    'type_raw'       => $column['type'] ?? '',
                    'comment'        => $column['comment'] ?? '',
                    'nullable'       => (bool) ($column['nullable'] ?? false),
                    'auto_increment' => (bool) ($column['auto_increment'] ?? false),
                    'default'        => $column['default'] ?? null,
                ];
            })->all();
        }

        // 老版本 Laravel：MySQL 用 information_schema 拿完整信息
        if ($connection->getDriverName() === 'mysql') {
            return static::mysqlColumns($connection, $name);
        }

        // 其他驱动只拿得到列名
        return collect($builder->getColumnListing($name))
            ->map(fn (string $column) => static::emptyColumn($column))
            ->all();
    }

    /**
     * 解析命令参数 / 表参数，返回 [表名, 连接]
     *
     * @param string|Model $table
     * @return array
     */
    protected static function resolve($table): array
    {
        if ($table instanceof Model) {
            return [$table->getTable(), $table->getConnection()];
        }

        if (is_string($table) && class_exists($table) && is_subclass_of($table, Model::class)) {
            $model = new $table;

            return [$model->getTable(), $model->getConnection()];
        }

        return [(string) $table, DB::connection()];
    }

    /**
     * 老版本 Laravel + MySQL 的列信息
     *
     * @param ConnectionInterface $connection
     * @param string $table
     * @return array
     */
    protected static function mysqlColumns(ConnectionInterface $connection, string $table): array
    {
        $columns = $connection->select(
            "SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, COLUMN_COMMENT, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
             FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? ORDER BY ORDINAL_POSITION",
            [$connection->getTablePrefix() . $table]
        );

        return collect($columns)->map(function ($column) {
            return [
                'name'           => $column->COLUMN_NAME,
                'type'           => $column->DATA_TYPE,
                'type_raw'       => $column->COLUMN_TYPE,
                'comment'        => $column->COLUMN_COMMENT ?? '',
                'nullable'       => strtoupper((string) $column->IS_NULLABLE) === 'YES',
                'auto_increment' => stripos((string) $column->EXTRA, 'auto_increment') !== false,
                'default'        => $column->COLUMN_DEFAULT,
            ];
        })->all();
    }

    /**
     * 只有列名时的默认结构
     *
     * @param string $name
     * @return array
     */
    protected static function emptyColumn(string $name): array
    {
        return [
            'name'           => $name,
            'type'           => '',
            'type_raw'       => '',
            'comment'        => '',
            'nullable'       => false,
            'auto_increment' => false,
            'default'        => null,
        ];
    }
}
