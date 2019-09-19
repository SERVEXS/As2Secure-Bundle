<?php
/**
 * Created by PhpStorm.
 * User: westin
 * Date: 3/15/2015
 * Time: 6:16 PM
 */

namespace TechData\AS2SecureBundle\Interfaces;

interface Events
{
    const LOG = 'tech_data_as2_secure.event.log';
    const ERROR = 'tech_data_as2_secure.event.error';
    const MESSAGE_RECIEVED = 'tech_data_as2_secure.event.message_received';
    const MESSAGE_SENT = 'tech_data_as2_secure.event.message_sent';
    const INCOMING_AS2_REQUEST = 'tech_data_as2_secure.event.incoming_as2_request';
    const OUTGOING_MESSAGE = 'tech_data_as2_secure.event.outgoing_message';
}