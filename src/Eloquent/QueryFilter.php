<?php

namespace Wang9707\MakeTable\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

abstract class QueryFilter
{
    protected $request;
    protected $builder;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function apply(Builder $builder)
    {
        $this->builder = $builder;

        foreach ($this->filters() as $name => $value) {

            if (!$this->isFilterValue($value)) {
                continue;
            }

            $name = Str::camel($name);

            if (method_exists($this, $name)) {
                call_user_func_array([$this, $name], array_filter([$value]));
            }
        }
    }

    /**
     * 过滤空值查询
     *
     * @param $value
     * @return bool
     */
    public function isFilterValue($value)
    {
        return $value !== '' && $value !== null && !empty($value);
    }


    public function filters(): array
    {
        return $this->request->all();
    }
}
