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

use ArrayAccess;
use Iterator;

class Header implements \Countable, \ArrayAccess, \Iterator, \Stringable
{
    protected array $headers = [];

    protected $_position;

    /**
     * @param Header|array $data
     */
    public function __construct($data = null)
    {
        if (is_array($data)) {
            $this->headers = $data;
        } elseif ($data instanceof self) {
            $this->headers = $data->getHeaders();
        }
    }

    /**
     * Reset all current headers with new values
     */
    public function setHeaders(array $headers): void
    {
        $this->headers = [];
        $this->addHeaders($headers);
    }

    /**
     * Add new header (or override current one)
     *
     * @param string $key The name of the header
     * @param string $value The value of the header
     */
    public function addHeader($key, $value): void
    {
        $this->headers[$key] = (string) $value;
    }

    /**
     * Add a set of headers (or override currents)
     */
    public function addHeaders($values): void
    {
        foreach ($values as $key => $value) {
            $this->addHeader($key, $value);
        }
    }

    /**
     * Add a set of headers extracted from a mime message
     *
     * @param string $message The message content to use
     */
    public function addHeadersFromMessage($message): void
    {
        $headers = $this->parseText($message);
        if (count($headers)) {
            foreach ($headers as $key => $value) {
                $this->addHeader($key, $value);
            }
        }
    }

    /**
     * Remove an header
     *
     * @param string $key The name of the header
     */
    public function removeHeader($key): void
    {
        unset($this->headers[$key]);
    }

    /**
     * Return all headers as an array
     *
     * @return array   The headers, eg: array(name1 => value1, name2 => value2)
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Return all headers as a formatted array
     *
     * @return array   The headers, eg: array(0 => name1:value1, 1 => name2:value2)
     */
    public function toFormattedArray(): array
    {
        $tmp = [];
        foreach ($this->headers as $key => $val) {
            $tmp[] = $key . ': ' . $val;
        }

        return $tmp;
    }

    /**
     * Return the value of an header
     *
     * @param string $key The header
     *
     * @return string        The value corresponding
     */
    public function getHeader($key)
    {
        $key = strtolower($key);
        $tmp = array_change_key_case($this->headers);
        if (isset($tmp[$key])) {
            return trim((string) $tmp[$key], '"');
        }

        return false;
    }

    /**
     * Return the count of headers
     */
    public function count(): int
    {
        return count($this->headers);
    }

    /**
     * Check if an header exists
     *
     * @param string $key The header to check existance
     */
    public function exists($key): bool
    {
        $tmp = array_change_key_case($this->headers);

        return array_key_exists(strtolower($key), $tmp);
    }

    /**
     * Magic method that returns headers serialized as in mime message
     */
    public function __toString(): string
    {
        $ret = '';

        foreach ($this->headers as $key => $value) {
            $ret .= $key . ': ' . $value . "\n";
        }

        return rtrim($ret);
    }

    /** ArrayAccess interface **/
    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->headers);
    }

    public function offsetGet($offset): mixed
    {
        return $this->headers[$offset];
    }

    public function offsetSet($offset, $value): void
    {
        $this->headers[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->headers[$offset]);
    }

    /** Iterator interface **/
    public function current(): mixed
    {
        return $this->headers[$this->key()];
    }

    public function key(): mixed
    {
        $keys = array_keys($this->headers);

        return $keys[$this->_position];
    }

    public function next(): void
    {
        ++$this->_position;
    }

    public function rewind(): void
    {
        $this->_position = 0;
    }

    public function valid(): bool
    {
        return $this->_position >= 0 && $this->_position < count($this->headers);
    }

    /**
     * Extract headers from mime message and return a new instance of Header
     *
     * @param string $text  The content to parse
     */
    protected function parseText(string $text): array
    {
        if (str_contains($text, "\n\n")) {
            $text = substr($text, 0, strpos($text, "\n\n"));
        }
        $text = rtrim($text) . "\n";

        $matches = [];
        preg_match_all('/(.*?):\s*(.*?\n(\s.*?\n)*)/', $text, $matches);
        if ($matches) {
            foreach ($matches[2] as &$value) {
                $value = trim(str_replace(["\r", "\n"], ' ', (string) $value));
            }
            unset($value);
            if (count($matches[1]) && count($matches[1]) === count($matches[2])) {
                return array_combine($matches[1], $matches[2]);
            }
        }

        return [];
    }
}
