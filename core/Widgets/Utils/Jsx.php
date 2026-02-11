<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * JSX 解析工具类
 * 提供将 XML/JSX 风格字符串转换为 UI Schema 数组的能力
 */
class Anon_Widgets_Utils_Jsx
{
    /**
     * 将 XML/JSX 字符串转换为 UI Schema 数组
     * 
     * @param string $xml XML 字符串
     * @param array $variables 变量注入
     * @return array
     * @throws Exception
     */
    public static function parse(string $xml, array $variables = []): array
    {
        $xml = trim($xml);
        if (empty($xml)) {
            return [];
        }

        $xml = self::preprocessJsxAttributes($xml);

        // 包装在根节点中以处理多个顶级元素
        $wrappedXml = '<root>' . $xml . '</root>';

        $dom = new DOMDocument();
        // 禁用错误输出
        libxml_use_internal_errors(true);
        // 保持 UTF-8
        // LIBXML_PARSEHUGE | LIBXML_NOBLANKS
        $dom->loadXML('<?xml version="1.0" encoding="utf-8"?>' . $wrappedXml, LIBXML_NOBLANKS | LIBXML_COMPACT);
        $errors = libxml_get_errors();
        libxml_clear_errors();

        if (!empty($errors)) {
            // 尝试更友好的错误提示
            $msg = $errors[0]->message;
            if (strpos($msg, 'AttValue') !== false) {
                $msg .= " (提示: 确保所有属性值都用引号包围，例如 data=\"{...}\")";
            }
            throw new Exception("JSX 解析失败: " . $msg);
        }

        $root = $dom->documentElement;
        $children = [];

        foreach ($root->childNodes as $node) {
            $parsed = self::parseNode($node, $variables);
            if ($parsed) {
                $children[] = $parsed;
            }
        }

        // 如果只有一个根节点，直接返回
        if (count($children) === 1) {
            return $children[0];
        }

        return $children;
    }

    /**
     * 预处理 JSX 属性，为表达式添加引号并编码
     */
    private static function preprocessJsxAttributes(string $xml): string
    {
        $length = strlen($xml);
        $result = '';
        $i = 0;

        while ($i < $length) {
            $char = $xml[$i];

            // 查找属性赋值 =
            if ($char === '=') {
                // 预读寻找 {
                $j = $i + 1;
                while ($j < $length && ctype_space($xml[$j])) {
                    $j++;
                }

                // 检查是否是大括号表达式
                if ($j < $length && $xml[$j] === '{') {
                    $result .= '=';
                    // 移动 i 到 { 的位置
                    $i = $j;

                    $braceLevel = 0;
                    $content = '';
                    $hasContent = false;

                    while ($i < $length) {
                        $c = $xml[$i];
                        if ($c === '{') {
                            $braceLevel++;
                        } elseif ($c === '}') {
                            $braceLevel--;
                            if ($braceLevel === 0) {
                                $content .= $c;
                                $i++;
                                $hasContent = true;
                                break;
                            }
                        }
                        $content .= $c;
                        $i++;
                    }

                    if ($hasContent) {
                        // 移除首尾大括号并 Base64 编码
                        $inner = substr($content, 1, -1);
                        $encoded = base64_encode($inner);
                        $result .= '"__JSX_B64_' . $encoded . '__"';
                    } else {
                        $result .= $content;
                    }
                    continue;
                }
            }

            $result .= $char;
            $i++;
        }

        return $result;
    }

    /**
     * 递归解析节点
     */
    private static function parseNode($node, $variables)
    {
        if ($node->nodeType === XML_TEXT_NODE || $node->nodeType === XML_CDATA_SECTION_NODE) {
            $content = trim($node->textContent);
            return $content !== '' ? $content : null;
        }

        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return null;
        }

        $tagName = $node->nodeName;
        // FormItem -> form_item
        $type = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $tagName));

        $props = [];
        $events = [];

        if ($node->hasAttributes()) {
            foreach ($node->attributes as $attr) {
                $name = $attr->name;
                $value = $attr->value;

                // 处理 Base64 编码的表达式
                if (strpos($value, '__JSX_B64_') === 0 && substr($value, -2) === '__') {
                    $encoded = substr($value, 10, -2);
                    $decoded = base64_decode($encoded);

                    // 尝试解析表达式
                    $value = self::evaluateExpression($decoded, $variables);
                }
                // 兼容旧的简单变量替换 {var} (虽然预处理应该已经覆盖了)
                elseif (strpos($value, '{') === 0 && strpos($value, '}') === strlen($value) - 1) {
                    $varName = substr($value, 1, -1);
                    $value = self::resolveVariable($varName, $variables);
                }

                // 处理事件 (onXxx)
                if (strpos($name, 'on') === 0) {
                    $events[$name] = $value;
                } else {
                    $props[$name] = $value;
                }
            }
        }

        $children = [];
        if ($node->hasChildNodes()) {
            foreach ($node->childNodes as $child) {
                $parsedChild = self::parseNode($child, $variables);
                if ($parsedChild !== null) {
                    $children[] = $parsedChild;
                }
            }
        }

        // 特殊处理：如果是文本节点作为唯一子节点，视为 content 或 children 文本
        if (count($children) === 1 && is_string($children[0])) {
            $props['children'] = $children[0];
            $children = [];
        }

        $schema = [
            'type' => $type,
            'props' => $props,
        ];

        if (!empty($events)) {
            $schema['events'] = $events;
        }

        if (!empty($children)) {
            $schema['children'] = $children;
        }

        return $schema;
    }

    /**
     * 评估表达式
     */
    private static function evaluateExpression($expr, $context)
    {
        $expr = trim($expr);

        // 1. 变量引用 $var 或 var
        if (preg_match('/^\$?[a-zA-Z0-9_\.]+$/', $expr)) {
            return self::resolveVariable($expr, $context);
        }

        // 2. JSON 数组或对象
        if ((strpos($expr, '[') === 0 && substr($expr, -1) === ']') ||
            (strpos($expr, '{') === 0 && substr($expr, -1) === '}')
        ) {
            $json = json_decode($expr, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }

            // 尝试替换单引号为双引号（容错）
            $fixedExpr = str_replace("'", '"', $expr);
            $json = json_decode($fixedExpr, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }

        // 3. 简单值
        if ($expr === 'true') return true;
        if ($expr === 'false') return false;
        if ($expr === 'null') return null;
        if (is_numeric($expr)) return $expr + 0;

        // 默认返回原字符串
        return $expr;
    }

    /**
     * 解析变量
     */
    private static function resolveVariable($path, $context)
    {
        if (is_numeric($path)) return $path + 0;
        if ($path === 'true') return true;
        if ($path === 'false') return false;
        if ($path === 'null') return null;

        // 如果变量名以 $ 开头（如 {$appList}），去掉 $
        if (strpos($path, '$') === 0) {
            $path = substr($path, 1);
        }

        $parts = explode('.', $path);
        $current = $context;
        foreach ($parts as $part) {
            if (is_array($current) && isset($current[$part])) {
                $current = $current[$part];
            } else {
                return $path; // 没找到，原样返回字符串
            }
        }
        return $current;
    }
}
