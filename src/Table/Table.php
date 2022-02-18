<?php

namespace Wang9707\MakeTable\Table;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Table
{
    /**
     * 获取数据库名称
     *
     * @return string
     */
    public static function getDB()
    {
        return DB::connection()->getDatabaseName();
    }

    /**
     * 判断表是否存在
     *
     * @param string $name
     * @return bool
     */
    public static function hasTable(string $name)
    {
        return Schema::hasTable($name);
    }
}
