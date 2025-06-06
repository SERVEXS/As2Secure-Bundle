<?php

namespace TechData\AS2SecureBundle\Models;

use TechData\AS2SecureBundle\Models\Horde\MIME\Part;
use TechData\AS2SecureBundle\Models\Mail\MimeDecode;

/**
 * AS2Secure - PHP Lib for AS2 message encoding / decoding
 *
 * @author  Sebastien MALOT <contact@as2secure.com>
 * @copyright Copyright (c) 2010, Sebastien MALOT
 *
 * Last release at : {@link http://www.as2secure.com}
 *
 * This file is part of AS2Secure Project.
 *
 * AS2Secure is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AS2Secure is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AS2Secure.
 * @license http://www.gnu.org/licenses/lgpl-3.0.html GNU General Public License
 *
 * @version 0.9.0
 */
class MDN extends AbstractBase implements \Stringable
{
    /**
     * Refers to RFC 4130
     * http://rfclibrary.hosting.com/rfc/rfc4130/rfc4130-34.asp
     */
    final public const ACTION_AUTO = 'automatic-action';
    final public const ACTION_MANUAL = 'manual-action';
    final public const SENDING_AUTO = 'MDN-sent-automatically';
    final public const SENDING_MANUAL = 'MDN-sent-manually';
    final public const TYPE_PROCESSED = 'processed';
    final public const TYPE_FAILED = 'failed';
    final public const MODIFIER_ERROR = 'error';
    final public const MODIFIER_WARNING = 'warning';
    /**
     * Human-readable message
     */
    protected string $message = '';
    protected string $url = '';
    /**
     * Valid tokens :
     *
     *    action-mode                    : "manual-action" | "automatic-action"
     *    sending-mode                   : "MDN-sent-manually" | "MDN-sent-automatically"
     *    disposition-type               : "processed" | "failed"
     *    disposition-modifier           : ( "error" | "warning" ) | disposition-modifier-extension
     *    disposition-modifier-extension : (cf : AS2Exception values)
     *    encoded-message-digest         : (base64 format + digest-alg-id = "sha1" | "md5")
     *    reporting-ua                   : user-agent
     */
    protected ?Header $attributes = null;

    public function __construct()
    {
    }

    /**
     * @throws AS2Exception
     */
    public function initialize($data = null, $params = []): void
    {
        $this->attributes = new Header(['action-mode' => self::ACTION_AUTO,
            'sending-mode' => self::SENDING_AUTO]);

        // adapter
        if (!($data instanceof AS2Exception) && $data instanceof \Exception) {
            $data = new AS2Exception($data->getMessage(), 6);
        }
        // full automatic handling
        if ($data instanceof AS2Exception) {
            $this->setMessage($data->getMessage());
            // $this->setHeaders($data->getHeaders());
            $this->setAttribute('disposition-type', $data->getLevel());
            $this->setAttribute('disposition-modifier', $data->getMessageShort());

            try {
                $this->setPartnerFrom($params['partner_from']);
            } catch (\Exception) {
                $this->partner_from = false;
            }
            try {
                $this->setPartnerTo($params['partner_to']);
            } catch (\Exception) {
                $this->partner_to = false;
            }
        } elseif ($data instanceof Request) { // parse response
            $params = [
                'is_file' => false,
                'mimetype' => 'multipart/report',
                'partner_from' => $data->getPartnerFrom(),
                'partner_to' => $data->getPartnerTo(),
            ];
            $this->initializeBase($data->getContent(), $params);

            // check requirements
            if ($this->partner_from->mdn_signed && !$data->isSigned()) {
                throw new AS2Exception('MDN from this partner are defined to be signed.', 4);
            }
        } elseif ($data instanceof Message) { // standard processed message
            $params['partner_from'] = $data->getPartnerTo();
            $params['partner_to'] = $data->getPartnerFrom();

            $this->initializeBase(false, $params);
        } elseif ($data instanceof Part) {
            try {
                $this->setPartnerFrom($params['partner_from']);
            } catch (\Exception) {
                $this->partner_from = false;
            }
            try {
                $this->setPartnerTo($params['partner_to']);
            } catch (\Exception) {
                $this->partner_to = false;
            }

            $this->path = Adapter::getTempFilename();
            file_put_contents($this->path, $data->toString(true));

            $this->initializeBase(false, $params);
        } else {
            throw new AS2Exception('Not handled case.');
        }
    }

    /**
     * Set attribute for computer readable message
     *
     * @param string $key Token
     * @param string $value Value
     */
    public function setAttribute($key, $value): void
    {
        $this->attributes->addHeader($key, $value);
    }

    public function __toString(): string
    {
        return $this->getMessage();
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): void
    {
        $this->message = $message;
    }

    public function getAttributes(): array
    {
        return $this->attributes->getHeaders();
    }

