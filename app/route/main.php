<?php

use Anon\Core\Facade\Route;
use Anon\Core\Facade\Log;
use Anon\Core\Facade\DB;
use Anon\Core\Facade\Cache;
use Anon\Core\Facade\Session;
use Anon\Core\Facade\Validator;
use Anon\Core\Facade\Event;
use Anon\Core\Facade\Auth;
use Anon\Core\Facade\Storage;
use Anon\Core\Exception\HttpException;
use Anon\Core\Http\Request;
use Anon\Core\Http\Response;
use Anon\Middleware\AuthMiddleware;

// 动态参数与依赖注入测试路由
Route::get('/user/{id}', function (Request $request, string $id) {
    return Response::success([
        'id'          => $id,
        'route_param' => $request->route('id'),
        'query_param' => $request->input('ref'),
    ], 'Dynamic Route Success');
});
Route::get('/', function (Request $request) {
    // 测试日志写入
    Log::info('Visit homepage', 'access');
    Log::debug(['method' => $request->method(), 'uri' => $request->uri()], 'access');

    return Response::success([
        'message' => 'Welcome to Anon Framework Next!',
        'method'  => $request->method(),
        'uri'     => $request->uri()
    ]);
});

// 单一路由附加中间件
Route::get('/profile', function () {
    return ['user_id' => 1001, 'name' => 'Anon User'];
})->middleware(AuthMiddleware::class);

// 路由组测试
Route::group('/api/v1', function ($route) {
    // 这将匹配 /api/v1/users
    $route->get('/users', function () {
        return ['user1', 'user2', 'user3'];
    });

    // 这将匹配 /api/v1/ping
    $route->get('/ping', function () {
        return 'pong';
    });
});

// 嵌套路由组与中间件 (使用属性数组绑定)
Route::group(['prefix' => '/admin', 'middleware' => AuthMiddleware::class], function ($route) {
    
    // 这将匹配 /admin
    $route->get('/', function () {
        return 'Admin Home';
    });

    // 这将匹配 /admin/dashboard
    $route->get('/dashboard', function () {
        return 'Admin Dashboard';
    });

    $route->group('/settings', function ($route) {
        // 这将匹配 /admin/settings/system
        $route->get('/system', function () {
            return 'System Settings';
        });
    });

});

// 数据库测试路由
Route::get('/db/test', function () {
    try {
        // 尝试查询数据库版本，验证连接是否成功
        $result = DB::select('SELECT VERSION() as version');
        return Response::success(['db_version' => $result[0]['version'] ?? 'unknown'], 'Database Connected');
    } catch (\Exception $e) {
        return Response::error('Database Connection Failed: ' . $e->getMessage(), 500);
    }
});

// 数据库高级查询测试路由
Route::get('/db/advanced', function () {
    try {
        // 演示更复杂的链式调用：连表、In、Null 判断、排序和分组
        $query = DB::table('users')
            ->select(['users.id', 'users.name', 'roles.role_name'])
            ->leftJoin('roles', 'users.role_id', '=', 'roles.id')
            ->whereIn('users.status', [1, 2])
            ->whereNotNull('users.email')
            ->orWhere('users.name', 'LIKE', '%admin%')
            ->orderBy('users.id', 'DESC')
            ->limit(5);

        return Response::success([
            'count' => DB::table('users')->count(),
            'exists' => DB::table('users')->where('id', 1)->exists(),
            'data' => $query->get()
        ]);
    } catch (\Exception $e) {
        return Response::error($e->getMessage(), 500);
    }
});

// Cache 测试路由
Route::get('/cache/test', function () {
    try {
        $driver = \Anon\Core\Facade\Env::get('CACHE_DRIVER', 'file');
        
        // 测试写入
        Cache::set('test_key', ['time' => time(), 'msg' => 'Hello Cache'], 60);
        
        // 测试读取
        $data = Cache::get('test_key');
        
        // 测试存在
        $has = Cache::has('test_key');
        
        return Response::success([
            'driver' => $driver,
            'data' => $data,
            'has' => $has
        ], 'Cache Test Success');
    } catch (\Exception $e) {
        return Response::error('Cache Test Failed: ' . $e->getMessage(), 500);
    }
});

// Session 测试路由
Route::get('/session/test', function () {
    // 增加计数器
    $count = Session::get('test_count', 0);
    Session::set('test_count', $count + 1);

    return Response::success([
        'session_id' => Session::getId(),
        'count'      => Session::get('test_count'),
        'has_count'  => Session::has('test_count')
    ], 'Session Test Success');
});

// 验证器测试路由
Route::post('/validator/test', function (Request $request) {
    $data = $request->post();
    
    $validator = Validator::make($data, [
        'username' => 'required|max:20',
        'email'    => 'required|email',
        'age'      => 'numeric|min:18'
    ]);

    if ($validator->fails()) {
        // 直接抛出 HTTPException，由 Handler 统一渲染
        throw new HttpException(400, $validator->firstError(), $validator->errors());
    }

    return Response::success($data, 'Validation Passed');
});

// 事件测试路由
Route::get('/event/test', function () {
    // 注册一个闭包监听器
    Event::listen('user.login', function ($user) {
        Log::info("User login event triggered for user: " . $user['name'], 'event');
        // 返回的数据会被收集
        return "Logged: " . $user['name'];
    });

    // 触发事件
    $responses = Event::dispatch('user.login', ['name' => 'Anon Admin']);

    return Response::success([
        'event_responses' => $responses
    ], 'Event Test Success');
});

// 分页测试路由
Route::get('/db/paginate', function () {
    try {
        $result = DB::table('users')->paginate(2, 1);
        return Response::success($result);
    } catch (\Exception $e) {
        return Response::error($e->getMessage(), 500);
    }
});

// Model 测试路由
Route::get('/model/test', function () {
    try {
        $modelClass = new class extends \Anon\Core\Database\Model {
            protected string $table = 'users';
        };
        return Response::success([
            'all' => $modelClass::all(),
            'find' => $modelClass::find(1)
        ]);
    } catch (\Exception $e) {
        return Response::error($e->getMessage(), 500);
    }
});

// Auth 测试路由
Route::post('/auth/login', function () {
    $token = Auth::login(['id' => 1, 'name' => 'admin']);
    return Response::success(['token' => $token], 'Login Success');
});

Route::get('/auth/user', function () {
    if (Auth::check()) {
        return Response::success(Auth::user(), 'Auth Success');
    }
    return Response::error('Unauthorized', 401);
});

// Storage 测试路由
Route::get('/storage/test', function () {
    Storage::put('test.txt', 'Hello Storage!');
    return Response::success([
        'exists' => Storage::exists('test.txt'),
        'content' => Storage::get('test.txt'),
        'url' => Storage::url('test.txt')
    ]);
});
