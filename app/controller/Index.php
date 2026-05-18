<?php

namespace Anon\Controller;

use Anon\Core\Facade\Validator;
use Anon\Core\Http\Request;
use Anon\Core\Http\Response;

class Index
{
    public function index(Request $request): Response
    {
        return Response::success([
            'message' => 'Welcome to Anon Framework Next',
            'method' => $request->method(),
            'uri' => $request->uri(),
            'routes' => [
                'GET /ping',
                'GET /hello/{name}',
                'GET /articles',
                'POST /articles',
            ],
        ], 'Skeleton Ready');
    }

    public function ping(): Response
    {
        return Response::success([
            'pong' => true,
            'timestamp' => time(),
        ], 'Ping Success');
    }

    public function hello(Request $request, string $name): Response
    {
        return Response::success([
            'message' => 'Hello, ' . $name,
            'name' => $request->route('name'),
            'from' => $request->input('from', 'anon'),
        ], 'Hello Success');
    }

    public function articles(): Response
    {
        return Response::success([
            [
                'id' => 1,
                'title' => 'Hello Anon',
                'content' => 'This is a simple skeleton example.',
            ],
            [
                'id' => 2,
                'title' => 'Build Your API',
                'content' => 'Replace this mock data with your own business logic.',
            ],
        ], 'Articles Success');
    }

    public function storeArticle(Request $request): Response
    {
        $data = $request->input();
        $validator = Validator::make($data, [
            'title' => 'required|max:100',
            'content' => 'required|max:500',
        ]);

        if ($validator->fails()) {
            return Response::error($validator->firstError(), 400, $validator->errors());
        }

        return Response::success([
            'id' => random_int(1000, 9999),
            'title' => $data['title'],
            'content' => $data['content'],
        ], 'Article Created');
    }
}
