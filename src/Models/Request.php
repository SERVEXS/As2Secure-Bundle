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
 * @version 0.8.2
 *
 */

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use TechData\AS2SecureBundle\Events\Log;
use TechData\AS2SecureBundle\Factories\MDN as MDNFactory;
use TechData\AS2SecureBundle\Factories\Message as MessageFactory;
use TechData\AS2SecureBundle\Models\Horde\MIME\Structure;
use TechData\AS2SecureBundle\Models\Mail\MimeDecode;

class Request extends AbstractBase
{
    // Injected Services
    protected $request;

    public function __construct(
        private readonly MDNFactory $mdnFactory,
        private readonly MessageFactory $messageFactory,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function initialize($content, $headers)
    {
        // build params to match parent::__construct
        $this->headers = $headers;
        $mimetype = $this->getHeader('content-type');
        if (($pos = strpos((string) $mimetype, ';')) !== false) {
            $mimetype = substr((string) $mimetype, 0, $pos);
        }
        $params = [
            'partner_from' => $this->getHeader('as2-from'),
            'partner_to' => $this->getHeader('as2-to'),
            'mimetype' => $mimetype,
            'is_file' => false,
        ];

        // content is stored into new file
        $this->initializeBase($content, $params);

        $message_id = $this->getHeader('message-id');
        $message_id = str_replace(['<', '>'], '', (string) $message_id);
        $this->setMessageId($message_id);
    }

    /**
     * @Return a file with the decrypted content
     *         false if the original document wasn't crypted
     */
    public function decrypt()
    {
        $mimetype = $this->getHeader('content-type');
        if (($pos = strpos((string) $mimetype, ';')) !== false) {
            $mimetype = trim(substr((string) $mimetype, 0, $pos));
        }

        if ($mimetype === 'application/pkcs7-mime' || $mimetype === 'application/x-pkcs7-mime') {
            $content = $this->getHeaders(true) . "\n\n";
            $content .= file_get_contents($this->getPath());

            $input = Adapter::getTempFilename();
            $mime_part = Structure::parseTextMIMEMessage($content);
            file_put_contents($input, $mime_part->toString(true));

            // get input file and returns decrypted file
            // throw an exception on error
            return $this->adapter->decrypt($input);
        }

        return false;
    }

    public function getObject()
    {
        // setup of full message
        $content = $this->getHeaders(true) . "\n\n";
        $content .= file_get_contents($this->getPath());
        $input = Adapter::getTempFilename();
        file_put_contents($input, $content);

        // setup of mailmime decoder
        $params = [
            'include_bodies' => false,
            'decode_headers' => true,
            'decode_bodies' => false,
            'input' => false,
            // 'crlf'           => "\n"
        ];
        $decoder = new MimeDecode(file_get_contents($input));
        $structure = $decoder->decode($params);
        $mimetype = $structure->ctype_primary . '/' . $structure->ctype_secondary;

        // handle crypted content
        $crypted = false;
        if (strtolower($mimetype) === 'application/pkcs7-mime') {
            try {
                // rewrite message into base64 encoding
                $content = file_get_contents($input);
                $mime_part = Structure::parseTextMIMEMessage($content);
                $input = Adapter::getTempFilename();
                file_put_contents($input, $mime_part->toString(true));

                $this->eventDispatcher->dispatch(new Log(Log::TYPE_INFO, 'AS2 message is encrypted.'));
                $input = $this->adapter->decrypt($input);
                $this->eventDispatcher->dispatch(
                    new Log(Log::TYPE_INFO, 'The data has been decrypted using the key "' . $this->getPartnerTo() . '".')
                );
                $crypted = true;

                // reload extracted content to get mimetype
                $decoder = new MimeDecode(file_get_contents($input));
                $structure = $decoder->decode($params);
                $mimetype = $structure->ctype_primary . '/' . $structure->ctype_secondary;
            } catch (\Exception $e) {
                throw new AS2Exception($e->getMessage(), 3);
            }
        }

        // handle signed content
        $signed = false;
        $mic = false;
        if (strtolower($mimetype) === 'multipart/signed') {
            try {
                $this->eventDispatcher->dispatch(new Log(Log::TYPE_INFO, 'AS2 message is signed.'));

                // get MicChecksum from signature
                $mic = $this->adapter->getMicChecksum($input);

                $input = $this->adapter->verify($input);
                $signed = true;

                $this->eventDispatcher->dispatch(
                    new Log(
                        Log::TYPE_INFO,
                        'The sender used the algorithm "' . $structure->ctype_parameters['micalg'] . '" to sign the message.'
                    )
                );

                // reload extracted content to get mimetype
                $decoder = new MimeDecode(file_get_contents($input));
                $structure = $decoder->decode($params);
                $mimetype = $structure->ctype_primary . '/' . $structure->ctype_secondary;

                $this->eventDispatcher->dispatch(
                    new Log(Log::TYPE_INFO, 'Using certificate "' . $this->getPartnerFrom() . '" to verify signature.')
                );
            } catch (\Exception $e) {
                throw new AS2Exception($e->getMessage(), 5);
            }
        } else {
            // check requested algo
            $mic = Adapter::calculateMicChecksum($input);
        }

        // security check
        if (strtolower($mimetype) === 'multipart/report') {
            // check about sign
            /*if ($this->getPartnerFrom()->sec_signature_algorithm == Partner::SIGN_NONE && !$this->getPartnerFrom()->mdn_signed && $signed){
                throw new AS2Exception('AS2 message is signed and shouldn\'t be.', 4);
            }
            else*/
            if ($this->getPartnerFrom()->sec_signature_algorithm != Partner::SIGN_NONE && $this->getPartnerFrom(
            )->mdn_signed && !$signed) {
                throw new AS2Exception('AS2 message is not signed and should be.', 4);
            }
        } else {
            // check about crypt
            /*if ($this->getPartnerFrom()->sec_encrypt_algorithm == Partner::CRYPT_NONE && $crypted){
                throw new AS2Exception('AS2 message is crypted and shouldn\'t be.', 4);
            }
            else*/
            if ($this->getPartnerFrom()->sec_encrypt_algorithm != Partner::CRYPT_NONE && !$crypted) {
                throw new AS2Exception('AS2 message is not crypted and should be.', 4);
            }

            // check about sign
            /*if ($this->getPartnerFrom()->sec_signature_algorithm == Partner::SIGN_NONE && $signed){
                throw new AS2Exception('AS2 message is signed and shouldn\'t be.', 4);
            }
            else*/
            if ($this->getPartnerFrom()->sec_signature_algorithm != Partner::SIGN_NONE && !$signed) {
                throw new AS2Exception('AS2 message is not signed and should be.', 4);
            }
        }

        try {
            // build object with extracted content
            $message = file_get_contents($input);
            $mime_part = Structure::parseTextMIMEMessage($message);

            switch (strtolower($mimetype)) {
                case 'multipart/report':
                    $params = [
                        'partner_from' => $this->getPartnerTo(),
                        'partner_to' => $this->getPartnerFrom(),
                        'is_file' => false,
                        'mic' => $mic,
                    ];

                    return $this->mdnFactory->build($mime_part, $params);

                default:
                    $params = [
                        'partner_from' => $this->getPartnerFrom(),
                        'partner_to' => $this->getPartnerTo(),
                        'is_file' => false,
                        'mic' => $mic,
                    ];
                    $object = $this->messageFactory->build($mime_part, $params);
                    $object->setHeaders($this->getHeaders());

                    return $object;
            }
        } catch (\Exception $e) {
            throw new AS2Exception($e->getMessage(), 6);
        }
    }

    public function encode(): bool
    {
        throw new AS2Exception('This method is not available.');
    }

    public function decode(): bool
    {
        throw new AS2Exception('This method is not available.');
    }
}
