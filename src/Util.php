<?php

namespace Eelf\Protobuf;

class Util {
    public static function bind($template, array $params = []) {
        $pieces = explode('#', $template);
        $result = '';
        $sharp = 0;
        $count = count($pieces);
        foreach ($pieces as $index => $piece) {
            if ($index % 2 == $sharp) {
                $result .= $piece;
            } else if (isset($params[$piece]) && $index != 0 && $index != $count - 1) {
                $result .= $params[$piece];
            } else {
                $result .= '#' . $piece;
                $sharp = $sharp ? 0 : 1;
            }
        }
        return $result;
    }
    public static function render(string $template, array $vars) {
        extract($vars);
        ob_start();
        include $template;
        return ob_get_clean();
    }

    public static function ensure_extensions(array $exts) {
        foreach ($exts as $ext) {
            if (!extension_loaded($ext) && !dl("$ext.so")) throw new \Exception("could not load extension $ext");
        }
    }

    public static function hasPrefix($string, $prefix) {
        $prefix_len = strlen($prefix);
        if (substr($string, 0, $prefix_len) == $prefix) return substr($string, $prefix_len);
        return false;
    }

    public static function protoToPath($class) {
        return implode('/', explode('.', trim($class, '.')));
    }
    public static function protoToPhpclass($class) {
        $parts = explode('.', trim($class, '.'));
        if (in_array(
            strtolower($parts[count($parts) - 1]),
            [
                'empty',
            ]
        )) {
            $parts[count($parts) - 1] .= '_';
        }
        return implode('\\', $parts);
    }

    public static function protoToPhpName($name) {
        return implode('', array_map('ucfirst', explode('_', $name)));
    }

    private static $autoload = [];
    public static function psr4($ns, $dir) {
        if (!self::$autoload) {
            spl_autoload_register(
                function ($class_name) {
                    foreach (self::$autoload as $ns => $dir) {
                        if (($substr = self::hasPrefix($class_name, $ns)) !== false) {
                            $path = $dir . '/' . implode('/', explode('\\', $substr)) . '.php';
                            if (file_exists($path)) {
                                require_once $path;
                            }
                        }
                    }
                }
            );
        }
        self::$autoload[$ns] = $dir;
    }

    public static function reservedPhp($name) {
        if (in_array(strtolower($name), ['empty'])) {
            $name .= '_';
        }
        return $name;
    }

    public static function iterateGenerators(array $generators) {
        foreach ($generators as $generator) yield from $generator;
    }

    public static function stderr_var_dump(...$var) {
        ob_start();
        var_dump(...$var);
        fwrite(STDERR, ob_get_clean());
    }
}
