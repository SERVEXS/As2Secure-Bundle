<?php

namespace TechData\AS2SecureBundle\Events;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use TechData\AS2SecureBundle\Models\Message;
use TechData\AS2SecureBundle\Models\Request;

class OutgoingMessage extends Event
{
    /**
     * @var Message
     */
    private $message;
    /**
     * @var string
     */
    private $messageContents;

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
