<?php

declare(strict_types=1);

namespace TechData\AS2SecureBundle\Events;

use Symfony\Component\EventDispatcher\Event;
use TechData\AS2SecureBundle\Models\Message;

class MessageSent extends Event
{
    /**
     * @var Message
     */
    private $message;

    /**
     * @var array
     */
    private $headers = [];

    public function __construct(Message $message, array $headers)
    {
        $this->headers = $headers;
        $this->message = $message;
    }

    public function getMessage(): Message
    {
        return $this->message;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }
}
