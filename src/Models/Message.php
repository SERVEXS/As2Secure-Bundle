<?php

namespace TechData\AS2SecureBundle\Models;

/*
 * AS2Secure - PHP Lib for AS2 message encoding / decoding
 *
 * @author  Sebastien MALOT <contact@as2secure.com>
 *
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
 *
 * @license http://www.gnu.org/licenses/lgpl-3.0.html GNU General Public License
 * @version 0.9.0
 *
 */

use TechData\AS2SecureBundle\Factories\MDN as MDNFactory;
use TechData\AS2SecureBundle\Models\Horde\MIME\Part;

class Message extends AbstractBase
{
    private $messageSubject;

    protected $mic_checksum = false;

    public function __construct(private readonly MDNFactory $mdnFactory)
    {
    }

    public function initialize($data, $params = [])
    {
        $this->initializeBase($data, $params);

        if (isset($params['message_subject'])) {
            $this->messageSubject = $params['message_subject'];
        }

        if ($data instanceof Request) {
            $this->path = $data->getPath();
        } elseif ($data instanceof Part) {
            $this->path = Adapter::getTempFilename();
            file_put_contents($this->path, $data->toString());
        } elseif ($data) {
            if (!isset($params['is_file']) || $params['is_file']) {
                $this->addFile($data);
            } else {
                $this->addFile($data, '', '', false);
            }
        }

        if (isset($params['mic'])) {
            $this->mic_checksum = $params['mic'];
        }
    }

    /**
     * Add file to the message
     *
     * @param string $data The content or the file
     * @param string $mimetype The mimetype of the message
     * @param bool $is_file If file
     * @param string $encoding The encoding to use for transfert
     */
    public function addFile($data, string $mimetype = '', ?string $filename = '', bool $is_file = true, string $encoding = 'base64'): void
    {
        if (!$is_file) {
            $file = Adapter::getTempFilename();
            file_put_contents($file, $data);
            $data = $file;
            $is_file = true;
        } else {
            if (!$filename) {
                $filename = basename($data);
            }
        }

        if (!$mimetype) {
            $mimetype = Adapter::detectMimeType($data);
        }

        $this->files[] = [
            'path' => $data,
            'mimetype' => $mimetype,
            'filename' => $filename,
            'encoding' => $encoding,
        ];
    }

    /**
     * Return the last calculated checksum
     *
     * @return string
     */
    public function getMicChecksum()
    {
        return $this->mic_checksum;
    }

    /**
     * Return the url to send message
     */
    public function getUrl(): string
    {
        return $this->getPartnerTo()->send_url;
    }

    /**
     * Return the authentication to use to send message to the partner
     */
    public function getAuthentication(): array
    {
        return [
            'method' => $this->getPartnerTo()->send_credencial_method,
            'login' => $this->getPartnerTo()->send_credencial_login,
            'password' => $this->getPartnerTo()->send_credencial_password,
        ];
    }

