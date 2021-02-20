<?php

namespace pzr\guarder\sender;

use Monolog\Logger;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use pzr\guarder\Helper;

class Mailer extends Message
{

    public $host;
    public $username;
    public $password;
    public $port;
    public $from;
    public $name;
    /** @var array */
    public $sendTo;

    protected $module = 'mailer';

    public function check(): bool
    {
        if (empty($this->content) || empty($this->sendTo)) {
            $this->logger->debug(sprintf(
                "check failed, content:%s, sendTo:%s",
                $this->content,
                implode(',', $this->sendTo)
            ));
            return false;
        }
        return true;
    }

    public function send()
    {
        $this->sendTo = $this->getReceiver()->mailers;
        $isOk = $this->check();
        if (!$isOk) {
            return false;
        }
        $mail = new PHPMailer(true);
        //Server settings
        $mail->SMTPDebug  = SMTP::DEBUG_OFF;
        $mail->isSMTP();                                    // Send using SMTP
        $mail->Host       = $this->host;                    // Set the SMTP server to send through
        $mail->SMTPAuth   = true;                           // Enable SMTP authentication
        $mail->Username   = $this->username;                // SMTP username
        $mail->Password   = $this->password;                // SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;    // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
        $mail->Port       = $this->port;                    // TCP port to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above
        $mail->CharSet    = PHPMailer::CHARSET_UTF8;

        //Recipients
        $mail->setFrom($this->from, $this->name);
        foreach ($this->sendTo as $mailer) {
            $mail->addAddress($mailer);
        }

        // Content
        $mail->isHTML(false);        // Set email format to HTML
        $mail->Subject = $this->subject;
        $mail->Body    = $this->content;
        $isSucc = $mail->send();
        $this->logger->info(sprintf("mail：%s, isSucc：%s", $this->content, $isSucc));
        return $isSucc;
    }
}
