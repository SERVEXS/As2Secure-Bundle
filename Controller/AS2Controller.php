<?php

declare(strict_types=1);

namespace TechData\AS2SecureBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use TechData\AS2SecureBundle\Services\AS2;

class AS2Controller
{
    /**
     * @var AS2
     */
    private $as2Service;

    function __construct(AS2 $as2Service)
    {
        $this->as2Service = $as2Service;
    }


    public function inboundAction(Request $request)
    {
        $this->as2Service->handleRequest($request);
    }
}
