<?php

namespace pzr\guarder;

use Closure;
use Cron\CronExpression;
use ErrorException;
use Monolog\Logger;
use pzr\guarder\FileHandler;
use pzr\guarder\Helper;
use pzr\guarder\sender\Sender;

class BaseTask extends BaseObject implements ITask
{

    /** 
     * @var string crontab定时策略：* * * * * 
     */
    public $cron;
    /** 警告上限次数 */
    public $alarm_limit = 0;
    /** @var int 返回不是预期结果重试次数 */
    public $retry = 0;
    /** @var string 日志文件 */
    public $file;

    /** @var pzr\guarder\sender\Sender 消息警报 */
    protected $sender = null;
    /** @var CronExpression */
    protected $cronExpression = null;
    /** @var Logger */
    protected $logger;


    public function init()
    {
        $this->sender = new Sender();
        // 阻塞读取stdin的数据
        $stdin = fread(STDIN, 1024);
        $config = unserialize($stdin);
        if (
            empty($config['cron']) ||
            empty($config['alarm_limit'])
        ) {
            throw new ErrorException('cron||alarm_limit is empty');
        }
        Helper::configure($this, $config);
        $this->logger = Helper::getLogger('guarder', $this->file);
        return $config;
    }

    public function run(Closure $callback)
    {
        if (empty($this->cron)) {
            throw new ErrorException('invalid value cron');
        }
        $cronExpression = CronExpression::factory($this->cron);

        $this->logger->info(sprintf("pid: %s 启动", getmypid()));

        $alarm = 0;
        while (true) {
            if ($cronExpression->isDue()) {
                // 执行相关监控业务
                $unExcepted = $callback();
                if ($unExcepted === true) {
                    $alarm++;
                } else {
                    $alarm = 0;
                }
            }
            if ($alarm >= $this->alarm_limit) break;
            usleep(60000000); //1 minute
        }
        $this->logger->info(sprintf("pid: %s 关闭", getmypid()));
        // 退出状态码为1则不自启动，0则自启动
        exit(1);
    }

    /**
     * Get the value of logger
     * @return Logger
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Get the value of sender
     * @return Sender
     */
    public function getSender()
    {
        return $this->sender;
    }
}
