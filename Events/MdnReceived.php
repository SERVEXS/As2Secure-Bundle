<?php

declare(strict_types=1);

namespace TechData\AS2SecureBundle\Events;

use Symfony\Component\EventDispatcher\Event;
use TechData\AS2SecureBundle\Models\MDN;

class MdnReceived extends Event
{
    private MDN $mdn;

    public function __construct(MDN $mdn)
    {
        $this->mdn = $mdn;
    }
    
    public function getMdn(): MDN
    {
        return $this->mdn;
    }
}