    /**
     * Build message and encode it (signing and/or crypting)
     */
    public function encode(): bool
    {
        if (!$this->getPartnerFrom() instanceof Partner || !$this->getPartnerTo() instanceof Partner) {
            throw new AS2Exception('Object not properly initialized');
        }

        // initialisation
        $this->mic_checksum = false;
        $this->setMessageId(self::generateMessageID($this->getPartnerFrom()));

        // chargement et construction du message
        $files = $this->getFiles();

        // initial message creation : mime_part
        // TODO : use adapter to build multipart file
        // managing all files (parts)
        $parts = [];
        foreach ($files as $file) {
            $mime_part = new Part($file['mimetype']);
            $mime_part->setContents(file_get_contents($file['path']));
            $mime_part->setName($file['filename']);
            if ($file['encoding']) {
                $mime_part->setTransferEncoding($file['encoding']);
            }

            $parts[] = $mime_part;
        }
        if (count($parts) > 1) {
            // handling multipart file
            $mime_part = new Part('multipart/mixed');
            foreach ($parts as $part) {
                $mime_part->addPart($part);
            }
        } else {
            // handling mono part (body)
            $mime_part = $parts[0];
        }

        $file = Adapter::getTempFilename();
        file_put_contents($file, $mime_part->toString());

        // signing file if wanted by Partner_To
        if ($this->getPartnerTo()->sec_signature_algorithm != Partner::SIGN_NONE) {
            $file = $this->adapter->sign($file, $this->getPartnerTo()->send_compress, $this->getPartnerTo()->send_encoding);
            $this->is_signed = true;

            // echo file_get_contents($file);
            $this->mic_checksum = $this->getMicChecksum();
        }

        // crypting file if wanted by Partner_To
        if ($this->getPartnerTo()->sec_encrypt_algorithm != Partner::CRYPT_NONE) {
            $file = $this->adapter->encrypt($file);
            $this->is_crypted = true;
        }

        $this->path = $file;
        /*if ($mime_part->getTransferEncoding() == 'base64'){
            file_put_contents($this->path, base64_decode($mime_part->toString(false)));
        }
        else{
            file_put_contents($this->path, $mime_part->toString());
        }*/
        // headers setup

        $messageSubject = $this->messageSubject;
        if ($messageSubject == null) {
            $messageSubject = $this->getPartnerFrom()->send_subject;
        }

        $headers = [
            'AS2-From' => $this->getPartnerFrom()->id,
            'AS2-To' => $this->getPartnerTo()->id,
            'AS2-Version' => '1.0',
            'From' => $this->getPartnerFrom()->email,
            'Subject' => $messageSubject,
            'Message-ID' => $this->getMessageId(),
            'Mime-Version' => '1.0',
            'Disposition-Notification-To' => $this->getPartnerFrom()->email,
            'Recipient-Address' => $this->getPartnerTo()->send_url,
            'User-Agent' => 'SupportPlaza AS2Secure HTTP Client',
        ];

        if ($this->getPartnerTo()->mdn_signed) {
            $headers['Disposition-Notification-Options'] = 'signed-receipt-protocol=optional, pkcs7-signature; signed-receipt-micalg=optional, sha1';
        }

        if ($this->getPartnerTo()->mdn_request == Partner::ACK_ASYNC) {
            $headers['Receipt-Delivery-Option'] = $this->getPartnerFrom()->send_url;
        }

        $this->headers = new Header($headers);

        // look for additionnal headers from message
        // eg : content-type
        $content = file_get_contents($this->path);
        $this->headers->addHeadersFromMessage($content);
        if (str_contains($content, "\n\n")) {
            $content = substr($content, strpos($content, "\n\n") + 2);
        }
        file_put_contents($this->path, $content);

        return true;
    }

    /**
     * Decode message extracting files from message
     */
    public function decode(): bool
    {
        $this->files = $this->adapter->extract($this->getPath());

        return true;
    }

    /**
     * Generate an MDN from the message
     */
    public function generateMDN(?\Throwable $exception = null): MDN
    {
        $mdn = $this->mdnFactory->build($this);

        $message_id = $this->getHeader('message-id');
        $partner = $this->getPartnerTo()->id;
        $mic = $this->getMicChecksum();

        $mdn->setAttribute('Original-Recipient', 'rfc822; "' . $partner . '"');
        $mdn->setAttribute('Final-Recipient', 'rfc822; "' . $partner . '"');
        $mdn->setAttribute('Original-Message-ID', $message_id);
        if ($mic) {
            $mdn->setAttribute('Received-Content-MIC', $mic);
        }

        if (is_null($exception)) {
            $mdn->setMessage('The AS2 message has been received.');
            $mdn->setAttribute('Disposition-Type', 'processed');
        } else {
            if (!$exception instanceof AS2Exception) {
                $exception = new AS2Exception($exception->getMessage());
            }

            $mdn->setMessage($exception->getMessage());
            $mdn->setAttribute('Disposition-Type', 'failure');
            $mdn->setAttribute('Disposition-Modifier', $exception->getLevel() . ': ' . $exception->getMessageShort());
        }

        return $mdn;
    }
}
