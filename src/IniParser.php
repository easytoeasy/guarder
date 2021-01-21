<?php

namespace pzr\guarder;

use ErrorException;
use FFI\ParserException;
use ParseError;

class IniParser
{

    public static $guarder_ini = [
        __DIR__ . '/config/guarder.ini',
        '/etc/guarder/guarder.ini',
    ];

    /**
     * 解析配置文件
     *
     * @return void|Config
     */
    public static function parse()
    {
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
        $files = self::getDirFiles($config->files);
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

    public static function getDirFiles($path)
    {
        if (empty($path)) return false;
        
        $str = strrchr($path, '/'); //正则匹配返回类似：/*.ini
        $dir = str_replace($str, '', $path);

        // 配置文件是相对路径
        if (!strncmp($dir, '.', 1)) {
            $dir = str_replace('./', __DIR__ . '/config/', $dir);
        }
        if (!is_dir($dir)) 
            throw new ErrorException('has no such file or directory:' . $dir);
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
        return $config->logfile ?: '/var/log/guarder.log';
    }
}
