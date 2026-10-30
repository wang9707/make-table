<?php

namespace Wang9707\MakeTable\Eloquent;

use BadMethodCallException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Wang9707\MakeTable\Attributes\Filter;

abstract class QueryFilter
{
    protected $request;
    protected $builder;

    /**
     * 已经解析过的过滤方法，按类缓存
     *
     * @var array
     */
    protected static array $filterMethods = [];

    /**
     * 表的列名缓存，按 连接.表名 缓存
     *
     * @var array
     */
    protected static array $columnCache = [];

    /**
     * 解析后的 #[Filter] 注解，按类缓存
     *
     * @var array
     */
    protected static array $attributeFilters = [];

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * 把过滤条件应用到查询上
     *
     * 依次处理：#[Filter] 注解 → 子类定义的过滤方法 → 同名列自动 where
     *
     * @param Builder $builder
     * @return Builder
     */
    public function apply(Builder $builder)
    {
        $this->builder = $builder;

        // 注解声明的过滤条件
        $handled = $this->applyAttributeFilters();

        foreach ($this->filters() as $name => $value) {

            if (!$this->isFilterValue($value)) {
                continue;
            }

            // 已被注解处理过，或属于分页 / 排序参数
            if (in_array($name, $handled, true) || in_array($name, $this->reservedParams(), true)) {
                continue;
            }

            $method = Str::camel($name);

            if ($this->isFilterMethod($method)) {
                $this->{$method}($value);
            } elseif ($this->autoWhere()) {
                // 没有对应的过滤方法时，按同名的数据库列自动 where，列不存在则忽略
                $this->whereColumn($method, $value);
            }
        }

        return $builder;
    }

    /**
     * 处理属性上的 #[Filter] 注解，返回已处理的请求参数名
     *
     * @return array
     */
    protected function applyAttributeFilters(): array
    {
        $handled = [];

        foreach ($this->attributeFilters() as $item) {
            $value = $this->request->input($item['param']);

            if (!$this->isFilterValue($value)) {
                continue;
            }

            $this->filterByAttribute($item['column'], $item['operator'], $value);

            $handled[] = $item['param'];
        }

        return $handled;
    }

    /**
     * 解析类上的 #[Filter] 注解，按类缓存，避免每次请求都走反射
     *
     * 注解参数只能是编译期常量，缓存是安全的。
     *
     * @return array
     */
    protected function attributeFilters(): array
    {
        $class = static::class;

        if (!isset(static::$attributeFilters[$class])) {
            $items = [];

            foreach ((new ReflectionClass($this))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                if ($property->isStatic()) {
                    continue;
                }

                foreach ($property->getAttributes(Filter::class) as $attribute) {
                    /** @var Filter $filter */
                    $filter = $attribute->newInstance();

                    $items[] = [
                        'column'   => $filter->column ?? Str::snake($property->getName()),
                        'param'    => $filter->param ?? Str::snake($property->getName()),
                        'operator' => strtolower($filter->operator),
                    ];
                }
            }

            static::$attributeFilters[$class] = $items;
        }

        return static::$attributeFilters[$class];
    }

    /**
     * 按注解生成 where 条件
     *
     * @param string $column
     * @param string $operator
     * @param mixed $value
     * @return void
     */
    protected function filterByAttribute(string $column, string $operator, $value): void
    {
        switch (strtolower($operator)) {
            case 'like':
                $this->builder->where($column, 'like', "%{$value}%");
                break;
            case 'like_left':
                $this->builder->where($column, 'like', "%{$value}");
                break;
            case 'like_right':
                $this->builder->where($column, 'like', "{$value}%");
                break;
            case 'in':
                $this->builder->whereIn($column, $this->toArray($value));
                break;
            case 'not_in':
                $this->builder->whereNotIn($column, $this->toArray($value));
                break;
            case 'between':
                $this->builder->whereBetween($column, $this->toArray($value));
                break;
            case 'null':
                $this->builder->whereNull($column);
                break;
            case 'not_null':
                $this->builder->whereNotNull($column);
                break;
            default:
                $this->builder->where($column, $operator, $value);
        }
    }

    /**
     * 把逗号分隔的字符串或数组统一成数组
     *
     * @param mixed $value
     * @return array
     */
    protected function toArray($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        return array_values(array_filter(explode(',', (string) $value), fn ($item) => $item !== ''));
    }

    /**
     * 请求参数没有对应方法时，默认按同名的数据库列过滤
     * 可通过 make-table.query.auto_where 关闭
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (!$this->autoWhere() || !$this->whereColumn($method, $parameters[0] ?? null)) {
            throw new BadMethodCallException(sprintf('Call to undefined method %s::%s()', static::class, $method));
        }

        return $this->builder;
    }

    /**
     * 是否允许按数据库列自动过滤
     *
     * @return bool
     */
    protected function autoWhere(): bool
    {
        return (bool) config('make-table.query.auto_where', true);
    }

    /**
     * 按列过滤，列不存在时返回 false
     *
     * @param string $method
     * @param mixed $value
     * @return bool
     */
    protected function whereColumn(string $method, $value): bool
    {
        if (!$this->builder) {
            return false;
        }

        $column = Str::snake($method);

        if (!$this->hasColumn($column)) {
            return false;
        }

        $this->builder->where($column, $value);

        return true;
    }

    /**
     * 判断表的列是否存在
     *
     * @param string $column
     * @return bool
     */
    protected function hasColumn(string $column): bool
    {
        $model      = $this->builder->getModel();
        $connection = $model->getConnection();
        $key        = $connection->getName() . '.' . $model->getTable();

        if (!isset(static::$columnCache[$key])) {
            static::$columnCache[$key] = array_map(
                'strtolower',
                $connection->getSchemaBuilder()->getColumnListing($model->getTable())
            );
        }

        return in_array(strtolower($column), static::$columnCache[$key], true);
    }

    /**
     * 保留参数，不参与过滤
     *
     * @return array
     */
    protected function reservedParams(): array
    {
        return [
            config('make-table.pagination.num_field', 'num'),
            config('make-table.pagination.page_field', 'page'),
            config('make-table.order.column_field', 'order_by'),
            config('make-table.order.direction_field', 'sort'),
        ];
    }

    /**
     * 只允许调用子类中定义的过滤方法，
     * 避免请求参数触发 apply() 等基类方法
     *
     * @param string $method
     * @return bool
     */
    protected function isFilterMethod(string $method): bool
    {
        $class = static::class;

        if (!isset(static::$filterMethods[$class])) {
            $methods = [];

            foreach ((new ReflectionClass($this))->getMethods(ReflectionMethod::IS_PUBLIC) as $item) {
                if ($item->isStatic()
                    || $item->getDeclaringClass()->getName() === self::class
                    || $item->getNumberOfRequiredParameters() > 1
                    || Str::startsWith($item->getName(), '__')) {
                    continue;
                }

                $methods[$item->getName()] = true;
            }

            static::$filterMethods[$class] = $methods;
        }

        return isset(static::$filterMethods[$class][$method]);
    }

    /**
     * 过滤空值查询
     *
     * @param $value
     * @return bool
     */
    public function isFilterValue($value)
    {
        return $value !== '' && $value !== null && $value !== [];
    }

    public function filters(): array
    {
        return $this->request->all();
    }
}
