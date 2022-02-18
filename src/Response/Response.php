<?php

namespace Wang9707\MakeTable\Response;


class Response
{
    /**
     * 成功返回
     *
     * @param array $data
     * @param string $message
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     */
    public static function success($data = [], $message = '')
    {
        return response([
            'code'    => 200,
            'message' => $message,
            'data'    => $data,
        ]);
    }

    /**
     * 错误返回
     *
     * @param string $message
     * @param array $data
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     */
    public static function error($message = '', $data = [])
    {
        return response([
            'code'    => 400,
            'message' => $message,
            'data'    => $data,
        ]);
    }

    /**
     * 自定义返回
     *
     * @param $code
     * @param $message
     * @param $data
     * @param $status
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     */
    public static function response($code, $message, $data, $status)
    {
        return response([
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }
}
