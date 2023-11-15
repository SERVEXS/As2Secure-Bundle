<?php

namespace TechData\AS2SecureBundle\Events;

use Symfony\Contracts\EventDispatcher\Event;
use TechData\AS2SecureBundle\Models\Message;

class OutgoingMessage extends Event
{
    public function __construct(private readonly Message $message, private readonly string $messageContents)
    {
    }

    public function getMessage(): Message
    {
        return $this->message;
    }

    public function getMessageContents(): string
    {
        return $this->messageContents;
    }
}
