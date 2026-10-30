<?php

namespace Wang9707\MakeTable\Tests\Unit;

use Illuminate\Support\Facades\Schema;
use Wang9707\MakeTable\Support\Table;
use Wang9707\MakeTable\Tests\Fixtures\Member;
use Wang9707\MakeTable\Tests\Fixtures\User;
use Wang9707\MakeTable\Tests\TestCase;

class TableTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name', 50)->comment('姓名');
            $table->string('email')->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->timestamp('created_at')->nullable();
        });

        Schema::connection('other')->create('members', function ($table) {
            $table->increments('member_id');
            $table->string('nickname', 20)->nullable();
        });
    }

    public function test_columns_by_table_name()
    {
        $columns = Table::columns('users');

        $this->assertSame(['id', 'name', 'email', 'status', 'created_at'], array_column($columns, 'name'));

        $byName = collect($columns)->keyBy('name');

        $this->assertTrue($byName['id']['auto_increment']);
        $this->assertFalse($byName['name']['nullable']);
        $this->assertTrue($byName['email']['nullable']);
        $this->assertSame('integer', $byName['id']['type']);
        $this->assertSame('varchar', $byName['name']['type']);
    }

    public function test_columns_by_model_class_or_instance()
    {
        $this->assertSame(Table::columns('users'), Table::columns(User::class));
        $this->assertSame(Table::columns('users'), Table::columns(new User()));
    }

    public function test_model_uses_its_own_connection()
    {
        $columns = Table::columns(Member::class);

        $this->assertSame('member_id', $columns[0]['name']);
        $this->assertTrue(Table::hasTable(Member::class));

        // 默认连接上没有 members 表
        $this->assertFalse(Table::hasTable('members'));
    }

    public function test_unknown_table_returns_empty_array()
    {
        $this->assertSame([], Table::columns('not_exists'));
        $this->assertFalse(Table::hasTable('not_exists'));
    }
}
