<?php

namespace Wang9707\MakeTable\Eloquent\Traits;
use DateTimeInterface;

trait EloquentDate
{

    /**
     * 为 array / JSON 序列化准备日期格式
     *
     * @param  \DateTimeInterface  $date
     * @return string
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
