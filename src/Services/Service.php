<?php

namespace Wang9707\MakeTable\Services;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Wang9707\MakeTable\Contracts\AttributeFilter;
use Wang9707\MakeTable\Eloquent\QueryFilter;
use Wang9707\MakeTable\Exceptions\TipsException;

/**
 * Service 基类
 *
 * 子类通过 $model 指定模型，并用模板参数标注具体模型，IDE / PHPStan 即可推断返回值类型：
 *
 * @template TModel of Model
 *
 * class UserService extends Service
 * {
 *     public $model = \App\Models\User::class;
 * }
 *
 * @extends Service<\App\Models\User>
 */
class Service
{
    private static array $instances = [];

    /**
     * 模型类名
     *
     * @var class-string<TModel>
     */
    public $model;

    /**
     * 允许用于排序的字段，留空时只校验字段格式
     *
     * @var array
     */
    protected array $orderable = [];

    /**
     * 获取当前 Service 的单例
     *
     * @return static
     */
    public static function instance(): static
    {
        $name = static::class;
        if (!isset(self::$instances[$name])) {
            self::$instances[$name] = new $name();
        }
        return self::$instances[$name];
    }

    /**
     * 获取分页列表
     *
     * @param QueryFilter $filter
     * @param Request $request
     * @param array $with
     * @param Closure ...$queries 附加查询，如 fn ($query) => $query->withCount('orders')
     * @return array{list: array<int, TModel>, total: int, current_page: int, num: int}
     */
    public function getResourceList(QueryFilter $filter, Request $request, array $with = [], Closure ...$queries)
    {
        [$num, $pageField, $page] = $this->resolvePagination($request);
        [$orderBy, $sort]         = $this->resolveOrder($request);

        $data = $this->buildQuery($with, $queries)
            ->filter($filter)
            ->orderBy($orderBy, $sort)
            ->paginate($num, ['*'], $pageField, $page);

        return [
            'list'         => $data->items(),
            'total'        => $data->total(),
            'current_page' => $data->currentPage(),
            'num'          => $num,
        ];
    }

    /**
     * 解析分页参数
     *
     * @param Request $request
     * @return array{0: int, 1: string, 2: int} [每页条数, 页码字段名, 当前页]
     */
    protected function resolvePagination(Request $request): array
    {
        $numField  = (string) config('make-table.pagination.num_field', 'num');
        $pageField = (string) config('make-table.pagination.page_field', 'page');

        $num = (int) $request->input($numField, config('make-table.pagination.default', 10));
        $num = max(1, min($num, (int) config('make-table.pagination.max', 100)));

        $page = max(1, (int) $request->input($pageField, 1));

        return [$num, $pageField, $page];
    }

    /**
     * 解析排序参数
     *
     * @param Request $request
     * @return array{0: string, 1: string} [排序字段, 排序方向]
     */
    protected function resolveOrder(Request $request): array
    {
        return [
            $this->resolveOrderBy($request->input(config('make-table.order.column_field', 'order_by'))),
            $this->resolveSort($request->input(config('make-table.order.direction_field', 'sort'))),
        ];
    }

