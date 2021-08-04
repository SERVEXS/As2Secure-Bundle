<?php

namespace TechData\AS2SecureBundle\Events;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class MessageReceived extends Event
{
    /**
     * @var string
     */
    private $message;

    /**
     * @var string
     */
    private $sendingPartnerId;

    /**
     * @var string
     */
    private $receivingPartnerId;

    /**
     * @var string
     */
    private $messageId;

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
    public function setMessageId($messageId)
    {
        $this->messageId = $messageId;

        return $this;
    }

    /**
     * @return string
     */
    public function getSendingPartnerId(): string
    {
        return $this->sendingPartnerId;
    }

    /**
     * @param string $sendingPartnerId
     * @return MessageReceived
     */
    public function setSendingPartnerId(string $sendingPartnerId): MessageReceived
    {
        $this->sendingPartnerId = $sendingPartnerId;
        return $this;
    }

    /**
     * @return string
     */
    public function getReceivingPartnerId(): string
    {
        return $this->receivingPartnerId;
    }

    /**
     * @param string $receivingPartnerId
     *
     * @return MessageReceived
     */
    public function setReceivingPartnerId(string $receivingPartnerId): MessageReceived
    {
        $this->receivingPartnerId = $receivingPartnerId;
        return $this;
    }

    /**
     * @param $message
     *
     * @return $this
     */
    public function setMessage($message)
    {
        $this->message = $message;

        return $this;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }
}
