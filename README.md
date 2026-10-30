# make-table

Laravel 根据数据库表生成 CRUD 代码（Model / Request / Controller / Service / Filter）。

## 安装

```bash
composer require wang9707/make-table

# 可选：发布配置文件
php artisan vendor:publish --tag=make-table
```

## 使用

```bash
# 一次生成 model + request + controller + service + filter
php artisan wang:make:all users

# 单独生成
php artisan wang:make:model users
php artisan wang:make:request users
php artisan wang:make:controller users
php artisan wang:make:service users
php artisan wang:make:filter users

# 只查看
php artisan wang:show:fillable users
php artisan wang:show:annotate users
```

生成的文件不会覆盖已存在的文件。表名会转成 StudlyCase，例如 `user_profiles` → `UserProfiles`。

命令参数也可以直接传模型类，此时会使用模型自己的 `$table` 和数据库连接（适合分库 / 多连接场景）：

```bash
php artisan wang:make:all 'App\Models\User'
php artisan wang:show:annotate 'App\Models\User'
```

## 验证规则

`wang:make:request` 会按表结构生成 `FormRequest`：

- 列 `NOT NULL` → `required`，可空 → `nullable`
- 自增主键、`created_at` / `updated_at` / `deleted_at` 不生成规则
- 根据类型追加规则：整数 `integer`、小数 `numeric`、布尔 `boolean`、日期时间 `date`、
  `json` → `array`、`enum` → `in:...`、字符串 `string`（有长度时追加 `max:n`）
- `config('make-table.ignore_columns')` 中的列同样不生成规则

生成的 Controller 的 `store` / `update` 会直接使用这个 Request 类。

## 配置

`config/make-table.php`：

| 配置项 | 默认值 | 说明 |
| --- | --- | --- |
| `model.path` / `model.namespace` | `app/Models` / `App\Models` | 模型生成位置 |
| `service.path` / `service.namespace` | `app/Services` / `App\Services` | Service 生成位置 |
| `controller.path` / `controller.namespace` | `app/Http/Controllers` / `App\Http\Controllers` | 控制器生成位置 |
| `request.path` / `request.namespace` | `app/Http/Requests` / `App\Http\Requests` | 表单验证生成位置 |
| `filter.path` / `filter.namespace` | `app/Models/Filter` / `App\Models\Filter` | 过滤器生成位置 |
| `filter.methods` | `false` | 是否按数据库列生成过滤方法；关闭时生成空的 Filter 类，交给 `query.auto_where` 处理 |
| `ignore_columns` | `[]` | 不参与 fillable / 过滤器方法 / 验证规则的列，如 `['id']` |
| `create.filter` | `true` | `create()` 对提交字段的处理：`true` 去掉值为 null 的字段、`false` 原样提交、类名则使用实现 `AttributeFilter` 的类 |
| `query.auto_where` | `true` | 请求参数没有对应过滤方法时，是否按同名数据库列自动 where |
| `soft_deletes` | `true` | 为 true 且表中存在 `deleted_at` 时，模型才使用 `SoftDeletes` |
| `pagination.default` | `10` | 列表每页条数默认值 |
| `pagination.max` | `100` | 列表每页条数上限 |
| `pagination.num_field` | `num` | 每页条数的请求字段名 |
| `pagination.page_field` | `page` | 页码的请求字段名 |
| `order.column` | `id` | 列表默认排序字段 |
| `order.direction` | `desc` | 列表默认排序方向 |
| `order.column_field` | `order_by` | 排序字段的请求字段名 |
| `order.direction_field` | `sort` | 排序方向的请求字段名 |

## 查询过滤

`QueryFilter` 会按请求参数依次匹配：

1. 子类中定义的过滤方法（`user_name` → `userName()`）
2. `query.auto_where` 开启时，按同名列自动 `where`；列不存在则忽略
3. 分页 / 排序参数会被跳过，不参与过滤

`filter.methods` 默认关闭，所以生成的 Filter 类是空的——常见场景直接靠自动 where 即可。
需要 `like`、区间等条件时，打开 `filter.methods` 生成基础方法，或在 Filter 类里手动补充方法。

