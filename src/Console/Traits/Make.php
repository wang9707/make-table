<?php

namespace Wang9707\MakeTable\Console\Traits;

use Wang9707\MakeTable\Table\Table;

trait Make
{
    public function check(string $name, string $path)
    {
        return $this->checkTable($name) && $this->checkFile($path);

    }

    /**
     * 检查要生成的文件是否存在
     *
     * @param string $path
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
     * @param string $table
     */
    public function checkTable(string $table)
    {
        if (!Table::hasTable($table)) {
            $this->error("table does not exist ");
            return false;
        }
        return true;
    }

    /**
     * 保存文件
     *
     * @param string $savePath
     * @param string $file
     */
    public function saveFile(string $savePath, string $file)
    {
        if (!file_exists(dirname($savePath))) {
            mkdir(dirname($savePath), 0755, true);
        }

        $result = file_put_contents($savePath, $file);

        if ($result) {
            $this->info("file create success --{$savePath}");
            return true;
        }
        $this->error("file already exist --{$savePath}");
        return false;
    }
}