    /**
     * 解析排序字段，避免请求参数直接拼进 SQL
     *
     * @param mixed $column
     * @return string
     */
    protected function resolveOrderBy($column): string
    {
        $default = (string) config('make-table.order.column', 'id');

        if (!is_string($column) || $column === '') {
            return $default;
        }

        if (!empty($this->orderable)) {
            return in_array($column, $this->orderable, true) ? $column : $default;
        }

        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) ? $column : $default;
    }

    /**
     * 解析排序方向
     *
     * @param mixed $direction
     * @return string
     */
    protected function resolveSort($direction): string
    {
        $direction = strtolower((string) $direction);

        if ($direction === 'asc' || $direction === 'desc') {
            return $direction;
        }

        return strtolower((string) config('make-table.order.direction', 'desc')) === 'asc' ? 'asc' : 'desc';
    }

    /**
     * 获取模型实例
     *
     * @return Model
     */
    protected function getModel(): Model
    {
        return new $this->model;
    }

    /**
     * 构造查询
     *
     * @param array $with           预加载关联，如 ['profile', 'roles']
     * @param Closure[] $queries    每个闭包拿到查询构造器，可继续 with / withCount / withSum / where 等
     * @return Builder
     */
    protected function buildQuery(array $with = [], array $queries = []): Builder
    {
        $query = $this->getModel()->newQuery();

        if (!empty($with)) {
            $query->with($with);
        }

        foreach ($queries as $callback) {
            $callback($query);
        }

        return $query;
    }

    /**
     * 获取单条数据
     *
     * @param int $id
     * @param array $with
     * @param Closure ...$queries 附加查询，如 fn ($query) => $query->withCount('orders')->where('status', 1)
     * @return TModel|null
     */
    public function getById(int $id, array $with = [], Closure ...$queries)
    {
        return $this->buildQuery($with, $queries)->find($id);
    }

    /**
     * 创建数据
     *
     * @param array $attribute
     * @return TModel
     * @throws TipsException
     */
    public function create(array $attribute)
    {
        $attribute = $this->filterAttributes($attribute);

        try {
            $model = new $this->model;
            $model->fill($attribute);
            $model->save();
            return $model;
        } catch (\Throwable $th) {
            $this->throw($th->getMessage());
        }
    }

    /**
     * 按 make-table.create.filter 配置处理提交字段
     *
     * true    去掉值为 null 的字段，让数据库默认值生效
     * false   原样提交
     * 类名    实现 AttributeFilter 的类，需在容器中可解析
     *
     * @param array $attributes
     * @return array
     */
    protected function filterAttributes(array $attributes): array
    {
        $filter = config('make-table.create.filter', true);

        if ($filter === true) {
            return array_filter($attributes, static fn ($value) => $value !== null);
        }

        if (is_string($filter) && $filter !== '') {
            if (!class_exists($filter)) {
                throw new InvalidArgumentException("make-table.create.filter class not found: {$filter}");
            }

            $instance = app($filter);

            if (!$instance instanceof AttributeFilter) {
                throw new InvalidArgumentException(
                    'make-table.create.filter must implement ' . AttributeFilter::class . ": {$filter}"
                );
            }

            return $instance->filter($attributes);
        }

        return $attributes;
    }

    /**
     * 更新模型
     *
     * @param int $id
     * @param array $attribute
     * @param array $with
     * @param Closure ...$queries
     * @return TModel
     * @throws TipsException
     */
    public function updateById(int $id, array $attribute, array $with = [], Closure ...$queries)
    {
        try {
            $model = $this->buildQuery($with, $queries)->findOrFail($id);
            $model->fill($attribute);
            $model->save();
            return $model;
        } catch (\Throwable $th) {
            $this->throw($th->getMessage());
        }
    }

    /**
     * 删除模型
     *
     * @param int $id
     * @param array $with
     * @param Closure ...$queries
     * @return bool
     * @throws TipsException
     */
    public function deleteById(int $id, array $with = [], Closure ...$queries)
    {
        try {
            $model = $this->buildQuery($with, $queries)->findOrFail($id);
            $model->delete();
            return true;
        } catch (\Throwable $th) {
            $this->throw($th->getMessage());
        }
    }

    /**
     * 抛出异常
     *
     * @param string|null $responseMessage
     * @param int|null $responseCode
     * @return never
     * @throws TipsException
     */
    protected function throw($responseMessage = null, $responseCode = null)
    {
        // 如果没有传入错误代码，则错误代码为5001
        $responseCode = $responseCode ?? 5001;

        // 如果没传入错误信息
        $responseMessage = $responseMessage ?? trans('system.error');

        // 抛出异常
        throw new TipsException($responseMessage, $responseCode);
    }
}
