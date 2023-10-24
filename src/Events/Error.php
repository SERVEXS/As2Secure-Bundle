<?php

namespace TechData\AS2SecureBundle\Events;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Description of Error
 *
 * @author wpigott
 */
class Error extends Event
{
    public const EVENT = 'ERROR';
}
