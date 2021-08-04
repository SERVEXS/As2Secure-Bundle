<?php

declare(strict_types=1);

namespace TechData\AS2SecureBundle\Interfaces;

interface Events
{
    public const LOG = 'tech_data_as2_secure.event.log';
    public const ERROR = 'tech_data_as2_secure.event.error';
    public const MESSAGE_RECEIVED = 'tech_data_as2_secure.event.message_received';
    public const MESSAGE_SENT = 'tech_data_as2_secure.event.message_sent';
    public const INCOMING_AS2_REQUEST = 'tech_data_as2_secure.event.incoming_as2_request';
    public const OUTGOING_MESSAGE = 'tech_data_as2_secure.event.outgoing_message';
    public const MDN_RECEIVED = 'tech_data_as2_secure.event.mdn_received';
}