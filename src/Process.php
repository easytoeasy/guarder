<?php

namespace pzr\guarder;

use ErrorException;
use Exception;
use Monolog\Logger;
use Smarty;
use Throwable;

require dirname(__DIR__) . '/vendor/autoload.php';

date_default_timezone_set('Asia/Shanghai');

class Process
{
    /** 子进程正常退出 */
    const NORMAL_CODE = 0;
    /** 子进程通知结束退出 */
    const ALARM_CODE = 1;
    /** 捕获异常退出 */
    const EXCEPTION_CODE = 10;

    protected $exceptedCode = [
        self::NORMAL_CODE,
        self::ALARM_CODE,
    ];

    /** @var Config 配置文件对象*/
    protected $config;
    /** 待回收子进程 */
    protected $childPids = array();
    /** 待执行的任务数组 */
    protected $taskers = array();
    /** @var Logger */
    protected $logger;
    /** @var string 操作记录 */
    protected $message = 'Init Process OK';
    /** @var Stream */
    protected $stream;
    /** @var string 保存父进程的pid */
    protected $pidFile;
    protected $host;
    protected $port;


    public function __construct()
    {
        $this->config = IniParser::parse();
        $this->taskers = $this->config->taskers;
        $this->pidFile = IniParser::getPidFile();
        $this->host = $this->config->host;
        $this->port = $this->config->port;
        $this->logger = Helper::getLogger('process');
        unset($this->config);

    }

    public function run()
    {
        if (empty($this->taskers)) {
            throw new ErrorException('taskers is empty');
        }

        // 父进程还在，不让重启
        if ($this->notifyMaster()) {
            echo 'master still alive';
            exit(3);
        }

        $pid = pcntl_fork();
        if ($pid < 0) {
            throw new ErrorException('fork error');
            exit(3);
        } elseif ($pid > 0) {
            exit(0);
        }
        if (!posix_setsid()) {
            exit(3);
        }

        @cli_set_process_title('Guarder Process');

        // 父进程退出
        pcntl_signal(SIGINT,  [$this, 'sigHandler']);
        pcntl_signal(SIGTERM, [$this, 'sigHandler']);
        pcntl_signal(SIGQUIT, [$this, 'sigHandler']);

        $stream = new Stream($this->host, $this->port);
        $this->stream = $stream;
        // 将主进程ID写入文件
        file_put_contents($this->pidFile, getmypid());
        // master进程继续
        while (true) {
            // 初始化子进程
            $this->initTasker();

            // 测试时打开很好用
            // if (empty($this->childPids)) {
            //     $stream->close();
            //     break;
            // }

            //接收HTTP请求
            $stream->accept(function ($program, $action) {
                $this->handle($program, $action);
                return $this->display();
            });

            // 子进程回收
            $this->waitpid();
        }
    }

    /**
     * 操作子进程。所有的子进程操作都在这里执行
     *
     * @return void
     */
    protected function initTasker()
    {
        foreach ($this->taskers as $c) {
            switch ($c->state) {
                case State::RUNNING:
                case State::STOPPED:
                case State::FATAL:
                case State::UNKNOWN:
                    break;
                case State::STARTING:
                    if (empty($c->pid)) {
                        $this->fork($c);
                        break;
                    }
                    if (posix_kill($c->pid, SIGTERM)) {
                        $this->fork($c);
                    }
                    break;
                case State::STOPPING:
                    if ($c->pid) {
                        if (posix_kill($c->pid, SIGTERM))
                            $this->updateState($c, State::STOPPED);
                    } else {
                        $this->updateState($c, State::STOPPED);
                    }
                    break;
                case State::BACKOFF:
                    $this->fork($c);
                    break;
                default:
                    break;
            }
        }
    }

    protected function handle($program, $action)
    {
        if (!empty($action)) {
            $this->message = sprintf("%s %s at %s", $action, $program, date('Y-m-d H:i:s'));
            $this->logger->debug(sprintf("program:%s, action:%s", $program, $action));
        }
        if ($program && isset($this->taskers[$program])) {
            $c = $this->taskers[$program];
        }
        switch ($action) {
            case 'refresh':
                array_walk($this->taskers, function ($c, $key) {
                    $c->uptime = date('m-d H:i');
                });
                break;
            case 'restartall':
                array_walk($this->taskers, function ($c, $key) {
                    $c->state = State::STARTING;
                });
                break;
            case 'stopall':
                array_walk($this->taskers, function ($c, $key) {
                    if (in_array($c->state, State::runingState()))
                        $c->state = State::STOPPING;
                });
                break;
            case 'stop':
                if (!in_array($c->state, State::runingState())) break;
                $c->state = State::STOPPING;
                break;
            case 'start':
                if (!in_array($c->state, State::stopedState())) break;
                $c->state = State::STARTING;
                break;
            case 'restart':
                $c->state = State::STARTING;
                break;
            case 'clear':
                if ($logfile = $c->logfile) {
                    file_put_contents($logfile, '');
                }
                break;
            default:
                break;
        }
    }

