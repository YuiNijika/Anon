<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 依赖注入容器
 */
class Anon_System_Container
{
    /**
     * @var Anon_Container 单例实例
     */
    private static $instance = null;

    /**
     * @var array 绑定列表
     */
    private $bindings = [];

    /**
     * @var array 实例缓存
     */
    private $instances = [];

    /**
     * @var array 别名映射
     */
    private $aliases = [];

    /**
     * @var array 反射缓存
     */
    private static $reflectionCache = [];

    /**
     * 获取实例
     * @return Anon_Container
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 绑定实现
     * @param string $abstract 抽象类名
     * @param mixed $concrete 具体实现
     * @param bool $singleton 是否单例
     * @return void
     */
    public function bind(string $abstract, $concrete, bool $singleton = false): void
    {
        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'singleton' => $singleton
        ];
    }

    /**
     * 绑定单例
     * @param string $abstract 抽象类名
     * @param mixed $concrete 具体实现
     * @return void
     */
    public function singleton(string $abstract, $concrete): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * 注册实例
     * @param string $abstract 抽象类名
     * @param object $instance 实例对象
     * @return void
     */
    public function instance(string $abstract, object $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    /**
     * 设置别名
     * @param string $alias 别名
     * @param string $abstract 原始类名
     * @return void
     */
    public function alias(string $alias, string $abstract): void
    {
        $this->aliases[$alias] = $abstract;
    }

    /**
     * 解析依赖
     * @param string $abstract 类名
     * @param array $parameters 参数
     * @return mixed
     * @throws RuntimeException
     */
    public function make(string $abstract, array $parameters = [])
    {
        $abstract = $this->aliases[$abstract] ?? $abstract;

        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $binding = $this->bindings[$abstract] ?? null;

        if ($binding !== null) {
            $concrete = $binding['concrete'];
            $singleton = $binding['singleton'];

            if ($concrete instanceof Closure) {
                $instance = $concrete($this, $parameters);
            } else {
                $instance = $this->build($concrete, $parameters);
            }

            if ($singleton) {
                $this->instances[$abstract] = $instance;
            }

            return $instance;
        }

        return $this->build($abstract, $parameters);
    }

    /**
     * 自动构建
     * @param string $concrete 类名
     * @param array $parameters 参数
     * @return object
     * @throws RuntimeException
     */
    private function build(string $concrete, array $parameters = []): object
    {
        if (!class_exists($concrete)) {
            throw new RuntimeException("类不存在: {$concrete}");
        }

        if (!isset(self::$reflectionCache[$concrete])) {
            self::$reflectionCache[$concrete] = new ReflectionClass($concrete);
        }
        $reflector = self::$reflectionCache[$concrete];

        if (!$reflector->isInstantiable()) {
            throw new RuntimeException("类无法实例化: {$concrete}");
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            return new $concrete();
        }

        $dependencies = $this->resolveDependencies($constructor->getParameters(), $parameters);

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * 解析参数
     * @param ReflectionParameter[] $parameters 参数列表
     * @param array $provided 提供参数
     * @return array
     * @throws RuntimeException
     */
    private function resolveDependencies(array $parameters, array $provided = []): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();

            if (isset($provided[$name])) {
                $dependencies[] = $provided[$name];
                continue;
            }

            $type = $parameter->getType();
            if ($type !== null && !$type->isBuiltin()) {
                $typeName = $type->getName();
                try {
                    $dependencies[] = $this->make($typeName);
                } catch (RuntimeException $e) {
                    if ($parameter->isDefaultValueAvailable()) {
                        $dependencies[] = $parameter->getDefaultValue();
                    } else {
                        throw new RuntimeException("无法解析依赖: {$typeName}::\${$name}");
                    }
                }
            } else {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new RuntimeException("无法解析参数: \${$name}");
                }
            }
        }

        return $dependencies;
    }

    /**
     * 检查绑定
     * @param string $abstract 类名
     * @return bool
     */
    public function bound(string $abstract): bool
    {
        $abstract = $this->aliases[$abstract] ?? $abstract;
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * 清空容器
     * @return void
     */
    public function flush(): void
    {
        $this->bindings = [];
        $this->instances = [];
        $this->aliases = [];
    }
}

