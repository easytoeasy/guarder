<?php

namespace pzr\guarder\sender;

use ErrorException;
use Monolog\Logger;
use pzr\guarder\BaseObject;
use pzr\guarder\Helper;
use pzr\guarder\IniParser;
use Yunpian\Sdk\Api\TplApi;
use Yunpian\Sdk\YunpianClient;

class Yunpian extends Message
{
    /** @var string 短信的apikey */
    public $apikey;
    /** @var string 短信的模板ID */
    public $tplid;
    /** @var string 短信模板的占位符 */
    public $tplstr;
    /** @var string */
    public $sendTo;

    /** @var Logger */
    protected $logger;
    protected $module = 'yunpian';

    public function check(): bool
    {
        if (empty($this->content) || empty($this->sendTo)) {
            return false;
        }
        return true;
    }


    public function send()
    {
        $this->sendTo = $this->getReceiver()->phones;
        $isOk = $this->check();
        if (!$isOk) return false;
        
        $apikey = $this->apikey;
        //初始化client,apikey作为所有请求的默认值
        $clnt = YunpianClient::create($apikey);

        $tplOperator = new TplApi();
        $tplOperator->init($clnt);
        $result = $tplOperator->get([
            YunpianClient::TPL_ID => $this->tplid,
            YunpianClient::APIKEY => $apikey,
        ]);

        if (!$result->isSucc())
            throw new ErrorException(sprintf("code:%s, msg:%s", $result->isSucc(), $result->msg()));

        $tpl = $result->data()['tpl_content'];
        $tpl = str_replace($this->tplstr, $this->content, $tpl);

        $param = [
            YunpianClient::MOBILE => $this->sendTo,
            YunpianClient::TEXT => $tpl,
            YunpianClient::APIKEY => $apikey
        ];
        $r = $clnt->sms()->batch_send($param);
        $this->logger->info(sprintf(
            "mobile：%s, tpl：%s, isSucc：%s",
            $this->sendTo,
            $tpl,
            $r->isSucc()
        ));
        return $r->isSucc();
    }
}