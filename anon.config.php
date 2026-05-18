<?php

use Anon\Core\Support\Config;

/**
 * Anon Framework Next 核心配置文件
 *
 * 框架内建了极为完善的默认配置，所有敏感信息均可通过 `.env` 环境变量配置。
 * 你可以像使用 `vite.config.ts` 一样，在这里仅写入你想要覆盖或自定义的配置项。
 * 
 * @see https://anon.miomoe.cn/guide/architecture/configuration
 */

return Config::define([
    // 例如：你可以取消注释来修改默认的应用名称
    // 'app' => [
    //     'name' => 'Anon Framework Next'
    // ]
]);
