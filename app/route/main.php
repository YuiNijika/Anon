<?php

use Anon\Core\Facade\Route;
use Anon\Controller\Index;

// 默认骨架只保留最基础的入门示例，便于像 ThinkPHP 一样开箱即改。
Route::get('/', [Index::class, 'index']);
Route::get('/ping', [Index::class, 'ping']);
Route::get('/hello/{name}', [Index::class, 'hello']);
Route::get('/articles', [Index::class, 'articles']);
Route::post('/articles', [Index::class, 'storeArticle']);
