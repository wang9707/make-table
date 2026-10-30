<?php

namespace Wang9707\MakeTable\Contracts;

interface AttributeFilter
{
    /**
     * 处理创建时提交的字段
     *
     * @param array $attributes
     * @return array
     */
    public function filter(array $attributes): array;
}
