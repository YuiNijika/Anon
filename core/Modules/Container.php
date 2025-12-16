<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 依赖注入容器
 * 支持单例、绑定、解析依赖
 */
class Anon_Container
{
    /**
     * @var Anon_Container 单例实例
     */
    private static $instance = null;

    /**
     * @var array 绑定容器
     */
    private $bindings = [];

    /**
     * @var array 单例实例缓存
     */
    private $instances = [];

    /**
     * @var array 别名映射
     */
    private $aliases = [];

    /**
     * 获取容器单例
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
     * 绑定接口或抽象类到具体实现
     * @param string $abstract 抽象类或接口名
     * @param mixed $concrete 具体实现（类名、闭包或实例）
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
     * @param string $abstract 抽象类或接口名
     * @param mixed $concrete 具体实现
     * @return void
     */
    public function singleton(string $abstract, $concrete): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * 注册实例
     * 直接绑定已创建的实例到容器中
     * @param string $abstract 抽象类或接口名
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
     * 解析依赖并创建实例
     * @param string $abstract 要解析的类名
     * @param array $parameters 构造函数参数（可选）
     * @return mixed
     * @throws RuntimeException
     */
    public function make(string $abstract, array $parameters = [])
    {
        // 检查别名
        $abstract = $this->aliases[$abstract] ?? $abstract;

        // 如果已有实例，直接返回
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // 获取绑定配置
        $binding = $this->bindings[$abstract] ?? null;

        // 如果绑定了具体实现
        if ($binding !== null) {
            $concrete = $binding['concrete'];
            $singleton = $binding['singleton'];

            // 如果是闭包，执行闭包
            if ($concrete instanceof Closure) {
                $instance = $concrete($this, $parameters);
            } else {
                // 如果是类名，递归解析
                $instance = $this->build($concrete, $parameters);
            }

            // 如果是单例，缓存实例
            if ($singleton) {
                $this->instances[$abstract] = $instance;
            }

            return $instance;
        }

        // 如果没有绑定，尝试直接构建
        return $this->build($abstract, $parameters);
    }

    /**
     * 构建类实例（自动解析依赖）
     * @param string $concrete 类名
     * @param array $parameters 手动提供的参数
     * @return object
     * @throws RuntimeException
     */
    private function build(string $concrete, array $parameters = []): object
    {
        // 如果类不存在，抛出异常
        if (!class_exists($concrete)) {
            throw new RuntimeException("类不存在: {$concrete}");
        }

        // 获取反射类
        $reflector = new ReflectionClass($concrete);

        // 检查是否可以实例化
        if (!$reflector->isInstantiable()) {
            throw new RuntimeException("类无法实例化: {$concrete}");
        }

        // 获取构造函数
        $constructor = $reflector->getConstructor();

        // 如果没有构造函数，直接创建实例
        if ($constructor === null) {
            return new $concrete();
        }

        // 解析构造函数参数
        $dependencies = $this->resolveDependencies($constructor->getParameters(), $parameters);

        // 创建实例
        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * 解析依赖参数
     * @param ReflectionParameter[] $parameters 参数列表
     * @param array $provided 手动提供的参数
     * @return array
     * @throws RuntimeException
     */
    private function resolveDependencies(array $parameters, array $provided = []): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();

            // 如果手动提供了参数，使用提供的参数
            if (isset($provided[$name])) {
                $dependencies[] = $provided[$name];
                continue;
            }

            // 如果参数有类型提示
            $type = $parameter->getType();
            if ($type !== null && !$type->isBuiltin()) {
                $typeName = $type->getName();
                // 尝试从容器解析
                try {
                    $dependencies[] = $this->make($typeName);
                } catch (RuntimeException $e) {
                    // 如果无法解析且有默认值，使用默认值
                    if ($parameter->isDefaultValueAvailable()) {
                        $dependencies[] = $parameter->getDefaultValue();
                    } else {
                        throw new RuntimeException("无法解析依赖: {$typeName}::\${$name}");
                    }
                }
            } else {
                // 基本类型或没有类型提示
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
     * 检查是否已绑定
     * @param string $abstract 类名
     * @return bool
     */
    public function bound(string $abstract): bool
    {
        $abstract = $this->aliases[$abstract] ?? $abstract;
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * 清空容器（主要用于测试）
     * @return void
     */
    public function flush(): void
    {
        $this->bindings = [];
        $this->instances = [];
        $this->aliases = [];
    }
}

