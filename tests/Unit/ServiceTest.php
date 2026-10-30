<?php

namespace Wang9707\MakeTable\Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Wang9707\MakeTable\Exceptions\TipsException;
use Wang9707\MakeTable\Tests\Fixtures\EmptyFilter;
use Wang9707\MakeTable\Tests\Fixtures\Post;
use Wang9707\MakeTable\Tests\Fixtures\UppercaseNameFilter;
use Wang9707\MakeTable\Tests\Fixtures\User;
use Wang9707\MakeTable\Tests\Fixtures\UserService;
use Wang9707\MakeTable\Tests\TestCase;

class ServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->integer('status')->nullable()->default(1);
        });

        Schema::create('posts', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('title');
            $table->integer('price')->default(0);
        });

        foreach (['tom', 'jerry', 'spike'] as $index => $name) {
            User::query()->insert(['id' => $index + 1, 'name' => $name, 'status' => 1]);
        }

        Post::query()->insert([
            ['id' => 1, 'user_id' => 1, 'title' => 'a', 'price' => 10],
            ['id' => 2, 'user_id' => 1, 'title' => 'b', 'price' => 20],
            ['id' => 3, 'user_id' => 2, 'title' => 'c', 'price' => 5],
        ]);
    }

    private function list(array $params, array $with = []): array
    {
        $request = Request::create('/', 'GET', $params);

        return UserService::instance()->getResourceList(new EmptyFilter($request), $request, $with);
    }

    public function test_default_pagination()
    {
        $result = $this->list([]);

        $this->assertSame(3, $result['total']);
        $this->assertSame(10, $result['num']);
        $this->assertSame(1, $result['current_page']);
    }

    public function test_pagination_is_clamped()
    {
        $this->assertSame(100, $this->list(['num' => 500])['num']);
        $this->assertSame(1, $this->list(['num' => 0])['num']);
    }

    public function test_custom_pagination_fields()
    {
        config([
            'make-table.pagination.num_field'  => 'size',
            'make-table.pagination.page_field' => 'p',
        ]);

        $result = $this->list(['size' => 2, 'p' => 2]);

        $this->assertSame(2, $result['num']);
        $this->assertSame(2, $result['current_page']);
        $this->assertCount(1, $result['list']);
    }

    public function test_invalid_order_by_falls_back_to_default()
    {
        config(['make-table.order.column' => 'id', 'make-table.order.direction' => 'desc']);

        $result = $this->list(['order_by' => 'id; drop table users']);

        $this->assertSame('spike', $result['list'][0]->name);
    }

    public function test_orderable_whitelist()
    {
        $service = new class extends UserService {
            protected array $orderable = ['name'];
        };

        // status 不在白名单，回落到默认 id
        $request = Request::create('/', 'GET', ['order_by' => 'status', 'sort' => 'asc']);
        $result  = $service->getResourceList(new EmptyFilter($request), $request);
        $this->assertSame('tom', $result['list'][0]->name);

        // name 在白名单内，正常排序
        $request = Request::create('/', 'GET', ['order_by' => 'name', 'sort' => 'asc']);
        $result  = $service->getResourceList(new EmptyFilter($request), $request);
        $this->assertSame('jerry', $result['list'][0]->name);
    }

    public function test_resource_list_with_relations_and_queries()
    {
        $request = Request::create('/', 'GET', []);

        $result = UserService::instance()->getResourceList(
            new EmptyFilter($request),
            $request,
            ['posts'],
            fn ($query) => $query->withCount('posts')->where('name', 'tom')
        );

        $this->assertSame(1, $result['total']);
        $this->assertSame('tom', $result['list'][0]->name);
        $this->assertTrue($result['list'][0]->relationLoaded('posts'));
        $this->assertSame(2, (int) $result['list'][0]->posts_count);
    }

    public function test_get_by_id_with_relations()
    {
        $user = UserService::instance()->getById(1, ['posts'], fn ($query) => $query->withCount('posts'));

        $this->assertTrue($user->relationLoaded('posts'));
        $this->assertCount(2, $user->posts);
        $this->assertSame(2, (int) $user->posts_count);
    }

    public function test_get_by_id_with_aggregates()
    {
        $user = UserService::instance()->getById(1, [], fn ($query) => $query
            ->withSum('posts', 'price')
            ->withMax('posts', 'price')
            ->withMin('posts', 'price')
            ->withAvg('posts', 'price'));

        $this->assertSame(30, (int) $user->posts_sum_price);
        $this->assertSame(20, (int) $user->posts_max_price);
        $this->assertSame(10, (int) $user->posts_min_price);
        $this->assertSame(15.0, (float) $user->posts_avg_price);
    }

    public function test_get_by_id_with_wheres()
    {
        $service = UserService::instance();

        $user = $service->getById(1, [], fn ($query) => $query->where('status', 1));
        $this->assertSame('tom', $user->name);

        // 多个闭包依次作用于同一个查询
        $this->assertNull($service->getById(
            1,
            [],
            fn ($query) => $query->where('status', 1),
            fn ($query) => $query->where('name', 'jerry')
        ));

        $this->assertNull($service->getById(1, [], fn ($query) => $query->where('status', 999)));
    }

    public function test_get_by_id_where_can_filter_relation()
    {
        $user = UserService::instance()->getById(1, ['posts' => fn ($query) => $query->where('title', 'a')]);

        $this->assertCount(1, $user->posts);
        $this->assertSame('a', $user->posts->first()->title);
    }

    public function test_update_by_id_with_relations_and_queries()
    {
        $user = UserService::instance()->updateById(
            1,
            ['name' => 'tom2'],
            ['posts'],
            fn ($query) => $query->where('status', 1)
        );

        $this->assertSame('tom2', $user->fresh()->name);
        $this->assertTrue($user->relationLoaded('posts'));
    }

    public function test_update_by_id_where_not_match_throws()
    {
        $this->expectException(TipsException::class);

        UserService::instance()->updateById(1, ['name' => 'x'], [], fn ($query) => $query->where('status', 999));
    }

    public function test_delete_by_id_with_queries()
    {
        $this->assertTrue(
            UserService::instance()->deleteById(1, [], fn ($query) => $query->where('status', 1))
        );
        $this->assertNull(User::query()->find(1));
    }

    public function test_delete_by_id_where_not_match_throws()
    {
        $this->expectException(TipsException::class);

        UserService::instance()->deleteById(1, [], fn ($query) => $query->where('status', 999));
    }

    public function test_create_filters_null_values_by_default()
    {
        $user = UserService::instance()->create(['name' => 'tom', 'status' => null]);

        // status 为 null 被去掉，落库使用数据库默认值
        $this->assertSame(1, (int) $user->fresh()->status);
    }

    public function test_create_keeps_null_values_when_filter_disabled()
    {
        config(['make-table.create.filter' => false]);

        $user = UserService::instance()->create(['name' => 'tom', 'status' => null]);

        $this->assertNull($user->fresh()->status);
    }

    public function test_create_uses_custom_attribute_filter()
    {
        config(['make-table.create.filter' => UppercaseNameFilter::class]);

        $user = UserService::instance()->create(['name' => 'tom']);

        $this->assertSame('TOM', $user->fresh()->name);
    }

    public function test_create_rejects_invalid_attribute_filter()
    {
        config(['make-table.create.filter' => 'App\\Not\\Exists']);

        $this->expectException(InvalidArgumentException::class);

        UserService::instance()->create(['name' => 'tom']);
    }
}
