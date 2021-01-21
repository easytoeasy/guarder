<?php

namespace pzr\guarder;

use ErrorException;
use Exception;
use Monolog\Logger;
use Smarty;

require dirname(__DIR__) . '/vendor/autoload.php';

date_default_timezone_set('Asia/Shanghai');

class Process
{
    /** 保存ppid的文件 */
    const PPID_FILE = '/tmp/guarder.pid';
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



    public function __construct()
    {
        $this->config = IniParser::parse();
        $this->taskers = $this->config->taskers;
        $this->logger = Helper::getLogger('process');
    }

    public function run()
    {
        if (empty($this->config->taskers)) {
            throw new ErrorException('no task');
        }
        if ($this->notifyMaster()) {
            return;
        }

        $pid = pcntl_fork();
        if ($pid < 0) {
            throw new ErrorException('fork error');
            exit;
        } elseif ($pid > 0) {
            exit;
        }
        if (!posix_setsid()) {
            exit;
        }

        @cli_set_process_title('Guarder Process');

        // 父进程退出
        pcntl_signal(SIGINT,  [$this, 'sigHandler']);
        pcntl_signal(SIGTERM, [$this, 'sigHandler']);
        pcntl_signal(SIGQUIT, [$this, 'sigHandler']);

        $stream = new Stream($this->config);
        $this->stream = $stream;
        // 将主进程ID写入文件
        file_put_contents(self::PPID_FILE, getmypid());
        // master进程继续
        while (true) {
            // 初始化子进程
            $this->initTasker();

            // 测试时打开很好用
            if (empty($this->childPids)) {
                $stream->close();
                break;
            }

            //接收HTTP请求
            $stream->accept(function ($program, $action) {
                $this->handle($program, $action);
                return $this->display();
            });

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
                $c = $this->taskers[$program];
                $state = pcntl_wifexited($status);
                $code = pcntl_wexitstatus($status);
                $this->logger->debug(sprintf("pid:%s, state:%s, code:%s", $pid, $state, $code));

                // 捕获异常退出
                if (!in_array($code, $this->exceptedCode)) {
                    $this->updateState($c, State::FATAL);
                    return;
                }
                // 通知结束后退出
                if ($code == self::ALARM_CODE) {
                    $this->updateState($c, State::STOPPED);
                    return;
                }
                // 命令退出
                if ($code == self::NORMAL_CODE && $state === false) {
                    $c->auto_restart ?
                        $this->updateState($c, State::BACKOFF) :
                        $this->updateState($c, State::STOPPED);
                    return;
                }
                // 其他情况
                $this->updateState($c, State::STOPPED);
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
        if (!is_file(self::PPID_FILE)) return false;
        $ppid = file_get_contents(self::PPID_FILE);
        $isAlive = Helper::isProcessAlive($ppid);
        if (!$isAlive) return false;
        return true;
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
            // 1 => ['pipe', 'w'], //输出，子进程输出
            2 => ['file', $c->logfile, 'a'],
        ];

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
        return $c;
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

        ob_start();
        try {
            require $sourcePath;
            $response = ob_get_contents();
        } catch (Exception $e) {
            $this->logger->error($e);
            $response = $e->getMessage();
        }
        ob_clean();
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

try {
    $cli = new Process();
    $cli->run();
} catch(Exception $e) {
    echo $e->getMessage();
}