    protected function waitpid()
    {
        // 子进程回收
        pcntl_signal_dispatch();
        foreach ($this->childPids as $program => $pid) {
            $result = pcntl_waitpid($pid, $status, WNOHANG);
            if ($result == $pid || $result == -1) {
                unset($this->childPids[$program]);
                // 回收重启前的子进程
                if (!isset($this->taskers[$program])) continue;
                /** @var Tasker $c */
                $c = $this->taskers[$program];
                /**
                 * @see https://www.jb51.net/article/73377.htm
                 * 0 正常退出
                 * 1 一般性未知错误
                 * 2 不适合的shell命令
                 * 126 调用的命令无法执行
                 * 127 命令没找到
                 * 128 非法参数导致退出
                 * 128+n Fatal error signal ”n”：如`kill -9` 返回137
                 * 130 脚本被`Ctrl C`终止
                 * 255 脚本发生了异常退出了。那么为什么是255呢？因为状态码的范围是0-255，超过了范围。
                 */
                $code = pcntl_wexitstatus($status);
                $this->logger->debug(sprintf("pid:%s, code:%s", $pid, $code));
                switch ($code) {
                    case 0: //正常退出，不重启
                        $state = State::STOPPED;
                        break;
                    case 1: // supervisor是将1、2的code码作为配置是否重启
                    case 2:
                        // $state = $c->auto_restart ? State::BACKOFF : State::EXITED;
                        $state = State::EXITED;
                        break;
                    case 255:
                        $state = State::FATAL;
                        break;
                    default:
                        $state = State::UNKNOWN;
                        break;
                }
                $this->updateState($c, $state);
            }
        }
    }

    protected function updateState(Tasker &$c, $state)
    {
        $c->pid = '';
        $c->uptime = '';
        $c->state = $state;
    }


    /**
     * 父进程存活情况下，只会通知父进程信息，否则可能产生多个守护进程
     *
     * @return bool 父进程是否健在
     */
    protected function notifyMaster()
    {
        if (!is_file($this->ppidFile)) return false;
        $pid = file_get_contents($this->ppidFile);
        return `ps aux | awk '{print $2}' | grep -w $pid`;
    }

    /**
     * fork一个新的子进程
     *
     * @param string $queueName
     * @param integer $qos
     * @return Tasker
     */
    protected function fork(Tasker &$c)
    {
        $descriptorspec = [
            0 => ['pipe', 'r'], //输入，子进程从此管道读取数据
        ];
        $file = $c->logfile ?: IniParser::getCommLogfile();
        if (empty($file)) {
            $c->state = State::EXITED;
            return;
        }
        $descriptorspec[1] = ['file', $file, 'a']; //正常输出
        $descriptorspec[2] = ['file', $file, 'a']; //异常输出

        $process = proc_open('exec ' . $c->command, $descriptorspec, $pipes, $c->directory);
        if ($process) {
            $stdin = [
                'cron' => $c->cron,
                'alarm_limit' => $c->alarm_limit,
                'retry' => $c->retry,
                'file' => $c->logfile,
            ];
            fwrite($pipes[0], serialize($stdin)); //把参数传给子进程
            fclose($pipes[0]); //fclose关闭管道后proc_close才能退出子进程,否则会发生死锁
            $ret = proc_get_status($process);
            if ($ret['running']) {
                $c->state = State::RUNNING;
                $c->pid = $ret['pid'];
                $c->uptime = date('m-d H:i');
                // 子进程restart时防止原子进程变成僵尸进程
                if (
                    isset($this->childPids[$c->program]) &&
                    $oldPid = $this->childPids[$c->program]
                ) {
                    $this->childPids[] = $oldPid;
                }
                $this->childPids[$c->program] = $ret['pid'];
            } else {
                $c->state = State::BACKOFF;
                proc_close($process);
            }
        } else {
            $c->state = State::FATAL;
        }
    }

    protected function display()
    {
        $scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
        $scriptName = in_array($scriptName, ['', '/']) ? '/index.php' : $scriptName;

        if ($scriptName == '/index.html') {
            $location = sprintf("%s://%s:%s", 'http', $this->config->host, $this->config->port);
            return Http::status_301($location);
        }

        $sourcePath = Http::$basePath . $scriptName;
        if (!is_file($sourcePath)) {
            return Http::status_404();
        }

        try {
            ob_start(null, null, PHP_OUTPUT_HANDLER_CLEANABLE | PHP_OUTPUT_HANDLER_REMOVABLE);
            require $sourcePath;
            $response = ob_get_contents();
            ob_end_clean();
        } catch (Throwable $e) {
            $this->logger->error($e);
            $response = $e->__toString();
        }
        return Http::status_200($response);
    }

    protected function sigHandler($signo)
    {
        switch ($signo) {
                // 父进程退出
            case SIGINT:
            case SIGTERM:
            case SIGQUIT:
                array_walk($this->taskers, function ($c) {
                    if ($c->pid) {
                        posix_kill($c->pid, SIGTERM);
                    }
                });
                exit(0);
        }
    }
}


if (PHP_SAPI != 'cli') {
    throw new ErrorException('非cli模式不可用');
}

error_reporting(E_ALL | ~E_WARNING | ~E_NOTICE);

try {
    $cli = new Process();
    $cli->run();
} catch (Exception $e) {
    echo $e->getMessage();
}
