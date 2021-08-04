<?php

namespace TechData\AS2SecureBundle\Events;

use Symfony\Component\EventDispatcher\Event;

class MessageBeforeSent extends Event
{
    /**
     * @var string
     */
    private $content;

    /**
     * @var string
     */
    private $messageId;

    /**
     * @var string
     */
    private $sender;

    /**
     * @var string
     */
    private $receiver;

    public function __construct(strintg $content, string $messageId, string $sender, string $receiver)
    {
        $this->content = $content;
        $this->messageId = $messageId;
        $this->sender = $sender;
        $this->receiver = $receiver;
    }

    public function getContent()
    {
        return $this->content;
    }

    public function getMessageId(): string
    {
        return $this->messageId;
    }

    public function getSender(): string
    {
        return $this->sender;
    }

    public function getReceiver(): string
    {
        return $this->receiver;
    }
}
