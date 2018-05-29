<?php

namespace TechData\AS2SecureBundle\Events;

use Symfony\Component\EventDispatcher\Event;

/**
 * Description of MessageSent
 *
 * @author wpigott
 */
class MessageSent extends Event {
    const EVENT = 'MESSAGE_SENT';

    private $message;
    private $messageType;
    private $headers = array();
    private $messageId;

    /**
     * @return string
     */
    public function getMessageId()
    {
        return $this->messageId;
    }

    /**
     * @param string $messageId
     */
    public function setMessageId($messageId)
    {
        $this->messageId = $messageId;
    }

    public function getMessage() {
        return $this->message;
    }

    public function setMessage($message) {
        $this->message = $message;
    }

    public function getMessageType() {
        return $this->messageType;
    }

    public function getHeaders() {
        return $this->headers;
    }

    public function setMessageType($messageType) {
        $this->messageType = $messageType;
    }

    public function setHeaders($headers) {
        $this->headers = $headers;
    }
}
