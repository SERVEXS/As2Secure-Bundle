<?php

namespace TechData\AS2SecureBundle\Events;

use Symfony\Contracts\EventDispatcher\Event;
use TechData\AS2SecureBundle\Models\Request;

class IncomingAs2Request extends Event
{
    final public const EVENT = 'INCOMING_AS2_REQUEST';

    public function __construct(private readonly Request $as2Request)
    {
    }

    public function getAs2Request(): Request
    {
        return $this->as2Request;
    }
}
