<?php
namespace Anon\Modules\Cms\Theme;



use Anon\Modules\Cms\Theme\Theme;
use S;if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Parsedown
{
    private bool $safeMode = false;
    private bool $breaksEnabled = false;

    public function setSafeMode(bool $safeMode): self
    {
        $this->safeMode = $safeMode;
        return $this;
    }

    public function setBreaksEnabled(bool $breaksEnabled): self
    {
        $this->breaksEnabled = $breaksEnabled;
        return $this;
    }

    public function text(string $markdown): string
    {
        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
        $lines = explode("\n", $markdown);

        $html = [];
        $inCode = false;
        $codeLines = [];
        $inUl = false;

        $flushParagraph = function (array $buffer) use (&$html) {
            $text = trim(implode("\n", $buffer));
            if ($text === '') {
                return;
            }
            $html[] = '<p>' . $text . '</p>';
        };

        $buffer = [];

        foreach ($lines as $line) {
            $raw = $line;
            $line = rtrim($line, "\n");

            if (preg_match('/^```/', $line)) {
                if ($inUl) {
                    $html[] = '</ul>';
                    $inUl = false;
                }
                if ($inCode) {
                    $code = implode("\n", $codeLines);
                    $code = htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $html[] = '<pre><code>' . $code . '</code></pre>';
                    $codeLines = [];
                    $inCode = false;
                } else {
                    if (!empty($buffer)) {
                        $flushParagraph($buffer);
                        $buffer = [];
                    }
                    $inCode = true;
                }
                continue;
            }

            if ($inCode) {
                $codeLines[] = $raw;
                continue;
            }

            if (trim($line) === '') {
                if ($inUl) {
                    $html[] = '</ul>';
                    $inUl = false;
                }
                if (!empty($buffer)) {
                    $flushParagraph($buffer);
                    $buffer = [];
                }
                continue;
            }

            $line = $this->safeMode ? htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $line;

            if (preg_match('/^(#{1,6})\s+(.*)$/', $line, $m)) {
                if ($inUl) {
                    $html[] = '</ul>';
                    $inUl = false;
                }
                if (!empty($buffer)) {
                    $flushParagraph($buffer);
                    $buffer = [];
                }
                $level = strlen($m[1]);
                $content = $this->inline($m[2]);
                $html[] = "<h{$level}>{$content}</h{$level}>";
                continue;
            }

            if (preg_match('/^\s*[-*+]\s+(.*)$/', $line, $m)) {
                if (!empty($buffer)) {
                    $flushParagraph($buffer);
                    $buffer = [];
                }
                if (!$inUl) {
                    $html[] = '<ul>';
                    $inUl = true;
                }
                $content = $this->inline($m[1]);
                $html[] = '<li>' . $content . '</li>';
                continue;
            }

            $buffer[] = $this->inline($line);
        }

        if ($inCode) {
            $code = implode("\n", $codeLines);
            $code = htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html[] = '<pre><code>' . $code . '</code></pre>';
        }

        if ($inUl) {
            $html[] = '</ul>';
        }

        if (!empty($buffer)) {
            $flushParagraph($buffer);
        }

        $output = implode("\n", $html);
        if ($this->breaksEnabled) {
            $output = preg_replace_callback('/<p>([\sS]*?)<\/p>/', function ($m) {
                return '<p>' . str_replace("\n", "<br>\n", $m[1]) . '</p>';
            }, $output);
        }

        return (string) $output;
    }

    private function inline(string $text): string
    {
        $text = preg_replace_callback('/`([^`]+)`/', function ($m) {
            $code = htmlspecialchars($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            return '<code>' . $code . '</code>';
        }, $text);

        $text = preg_replace('/\*\*([^\*]+)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $text);

        $text = preg_replace_callback('/\[(.*?)\]\((.*?)\)/', function ($m) {
            $label = $m[1];
            $url = $m[2];
            $url = trim($url);
            if ($this->safeMode) {
                $url = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
            $allowed = preg_match('#^(https?:)?//#i', $url) || preg_match('#^/[^/].*#', $url);
            if (!$allowed) {
                return $label;
            }
            return '<a href="' . $url . '">' . $label . '</a>';
        }, $text);

        return $text;
    }
}