## 生成的列表接口

`Service::getResourceList(QueryFilter $filter, Request $request, array $with = [], Closure ...$queries)`
读取以下查询参数（字段名可通过配置修改）：

- `num`：每页条数，受 `pagination.max` 限制
- `page`：页码
- `order_by`：排序字段，只允许合法字段名；Service 可通过 `$orderable` 白名单进一步限制
- `sort`：`asc` / `desc`

和 `getById` 一样，`with` 之后可以继续挂闭包：

```php
$service->getResourceList($filter, $request, ['profile'], fn ($q) => $q->withCount('orders'));
```

返回 `['list' => [...], 'total' => 0, 'current_page' => 1, 'num' => 10]`。

## 目录结构

```
make-table/
├── config/make-table.php        配置文件
├── stubs/                       代码模板
├── src/
│   ├── Attributes/              #[Filter] 过滤注解
│   ├── Console/                 生成命令
│   ├── Eloquent/                Model / QueryFilter 基类
│   ├── Exceptions/
│   ├── Services/                Service 基类
│   ├── Support/                 Table / Response 工具类
│   └── MakeTableServiceProvider.php
└── tests/
    ├── Unit/
    ├── Feature/
    └── Fixtures/
```

## 测试

```bash
composer install
composer test
# 或
vendor/bin/phpunit
```

测试基于 Orchestra Testbench + SQLite 内存库，覆盖表结构读取、过滤（自动 where / 方法 / 注解）、分页排序和代码生成。

## Service 方法

`getById` / `updateById` / `deleteById` 只保留必要的参数，其余全部通过可变闭包传入。
闭包拿到的是查询构造器，`withCount`、`withSum`、`withMax`、`withMin`、`withAvg`、`where` 等都能直接用：

```php
// getById(int $id, array $with = [], Closure ...$queries)
$service->getById(1);
$service->getById(1, ['profile']);
$service->getById(1, ['profile'], fn ($q) => $q->withCount('orders')->where('status', 1));
$service->getById(1, [], fn ($q) => $q->withSum('orders', 'price')->withMax('orders', 'price'));

// updateById(int $id, array $attribute, array $with = [], Closure ...$queries)
$service->updateById(1, ['name' => 'tom'], ['profile'], fn ($q) => $q->where('status', 1));

// deleteById(int $id, array $with = [], Closure ...$queries)
$service->deleteById(1, [], fn ($q) => $q->where('status', 1));
```

多个闭包依次作用于同一个查询，条件可以封装成返回闭包的小函数复用：

```php
function whereOfficeId(int $officeId): Closure
{
    return static fn ($query) => $query->where('office_id', $officeId);
}

$service->getById($id, [], whereOfficeId($officeId));
```

条件不匹配时：`getById` 返回 `null`，`updateById` / `deleteById` 抛 `TipsException`。
关联也可以用闭包过滤：`$service->getById(1, ['posts' => fn ($q) => $q->where('title', 'a')]);`
闭包直接作用于查询本身，不会额外包一层括号；需要分组时在闭包里用 `$q->where(fn ($q) => ...)`。

## 数据库支持

`Table::columns($table)` / `Table::hasTable($table)` 同时接受表名或模型实例 / 模型类名；
传模型时使用模型自己的连接，不依赖默认连接：

```php
use Wang9707\MakeTable\Support\Table;

Table::columns('users');
Table::columns(app(\App\Models\User::class));
Table::columns(\App\Models\User::class);
```

- Laravel 10.30+ 使用连接上的 `getColumns()`，MySQL / PostgreSQL / SQLite 都能拿到类型、可空、注释
- 更低版本 Laravel + MySQL 走 `information_schema`，信息完整
- 更低版本 + 其他驱动只能拿到列名，此时类型按 `string` 处理、可空未知（按 `required` 生成规则）
- SQLite 不保存列注释和字符串长度，注释与 `max:n` 规则会缺失；MySQL / PostgreSQL 正常
