<?php

namespace TechData\AS2SecureBundle\Events;

use Symfony\Contracts\EventDispatcher\Event;
use TechData\AS2SecureBundle\Models\Message;

class OutgoingMessage extends Event
{
    private Message $message;

    private string $messageContents;

    public function __construct(Message $message, string $messageContents)
    {
        $this->message = $message;
        $this->messageContents = $messageContents;
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
