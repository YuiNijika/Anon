<?php
namespace Anon\Modules\System\Plugin;

use Anon\Modules\Cms\Theme\OptionsProxy;
use Anon\Modules\Database\Database;
use Anon\Modules\Debug;
use Anon\Modules\System\Env;
use ReflectionClass;
use ReflectionMethod;
use Throwable;
use Anon\Modules\System\Plugin;
use Anon\Modules\Anon;
use Anon\Modules\Cms\Cms;
use Anon\Widgets\Cms\User;
use Anon\Widgets\Cms\ThemeHelper;
use Anon\Modules\Cms\Theme\Theme;

if (!defined('ANON_ALLOWED_ACCESS')) exit;

abstract class Base
{
    protected string $slug;
    protected string $pluginDir;

    public function __construct(string $pluginSlug = '')
    {
        $this->slug = $pluginSlug !== '' ? strtolower($pluginSlug) : self::slugFromClass(get_class($this));

        if (!Plugin::isPluginActive($this->slug)) {
            $this->pluginDir = '';
            return;
        }

        try {
            $reflector = new ReflectionClass($this);
            $this->pluginDir = dirname($reflector->getFileName());
        } catch (Throwable $e) {
            $this->pluginDir = '';
        }

        $this->autoloadMode();
    }

    protected function autoloadMode(): void
    {
        if (!Plugin::isPluginActive($this->slug)) {
            return;
        }

        if ($this->pluginDir === '') {
            return;
        }

        $mode = defined('ANON_APP_MODE') ? ANON_APP_MODE : Env::get('app.mode', 'api');

        if ($mode === 'api') {
            $file = $this->pluginDir . '/app/useApp.php';
            if (file_exists($file)) {
                include_once $file;
            }
            return;
        }

        if ($mode === 'cms') {
            $this->loadRoutesFromDatabase();
            return;
        }

        if ($mode === 'auto') {
            $loaded = $this->loadRoutesFromDatabase();
            if (!$loaded) {
                $file = $this->pluginDir . '/app/useApp.php';
                if (file_exists($file)) {
                    include_once $file;
                }
            }
        }
    }

    private function loadRoutesFromDatabase(): bool
    {
        try {
            $db = Database::getInstance();
            $row = $db->db('options')
                ->where('name', 'plugin:' . $this->slug . ':routes')
                ->first();

            if ($row && !empty($row['value'])) {
                $routes = json_decode($row['value'], true);
                if (is_array($routes)) {
                    foreach ($routes as $route) {
                        if (isset($route['path']) && isset($route['handler'])) {
                            Anon::route($route['path'], $route['handler'], $route['config'] ?? []);
                        }
                    }
                    return true;
                }
            }
        } catch (Throwable $e) {
            Debug::error("Failed to load plugin routes from database", [
                'plugin' => $this->slug,
                'error' => $e->getMessage()
            ]);
        }

        return false;
    }

    public function getPages()
    {
        $pages = $this->loadPagesConfig();
        if (is_array($pages)) {
            foreach ($pages as &$page) {
                if (isset($page['handler'])) {
                    unset($page['handler']);
                }
            }
            return $pages;
        }
        return [];
    }

    public function getPagesWithHandler()
    {
        return $this->loadPagesConfig();
    }

    protected function loadPagesConfig()
    {
        if ($this->pluginDir !== '') {
            $file = $this->pluginDir . '/app/pages.php';
            if (file_exists($file)) {
                $plugin = $this;
                return include $file;
            }
        }
        return [];
    }

    public function getSettingsSchema()
    {
        if ($this->pluginDir !== '') {
            $file = $this->pluginDir . '/app/setup.php';
            if (file_exists($file)) {
                $config = include $file;
                if (is_array($config)) {
                    if (isset($config['api']) || isset($config['cms'])) {
                        $apiConfig = isset($config['api']) && is_array($config['api']) ? $config['api'] : [];
                        $cmsConfig = isset($config['cms']) && is_array($config['cms']) ? $config['cms'] : [];
                        return array_merge($apiConfig, $cmsConfig);
                    }
                    return $config;
                }
            }
        }

        try {
            $ref = new ReflectionMethod($this, 'options');
            if ($ref->getDeclaringClass()->getName() !== __CLASS__) {
                $result = $ref->isStatic() ? $ref->invoke(null) : $ref->invoke($this);
                if (is_array($result)) {
                    return $result;
                }
            }
        } catch (Throwable $e) {
        }

        if ($this->pluginDir !== '') {
            $meta = Plugin::readPluginMetaFromPackageJson($this->pluginDir);
            if ($meta && !empty($meta['settings']) && is_array($meta['settings'])) {
                return $meta['settings'];
            }
        }

        return [];
    }

    protected static function slugFromClass(string $class): string
    {
        if (strpos($class, 'Anon\\Modules\\System\\Plugin\\') === 0) {
            $name = substr($class, strlen('Anon\\Modules\\System\\Plugin\\'));
            return strtolower(preg_replace('/[-_]/', '', $name));
        }
        return strtolower($class);
    }

    public function options()
    {
        return new OptionsProxy('plugin', $this->slug, null);
    }

    public function user()
    {
        $user = Cms::getCurrentUser();
        return $user !== null ? new User($user) : null;
    }

    public function theme()
    {
        if (!class_exists(ThemeHelper::class) || !class_exists(Theme::class)) {
            return null;
        }
        return new ThemeHelper(Theme::getCurrentTheme());
    }
}

