<?php

namespace TechData\AS2SecureBundle\Interfaces;

interface MessageSender
{
    /**
     * @param null $messageSubject
     */
    public function sendMessage($toPartner, $fromPartner, $messageContent, $messageSubject = null, $filename = null);
}