    /**
     * Encode and generate MDN from attributes and message (if exists)
     *
     * @param object|null $message The refering message
     */
    public function encode(?object $message = null)
    {
        // container
        $container = new Part('multipart/report', ' ');
        $container->setContentTypeParameter('report-type', 'disposition-notification');

        // first part
        $text = new Part('text/plain', $this->getMessage(), MIME_DEFAULT_CHARSET, null, '7bit');
        // add human readable message
        $container->addPart($text);

        // second part
        $lines = new Header();
        $lines->addHeader('Reporting-UA', 'Dimass SupportPlaza AS2Secure');
        if ($this->getPartnerFrom()) {
            $lines->addHeader('Original-Recipient', 'rfc822; "' . $this->getPartnerFrom()->id . '"');
            $lines->addHeader('Final-Recipient', 'rfc822; "' . $this->getPartnerFrom()->id . '"');
        }
        $lines->addHeader('Original-Message-ID', $this->getAttribute('original-message-id'));
        $lines->addHeader('Disposition', $this->getAttribute('action-mode') . '/' . $this->getAttribute('sending-mode') . '; ' . $this->getAttribute('disposition-type'));
        if ($this->getAttribute('disposition-type') != self::TYPE_PROCESSED) {
            $lines->addHeader('Disposition', $lines->getHeader('Disposition') . ': ' . $this->getAttribute('disposition-modifier'));
        }
        if ($this->getAttribute('received-content-mic')) {
            $lines->addHeader('Received-Content-MIC', $this->getAttribute('received-content-mic'));
        }

        // build computer readable message
        $mdn = new Part('message/disposition-notification', $lines, MIME_DEFAULT_CHARSET, null, '7bit');
        $container->addPart($mdn);

        $this->setMessageId(self::generateMessageID($this->getPartnerFrom()));

        // headers setup
        $this->headers = new Header(['AS2-Version' => '1.0',
            'Message-ID' => $this->getMessageId(),
            'Mime-Version' => '1.0',
            'Server' => 'Dimass SupportPlaza AS2Secure',
            'User-Agent' => 'Dimass SupportPlaza AS2Secure',
        ]);
        $this->headers->addHeaders($container->header());

        if ($this->getPartnerFrom()) {
            $headers_from = [
                'AS2-From' => '"' . $this->getPartnerFrom()->id . '"',
                'From' => $this->getPartnerFrom()->email,
                'Subject' => $this->getPartnerFrom()->mdn_subject,
                'Disposition-Notification-To' => $this->getPartnerFrom()->send_url,
            ];
            $this->headers->addHeaders($headers_from);
        }

        if ($this->getPartnerTo()) {
            $headers_to = [
                'AS2-To' => '"' . $this->getPartnerTo()->id . '"',
                'Recipient-Address' => $this->getPartnerTo()->send_url,
            ];
            $this->headers->addHeaders($headers_to);
        }

        if ($message && ($url = $message->getHeader('Receipt-Delivery-Option')) && $this->getPartnerFrom()) {
            $this->url = $url;
            $this->headers->addHeader('Recipient-Address', $this->getPartnerFrom()->send_url);
        }

        $this->path = Adapter::getTempFilename();

        // signing if requested
        if ($message && $message->getHeader('Disposition-Notification-Options')) {
            file_put_contents($this->path, $container->toCanonicalString());
            $this->path = $this->adapter->sign($this->path);

            $content = file_get_contents($this->path);
            $this->headers->addHeadersFromMessage($content);

            // TODO : replace with futur AS2MimePart to separate content from header
            if (str_contains($content, "\n\n")) {
                $content = substr($content, strpos($content, "\n\n") + 2);
            }
            file_put_contents($this->path, ltrim($content));
        } else {
            file_put_contents($this->path, $container->toCanonicalString(false));
            $content = $container->toString();
        }
    }

    /**
     * Return an attribute fromcomputer readable message
     *
     * @param string $key Token
     *
     * @return string
     */
    public function getAttribute($key)
    {
        if ($this->attributes === null) {
            return '';
        }

        return $this->attributes->getHeader($key);
    }

    /**
     * Decode MDN stored into path file and set attributes
     */
    public function decode()
    {
        // parse mime message
        $params = ['include_bodies' => true,
            'decode_headers' => true,
            'decode_bodies' => true,
            'input' => false,
            'crlf' => "\n",
        ];
        $decoder = new MimeDecode(file_get_contents($this->path));
        $structure = $decoder->decode($params);

        // reset values before decoding (for security reasons)
        $this->setMessage('');
        $this->attributes = null;

        // should contains 2 parts
        foreach ($structure->parts as $num => $part) {
            if (strtolower((string) $part->headers['content-type']) === 'message/disposition-notification') {
                // computer readable message
                /*preg_match_all('/([^: ]+): (.+?(?:\r\n\s(?:.+?))*)\r\n/m', $part->body, $headers);
                $headers = array_combine($headers[1], $headers[2]);
                foreach($headers as $key => $val)
                    $this->setAttribute(trim(strtolower($key)), trim($val));*/
                $headers = new Header();
                $headers->addHeadersFromMessage($part->body);
                $this->attributes = $headers;
            } else {
                // human readable message
                $this->setMessage(trim((string) $part->body));
            }
        }
    }

    /**
     * Return the url to send message
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }
}
