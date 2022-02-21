<?php

namespace Wang9707\MakeTable\Services;


use App\Modelsasdf\Test;
use Illuminate\Database\Eloquent\Model;
use Wang9707\MakeTable\Eloquent\QueryFilter;
use Wang9707\MakeTable\Exceptions\TipsException;


class Service
{
    private static array $instances = [];

    /**
     * @var Model $model
     */
    public $model;

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
     * @param $request
     * @return mixed
     */
    public function getResourceList(QueryFilter $filter, $request)
    {
        $num = $request->input('num', 10);

        $data = $this->model::query()->filter($filter)->orderBy('id', 'desc')->paginate($num);

        return [
            'list'         => $data->items(),
            'total'        => $data->total(),
            'current_page' => $data->currentPage(),
            'num'          => $num,
        ];
    }

    /**
     * 获取单条数据
     *
     * @param int $id
     * @param string[] $columns
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection|Model|null
     */
    public function getById(int $id, $columns = ['*'])
    {
        return $this->model::query()->find($id);
    }

    /**
     * 创建数据
     *
     * @param array $attribute
     * @return mixed
     * @throws \Exception
     */
    public function create(array $attribute)
    {
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
     * 更新模型
     *
     * @param int $id
     * @param array $arrtibute
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection|Model|null
     */
    public function updateById(int $id, array $arrtibute)
    {
        try {
            $model = $this->model::query()->findOrFail($id);
            $model->fill($arrtibute);
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
     * @return bool
     * @throws TipsException
     */
    public function deleteById(int $id)
    {
        try {
            $model = $this->model::query()->findOrFail($id);
            $model->delete();
            return true;
        } catch (\Throwable $th) {
            $this->throw($th->getMessage());
        }
    }


    /**
     * 抛出异常
     *
     * @param null $responseMessage
     * @param null $responseCode
     * @return null
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
