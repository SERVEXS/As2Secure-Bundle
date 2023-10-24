<?php

namespace TechData\AS2SecureBundle\Events;

use Symfony\Contracts\EventDispatcher\Event;

class MessageReceived extends Event
{
    private string $message;

    private string $sendingPartnerId;

    private string $receivingPartnerId;

    private string $messageId;

    /**
     * @return int
     */
    public function getMessageId()
    {
        return $this->messageId;
    }

    /**
     * @param string $messageId
     *
     * @return MessageReceived
     */
    public function setMessageId($messageId): MessageReceived
    {
        $this->messageId = $messageId;

        return $this;
    }

    public function getSendingPartnerId(): string
    {
        return $this->sendingPartnerId;
    }

    public function setSendingPartnerId(string $sendingPartnerId): MessageReceived
    {
        $this->sendingPartnerId = $sendingPartnerId;

        return $this;
    }

    public function getReceivingPartnerId(): string
    {
        return $this->receivingPartnerId;
    }

    public function setReceivingPartnerId(string $receivingPartnerId): MessageReceived
    {
        $this->receivingPartnerId = $receivingPartnerId;

        return $this;
    }

    public function setMessage($message): MessageReceived
    {
        $this->message = $message;

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
