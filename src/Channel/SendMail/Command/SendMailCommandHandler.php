<?php

namespace Fluxlabs\FluxMailApi\Channel\SendMail\Command;

use Fluxlabs\FluxMailApi\Adapter\Api\AttachmentDto;
use Fluxlabs\FluxMailApi\Adapter\Config\SmtpConfigDto;
use PHPMailer\PHPMailer\PHPMailer;

class SendMailCommandHandler
{

    private SmtpConfigDto $smtp_config;


    public static function new(SmtpConfigDto $smtp_config) : static
    {
        $handler = new static();

        $handler->smtp_config = $smtp_config;

        return $handler;
    }


    public function handle(SendMailCommand $command) : void
    {
        $sender = null;
        try {
            $sender = new PHPMailer(true);

            $sender->isSMTP();
            $sender->Host = $this->smtp_config->getHost();
            $sender->Port = $this->smtp_config->getPort();

            if ($this->smtp_config->getEncryptionType() === SmtpConfigDto::ENCRYPTION_TYPE_TLS_AUTO) {
                $sender->SMTPSecure = SmtpConfigDto::ENCRYPTION_TYPE_SSL;
                $sender->SMTPAutoTLS = true;
            } else {
                $sender->SMTPSecure = $this->smtp_config->getEncryptionType();
                $sender->SMTPAutoTLS = false;
            }

            $sender->SMTPAuth = ($this->smtp_config->getAuthType() !== null || $this->smtp_config->getUserName() !== null || $this->smtp_config->getPassword() !== null);
            $sender->Username = $this->smtp_config->getUserName();
            $sender->Password = $this->smtp_config->getPassword();
            $sender->AuthType = $this->smtp_config->getAuthType();

            $sender->Subject = $command->getMail()->getSubject();

            $sender->isHTML();
            $sender->Body = $command->getMail()->getBodyHtml();
            $sender->AltBody = $command->getMail()->getBodyText();

            foreach ($command->getMail()->getTo() as $to) {
                $sender->addAddress($to->getEmail(), $to->getName());
            }

            foreach ($command->getMail()->getAttachments() as $attachment) {
                $data = $attachment->getData();
                switch ($attachment->getDataEncoding()) {
                    case AttachmentDto::DATA_ENCODING_BASE64:
                        $data = base64_decode($data);
                        break;

                    case AttachmentDto::DATA_ENCODING_PLAIN:
                    default:
                        break;
                }
                $sender->addStringAttachment($data, $attachment->getName(), PHPMailer::ENCODING_BASE64, $attachment->getDataType());
            }

            foreach ($command->getMail()->getReplyTo() as $reply_to) {
                $sender->addReplyTo($reply_to->getEmail(), $reply_to->getName());
            }

            foreach ($command->getMail()->getCc() as $cc) {
                $sender->addCC($cc->getEmail(), $cc->getName());
            }

            foreach ($command->getMail()->getBcc() as $bcc) {
                $sender->addBCC($bcc->getEmail(), $bcc->getName());
            }

            $from = $command->getMail()->getFrom() ?? $this->smtp_config->getDefaultFrom();
            $sender->setFrom($from->getEmail(), $from->getName());

            $sender->MessageDate = $command->getMail()->getTime()->format("D, j M Y H:i:s O");

            $sender->MessageID = $command->getMail()->getMessageId();

            $sender->send();
        } finally {
            if ($sender !== null) {
                $sender->smtpClose();
            }
        }
    }
}
