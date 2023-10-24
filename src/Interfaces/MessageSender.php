<?php

namespace TechData\AS2SecureBundle\Interfaces;

interface MessageSender
{
    /**
     * @param $toPartner
     * @param $fromPartner
     * @param $messageContent
     * @param null $messageSubject
     *
     * @return
     */
    public function sendMessage($toPartner, $fromPartner, $messageContent, $messageSubject = null, $filename = null);
}