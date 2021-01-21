<?php

namespace pzr\guarder\sender;

use DateTimeZone;
use ErrorException;
use Monolog\Logger;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use pzr\guarder\FileHandler;
use pzr\guarder\Helper;
use Yunpian\Sdk\Api\TplApi;
use Yunpian\Sdk\YunpianClient;

class Sender
{

    const RECEIVER_INI_PATH = __DIR__ . '/../config/receiver.ini';
    const SEND_NO = 0;
    const SEND_MAILER = 1;
    const SEND_MESSAGE = 2;
    const SEND_ALL = 3;
    const SEND_DEFAULT = 1;

    public $ini_file;

    /** @var Mailer */
    protected $mail;
    /** @var Message */
    protected $message;
    /** 发送方式*/
    protected $send_type;
    /** @var Logger */
    protected $logger;

    protected $sendTypes = [
        self::SEND_NO,
        self::SEND_MAILER,
        self::SEND_MESSAGE,
        self::SEND_ALL,
    ];


    public function __construct($ini_file = '')
    {
        if (is_file($ini_file)) $this->ini_file = $ini_file;
        else $this->ini_file = self::RECEIVER_INI_PATH;

        $this->init();
        $this->check();

        $this->logger = Helper::getLogger('sender');
    }

    public function init()
    {
        $ini = parse_ini_file($this->ini_file, true);
        if (empty($ini))
            throw new ErrorException(sprintf("has no such directory or file: %s", $this->ini_file));

        if (!empty($ini['mail']))
            $this->mail = new Mailer($ini['mail']);

        if (!empty($ini['message']))
            $this->message = new Message($ini['message']);

        $receiver = $ini['receiver'];
        $mailers = $receiver['mailers'];
        $phones = $receiver['phones'];
        $send_type = $receiver['send_type'];
        if (!in_array($send_type, $this->sendTypes))
            $send_type = self::SEND_DEFAULT;
        $this->send_type = $send_type;

        if ($mailers)
            $this->mail->sendTo = explode(',', $mailers);

        if ($phones)
            $this->message->sendTo = $phones;

        return $ini;
    }

    public function check()
    {
        if (
            $this->send_type == self::SEND_MAILER &&
            empty($this->mail->sendTo)
        ) {
            throw new ErrorException('open sender mail but empty sendTo');
        }

        if ($this->send_type == self::SEND_MESSAGE) {
            if (empty($this->message->sendTo))
                throw new ErrorException('open sender message but empty sendTo');
            if (empty($this->message->tplstr))
                throw new ErrorException('open sender message but empty tplstr');
        }

        if ($this->send_type == self::SEND_ALL) {
            if (empty($this->message->tplstr))
                throw new ErrorException('open sender message but empty tplstr');
            if (empty($this->mail->sendTo) && empty($this->message->sendTo)) {
                throw new ErrorException('open sender both but empty sendTo');
            }
        }

        return true;
    }

    public function sendMail($subject, $content)
    {
        $mail = new PHPMailer(true);
        //Server settings
        $mail->SMTPDebug  = SMTP::DEBUG_OFF;
        $mail->isSMTP();                                          // Send using SMTP
        $mail->Host       = $this->mail->host;                    // Set the SMTP server to send through
        $mail->SMTPAuth   = true;                                 // Enable SMTP authentication
        $mail->Username   = $this->mail->username;                // SMTP username
        $mail->Password   = $this->mail->password;                // SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;          // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
        $mail->Port       = $this->mail->port;                    // TCP port to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above
        $mail->CharSet    = PHPMailer::CHARSET_UTF8;

        //Recipients
        $mail->setFrom($this->mail->from, $this->mail->name);
        foreach ($this->mail->sendTo as $mailer) {
            $mail->addAddress($mailer);
        }

        // Content
        $mail->isHTML(false);        // Set email format to HTML
        $mail->Subject = $subject;
        $mail->Body    = $content;
        $isSucc = $mail->send();
        $this->logger->info(sprintf("mail：%s, isSucc：%s", $content, $isSucc));
        return $isSucc;
    }

    public function sendMessage($content)
    {
        $apikey = $this->message->apikey;
        //初始化client,apikey作为所有请求的默认值
        $clnt = YunpianClient::create($apikey);

        $tplOperator = new TplApi();
        $tplOperator->init($clnt);
        $result = $tplOperator->get([
            YunpianClient::TPL_ID => $this->message->tplid,
            YunpianClient::APIKEY => $apikey,
        ]);

        if (!$result->isSucc())
            throw new ErrorException(sprintf("code:%s, msg:%s", $result->isSucc(), $result->msg()));

        $tpl = $result->data()['tpl_content'];
        $tpl = str_replace($this->message->tplstr, $content, $tpl);

        $param = [
            YunpianClient::MOBILE => $this->message->sendTo,
            YunpianClient::TEXT => $tpl,
            YunpianClient::APIKEY => $apikey
        ];
        $r = $clnt->sms()->batch_send($param);
        $this->logger->info(sprintf(
            "mobile：%s, tpl：%s, isSucc：%s",
            $this->message->sendTo,
            $tpl,
            $r->isSucc()
        ));
        return $r->isSucc();
    }

    public function send($content, $subject = '')
    {
        switch ($this->send_type) {
            case self::SEND_NO:
                break;
            case self::SEND_MAILER:
                $this->sendMail($subject, $content);
                break;
            case self::SEND_MESSAGE:
                $this->sendMessage($content);
                break;
            case self::SEND_ALL:
                $this->sendMail($subject, $content);
                $this->sendMessage($content);
                break;
            default:
                break;
        }
    }

    /**
     * Get the value of message
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Get the value of mail
     */
    public function getMail()
    {
        return $this->mail;
    }
}
