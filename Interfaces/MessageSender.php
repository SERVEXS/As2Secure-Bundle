<?php
/**
 * Created by PhpStorm.
 * User: westin
 * Date: 3/15/2015
 * Time: 8:26 PM
 */

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
    public function sendMessage($toPartner, $fromPartner, $messageContent, $messageSubject = null);
}