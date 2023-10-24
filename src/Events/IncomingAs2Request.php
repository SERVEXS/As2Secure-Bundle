<?php

namespace TechData\AS2SecureBundle\Events;

use Symfony\Contracts\EventDispatcher\Event;
use TechData\AS2SecureBundle\Models\Request;

class IncomingAs2Request extends Event
{
    public const EVENT = 'INCOMING_AS2_REQUEST';

    private Request $as2Request;

    public function __construct(Request $as2Request)
    {
        $this->as2Request = $as2Request;
    }

    public function getAs2Request(): Request
    {
        return $this->as2Request;
    }
}
