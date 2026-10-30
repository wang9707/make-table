<?php

namespace Wang9707\MakeTable\Tests\Unit;

use BadMethodCallException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Wang9707\MakeTable\Eloquent\QueryFilter;
use Wang9707\MakeTable\Tests\Fixtures\EmptyFilter;
use Wang9707\MakeTable\Tests\Fixtures\User;
use Wang9707\MakeTable\Tests\Fixtures\UserAttributeFilter;
use Wang9707\MakeTable\Tests\Fixtures\UserMethodFilter;
use Wang9707\MakeTable\Tests\Fixtures\UserOperatorFilter;
use Wang9707\MakeTable\Tests\TestCase;

class QueryFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->integer('status')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
    }

    /**
     * @param class-string<QueryFilter> $filterClass
     */
    private function build(string $filterClass, array $params): Builder
    {
        $request = Request::create('/', 'GET', $params);

        $query = User::query();
        (new $filterClass($request))->apply($query);

        return $query;
    }

    public function test_auto_where_filters_existing_columns()
    {
        $query = $this->build(EmptyFilter::class, ['name' => 'tom', 'status' => 1, 'unknown' => 'x']);

        $this->assertSame('select * from "users" where "name" = ? and "status" = ?', $query->toSql());
        $this->assertSame(['tom', 1], $query->getBindings());
    }

    public function test_auto_where_can_be_disabled()
    {
        config(['make-table.query.auto_where' => false]);

        $query = $this->build(EmptyFilter::class, ['name' => 'tom']);

        $this->assertSame('select * from "users"', $query->toSql());
    }

    public function test_reserved_params_are_ignored()
    {
        $query = $this->build(EmptyFilter::class, [
            'num'      => 5,
            'page'     => 2,
            'order_by' => 'name',
            'sort'     => 'asc',
        ]);

        $this->assertSame('select * from "users"', $query->toSql());
    }

    public function test_zero_value_is_filtered()
    {
        $query = $this->build(EmptyFilter::class, ['status' => '0']);

        $this->assertSame('select * from "users" where "status" = ?', $query->toSql());
        $this->assertSame(['0'], $query->getBindings());
    }

    public function test_method_filter_has_priority()
    {
        $query = $this->build(UserMethodFilter::class, ['name' => 'tom']);

        $this->assertSame('select * from "users" where "name" like ?', $query->toSql());
        $this->assertSame(['%tom%'], $query->getBindings());
    }

    public function test_call_maps_to_column()
    {
        $request = Request::create('/');
        $filter  = new EmptyFilter($request);

        $query = User::query();
        $filter->apply($query);
        $filter->name('tom');

        $this->assertSame('select * from "users" where "name" = ?', $query->toSql());
    }

    public function test_call_unknown_column_throws()
    {
        $filter = new EmptyFilter(Request::create('/'));
        $filter->apply(User::query());

        $this->expectException(BadMethodCallException::class);

        $filter->notAColumn('x');
    }

    public function test_attribute_filter()
    {
        $query = $this->build(UserAttributeFilter::class, [
            'name'   => 'to',
            'status' => 1,
            'q'      => 'tm',
        ]);

        $this->assertSame(
            'select * from "users" where "name" like ? and "status" = ? and "name" like ?',
            $query->toSql()
        );
        $this->assertSame(['%to%', 1, '%tm'], $query->getBindings());
    }

    public function test_attribute_operators()
    {
        $query = $this->build(UserOperatorFilter::class, [
            'statuses'     => '1,2',
            'date_range'   => '2024-01-01,2024-01-31',
            'has_date'     => '1',
            'only_deleted' => '1',
        ]);

        $this->assertSame(
            'select * from "users" where "status" in (?, ?) and "created_at" between ? and ?'
            . ' and "created_at" is not null and "deleted_at" is null',
            $query->toSql()
        );
        $this->assertSame(['1', '2', '2024-01-01', '2024-01-31'], $query->getBindings());
    }

    public function test_attribute_filter_skips_empty_value()
    {
        $query = $this->build(UserAttributeFilter::class, ['name' => '']);

        $this->assertSame('select * from "users"', $query->toSql());
    }
}
