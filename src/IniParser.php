<?php

namespace pzr\guarder;

use ErrorException;
use FFI\ParserException;
use ParseError;

class IniParser
{

    public static $guarder_ini = [
        __DIR__ . '/config/guarder.ini',
        '/etc/guarder.ini',
        '/etc/config/guarder.ini',
        '/etc/guarder/guarder.ini',
    ];

    private static $config = null;

    /**
     * 解析配置文件
     *
     * @return void|Config
     */
    public static function parse()
    {
        $config = self::getConfig();
        $files = self::scanFiles($config->files);
        if (is_array($files)) foreach ($files as $file) {
            $taskConfig = parse_ini_file($file);
            $c = new Tasker($taskConfig);
            if (
                isset($config->taskers[$c->program]) ||
                empty($c->program)
            ) {
                throw new ErrorException('program empty or duplication');
            }
            $config->taskers[$c->program] = $c;
        }
        if (!$config->check()) {
            return false;
        }
        return $config;
    }

    public static function scanFiles($path)
    {
        if (empty($path)) return false;

        $str = strrchr($path, DIRECTORY_SEPARATOR); //正则匹配返回类似：/*.ini
        $dir = str_replace($str, '', $path);

        // 配置文件是相对路径
        if (!strncmp($dir, './', 2)) {
            $dir = str_replace('./', __DIR__ . '/config/', $dir);
        }
        if (!is_dir($dir))
            throw new ErrorException('has no such directory:' . $dir);
        $str = str_replace(['/', '.', '*'], ['', '\.', '.*?'], $str);
        $pattern =  '/^' . $str . '$/';
        $files = scandir($dir);
        $databack = [];
        foreach ($files as $file) {
            if ($file == '.' || $file == '..') continue;
            if (!preg_match($pattern, $file)) continue;
            $databack[] = $dir . DIRECTORY_SEPARATOR . $file;
        }
        return $databack;
    }

    public static function getCommLogfile()
    {
        $config = self::getConfig();
        return $config->logfile ?: '/var/log/guarder.log';
    }

    public static function getReceiverFile()
    {
        $config = self::getConfig();
        $receiver = $config->receiver;
        // 配置文件是相对路径
        if (!strncmp($receiver, './', 2)) {
            $receiver = str_replace('./', __DIR__ . '/config/', $receiver);
        }
        if (empty($receiver)) {
            $receiver = __DIR__ . '/config/receiver.ini';
        }
        if (!is_file($receiver)) {
            throw new ErrorException('has no such file:' . $receiver);
        }
        return $receiver;
    }

    public static function getPpidFile()
    {
        $config = self::getConfig();
        $file = $config->ppid_file;
        if (empty($file)) {
            $file = '/tmp/guarder.pid';
        }
        if (!is_file($file)) {
            throw new ErrorException('has no such file:' . $file);
        }
        return $file;
    }

    public static function parseReceiver()
    {
        $file = self::getReceiverFile();
        $ini = self::parse_ini_file_extended($file, true);
        return $ini;
    }

    public static function getConfig()
    {
        if (self::$config instanceof Config) {
            return self::$config;
        }

        $config = null;
        foreach (self::$guarder_ini as $ini_file) {
            if (!is_file($ini_file)) continue;
            $ini = parse_ini_file($ini_file);
            if (empty($ini) || !is_array($ini)) continue;
            $config = new Config($ini);
            break;
        }
        if (!($config instanceof Config)) {
            throw new ErrorException('guarder.ini parse error');
        }
        self::$config = $config;
        return $config;
    }

    /**
     * Parses INI file adding extends functionality via ":base" postfix on namespace.
     *
     * @param string $filename
     * @return array
     */
    public static function parse_ini_file_extended($filename)
    {
        $p_ini = parse_ini_file($filename, true);
        $config = array();
        foreach ($p_ini as $namespace => $properties) {
            if (strpos($namespace, ':') === false) {
                $config[$namespace] = $properties;
                continue;
            }
            list($name, $extends) = explode(':', $namespace);
            $name = trim($name);
            $extends = trim($extends);
            // create namespace if necessary
            if (!isset($config[$name])) $config[$name] = array();
            // inherit base namespace
            if (isset($p_ini[$extends])) {
                foreach ($p_ini[$extends] as $prop => $val)
                    $config[$name][$prop] = $val;
            }
            // overwrite / set current namespace values
            foreach ($properties as $prop => $val)
                $config[$name][$prop] = $val;
        }
        return $config;
    }
}
