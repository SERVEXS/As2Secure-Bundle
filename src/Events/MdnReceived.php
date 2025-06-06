<?php

declare(strict_types=1);

namespace TechData\AS2SecureBundle\Events;

use Symfony\Contracts\EventDispatcher\Event;
use TechData\AS2SecureBundle\Models\MDN;

class MdnReceived extends Event
{
    public function __construct(private readonly MDN $mdn)
    {
    }

    public function getMdn(): MDN
    {
        return $this->mdn;
    }
}
