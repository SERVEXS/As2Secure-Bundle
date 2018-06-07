<?php

namespace TechData\AS2SecureBundle\Events;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use TechData\AS2SecureBundle\Models\Request;

class IncomingAs2Request extends Event
{
    const EVENT = 'INCOMING_AS2_REQUEST';

    /**
     * @var Request
     */
    private $as2Request;

    /**
     * @param Request $as2Request
     */
    public function __construct(Request $as2Request)
    {
        $this->as2Request = $as2Request;
    }

    /**
     * @return Request
     */
    public function getAs2Request(): Request
    {
        return $this->as2Request;
    }
}
