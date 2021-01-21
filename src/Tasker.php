<?php

namespace pzr\guarder;

class Tasker extends BaseObject
{
    /** 开启多少个消费者 */
    public $numprocs = 1;
    /** 当前配置的唯一标志 */
    public $program;
    /** 执行的命令 */
    public $command;
    /** 当前工作的目录 */
    public $directory;
    /** 程序执行日志记录 */
    public $logfile = '';
    /** 进程pid */
    public $pid;
    /** 进程状态 */
    public $state = State::STARTING;
    /** 自启动 */
    public $auto_restart = false;
    /** 启动时间 */
    public $uptime;
    /** 定时任务 */
    public $cron;
    /** 警告次数上限 */
    public $alarm_limit;
    /** @var int 重试次数 */
    public $retry;

}
