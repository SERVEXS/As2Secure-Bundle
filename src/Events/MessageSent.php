<?php

declare(strict_types=1);

namespace TechData\AS2SecureBundle\Events;

use Symfony\Contracts\EventDispatcher\Event;
use TechData\AS2SecureBundle\Models\Message;

class MessageSent extends Event
{
    public function __construct(private readonly Message $message, private readonly array $headers)
    {
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
