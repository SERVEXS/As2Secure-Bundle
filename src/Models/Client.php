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

use TechData\AS2SecureBundle\Factories\Request as RequestFactory;

class Client
{
    private RequestFactory $requestFactory;

    protected array $responseHeaders = [];
    protected string $responseContent = '';
    protected int $responseIndice = 0;

    public function __construct(RequestFactory $requestFactory)
    {
        $this->requestFactory = $requestFactory;
    }

    /**
     * Send request to the partner (manage headers, security, ...)
     *
     * @param AbstractBase $request The request to send (instanceof : AS2Message | AS2MDN)
     *
     * @return array{
     *     request: AbstractBase,
     *     headers: array,
     *     response: MDN|Message|null,
     *     info: mixed
     * }
     * @throws AS2Exception
     */
    public function sendRequest($request): array
    {
        if (!$request instanceof Message && !$request instanceof MDN) {
            throw new AS2Exception('Unexpected format');
        }

        // format headers
        $headers = $request->getHeaders()->toFormattedArray();

        // initialize variables for building response headers with curl
        $this->responseHeaders = [];
        $this->responseContent = '';
        $this->responseIndice = 0;

        // send as2 message with headers
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $request->getUrl());
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request->getContent());
        curl_setopt($ch, CURLOPT_USERAGENT, 'SupportPlaza AS2Secure HTTP Client');
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, [$this, 'handleResponseHeader']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        // authentication setup
        $auth = $request->getAuthentication();
        if ($auth['method'] !== Partner::METHOD_NONE) {
            curl_setopt($ch, CURLOPT_HTTPAUTH, $auth['method']);
            curl_setopt($ch, CURLOPT_USERPWD, urlencode($auth['login']) . ':' . urlencode($auth['password']));
        }

        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        $error = curl_error($ch);
        curl_close($ch);

        $this->responseContent = $response;

        if ($info['http_code'] !== 200 && $info['http_code'] !== 202) {
            throw new AS2Exception('HTTP Error Code : ' . $info['http_code'] . '(url:' . $request->getUrl() . ').');
        }

        if ($error) {
            throw new AS2Exception($error);
        }

        $as2Response = null;

        if ($request instanceof Message && $request->getPartnerTo()->mdn_request === Partner::ACK_SYNC) {
            $tmpResponse = $this->requestFactory->build($response, new Header($this->responseHeaders[count($this->responseHeaders) - 1]));
            $as2Response = $tmpResponse->getObject();
            $as2Response->decode();
        }

        return [
            'request' => $request,
            'headers' => $this->responseHeaders[count($this->responseHeaders) - 1],
            'response' => $as2Response,
            'info' => $info,
        ];
    }

    public function getLastResponse(): array
    {
        return [
            'headers' => $this->responseHeaders[count($this->responseHeaders) - 1],
            'content' => $this->responseContent,
        ];
    }

    /**
     * Allow to retrieve HTTP headers even if there is HTTP redirections
     *
     * @param object $curl The curl instance
     * @param string $header The header received
     *
     * @return int              The length of current received header
     */
    protected function handleResponseHeader($curl, $header)
    {
        if (!trim($header) && isset($this->responseHeaders[$this->responseIndice]) && count($this->responseHeaders[$this->responseIndice])) {
            ++$this->responseIndice;
        } else {
            $pos = strpos($header, ':');
            if ($pos !== false) {
                $this->responseHeaders[$this->responseIndice][trim(strtolower(substr($header, 0, $pos)))] = trim(substr($header, $pos + 1));
            }
        }

        return strlen($header);
    }
}
