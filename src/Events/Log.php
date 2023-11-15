<?php

namespace TechData\AS2SecureBundle\Events;

use Symfony\Contracts\EventDispatcher\Event;

class Log extends Event
{
    final public const TYPE_INFO = 'info';

    final public const TYPE_WARN = 'warning';

    final public const TYPE_ERROR = 'error';

    public function __construct(private readonly string $type, private readonly string $message)
    {
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getType(): string
    {
        return $this->type;
    }
}
