<?php

namespace Wang9707\MakeTable\Attributes;

use Attribute;

/**
 * 声明式过滤条件，作用在 QueryFilter 的 public 属性上
 *
 * class UserFilter extends QueryFilter
 * {
 *     #[Filter('title', 'like')]
 *     public ?string $name = null;
 * }
 *
 * 请求参数名默认取属性名的下划线形式（createdAt → created_at），可用 param 指定。
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class Filter
{
    /**
     * @param string|null $column   数据库列名，默认由属性名转成下划线形式
     * @param string $operator      操作符：= != > >= < <= like like_left like_right in not_in between null not_null
     * @param string|null $param    请求参数名，默认由属性名转成下划线形式
     */
    public function __construct(
        public ?string $column = null,
        public string $operator = '=',
        public ?string $param = null
    ) {
    }
}
