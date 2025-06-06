<?php

namespace TechData\AS2SecureBundle\Events;

use Symfony\Contracts\EventDispatcher\Event;

class MessageBeforeSent extends Event
{
    public function __construct(
        private readonly string $content,
        private readonly string $messageId,
        private readonly string $sender,
        private readonly string $receiver
    ) {
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
