<?php

namespace TechData\AS2SecureBundle\Events;


use Symfony\Contracts\EventDispatcher\Event;

class MessageBeforeSent extends Event
{

    private string $content;

    private string $messageId;

    private string $sender;

    private string $receiver;

    public function __construct(string $content, string $messageId, string $sender, string $receiver)
    {
        $this->content = $content;
        $this->messageId = $messageId;
        $this->sender = $sender;
        $this->receiver = $receiver;
    }

    public function getContent(): string
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
