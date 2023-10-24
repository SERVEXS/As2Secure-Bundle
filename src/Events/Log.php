<?php

namespace TechData\AS2SecureBundle\Events;

use Symfony\Contracts\EventDispatcher\Event;

class Log extends Event
{
    public const TYPE_INFO = 'info';

    public const TYPE_WARN = 'warning';

    public const TYPE_ERROR = 'error';

    private string $message;

    private string $type;

    public function __construct(string $type, string $message)
    {
        $this->type = $type;
        $this->message = $message;
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
