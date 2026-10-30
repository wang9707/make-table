<?php

namespace Wang9707\MakeTable\Eloquent\Traits;

use Illuminate\Database\Eloquent\Builder;
use Wang9707\MakeTable\Eloquent\QueryFilter;

/**
 * 查询过滤作用域
 *
 * 用法：
 *
 * User::query()->filter($filter)->get();
 * User::filter($filter)->paginate();
 *
 * @method static Builder filter(QueryFilter $filter)
 */
trait QueryFilterTrait
{
    /**
     * 应用过滤器
     *
     * @param Builder $query
     * @param QueryFilter $filter
     * @return Builder
     */
    public function scopeFilter(Builder $query, QueryFilter $filter): Builder
    {
        return $filter->apply($query);
    }
}
