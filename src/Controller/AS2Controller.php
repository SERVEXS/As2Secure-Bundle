<?php

declare(strict_types=1);

namespace TechData\AS2SecureBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use TechData\AS2SecureBundle\Services\AS2;

class AS2Controller
{
    public function __construct(private readonly AS2 $as2Service)
    {
    }

    public function inboundAction(Request $request)
    {
        $this->as2Service->handleRequest($request);
    }
}
