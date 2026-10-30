<?php

namespace Wang9707\MakeTable\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Wang9707\MakeTable\MakeTableServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [MakeTableServiceProvider::class];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('database.default', 'testing');

        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // 另一个连接，用来验证按模型连接取表结构
        $app['config']->set('database.connections.other', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }
}
