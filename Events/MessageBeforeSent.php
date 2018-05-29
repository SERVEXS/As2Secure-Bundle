<?php

namespace TechData\AS2SecureBundle\Events;

use Symfony\Component\EventDispatcher\Event;

class MessageBeforeSent extends Event {

    const EVENT = 'MESSAGE_BEFORE_SENT';

    /**
     * @param string $content
     * @param string $messageId
     * @param string $sender
     * @param string $receiver
     */
    public function __construct($content, $messageId, $sender, $receiver)
    {
        $this->content = $content;
        $this->messageId = $messageId;
        $this->sender = $sender;
        $this->receiver = $receiver;
    }

    /**
     * @var string
     */
    private $content;

    /**
     * @var string
     */
    private $messageId;

    /**
     * @var string
     */
    private $sender;

    /**
     * @var string
     */
    private $receiver;
}
