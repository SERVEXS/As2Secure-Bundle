<?php

namespace TechData\AS2SecureBundle\Models\Horde\MIME;

use TechData\AS2SecureBundle\Models\Horde\MIME;

/**
 * The MIME_Message:: class provides methods for creating and manipulating
 * MIME email messages.
 *
 * $Horde: framework/MIME/MIME/Message.php,v 1.76.10.19 2009/01/06 15:23:20 jan Exp $
 *
 * Copyright 1999-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @author  Michael Slusarz <slusarz@horde.org>
 */
class Message extends Part
{
    /**
     * Has the message been parsed via buildMessage()?
     */
    protected bool $_build = false;

    /**
     * The server to default unqualified addresses to.
     */
    protected ?string $_defaultServer = null;

    /**
     * Constructor - creates a new MIME email message.
     *
     * @param string|null $defaultServer the server to default unqualified
     *                               addresses to
     */
    public function __construct(?string $defaultServer = null)
    {
        parent::__construct();

        if (is_null($defaultServer) && isset($_SERVER['SERVER_NAME'])) {
            $this->_defaultServer = $_SERVER['SERVER_NAME'];
        } else {
            $this->_defaultServer = $defaultServer;
        }
    }

    /**
     * Create a MIME_Message object from a MIME_Part object.
     * This public function can be called statically via:
     *    MIME_Message::convertMIMEPart();
     *
     * @param Part &$mime_part The MIME_Part object
     * @param string|null $server the server to default unqualified
     *                               addresses to
     *
     * @return Message  the new MIME_Message object
     */
    public function &convertMIMEPart(&$mime_part, ?string $server = null): Message
    {
        if (!$mime_part->getMIMEId()) {
            $mime_part->setMIMEId(1);
        }

        $mime_message = new Message($server);
        $mime_message->addPart($mime_part);
        $mime_message->buildMessage();

        return $mime_message;
    }

    /**
     * Take a set of headers and make sure they are encoded properly.
     *
     * @param array $headers the headers to encode
     * @param string|null $charset the character set to use
     *
     * @return array  the array of encoded headers
     */
    public function encode(array $headers, ?string $charset): array
    {
        $addressKeys = ['To', 'Cc', 'Bcc', 'From'];
        $asciikeys = ['MIME-Version', 'Received', 'Message-ID', 'Date', 'Content-Disposition', 'Content-Transfer-Encoding', 'Content-ID', 'Content-Type', 'Content-Description'];
        foreach ($headers as $key => $val) {
            if (is_array($val)) {
                foreach ($val as $key2 => $val2) {
                    $headers[$key][$key2] = (new MIME())->wrapHeaders($key, $val2, $this->getEOL());
                }
            } else {
                if (in_array($key, $addressKeys)) {
                    $text = MIME::encodeAddress($val, $charset, $this->_defaultServer);
                } else {
                    $text = MIME::encode($val, in_array($key, $asciikeys) ? 'US-ASCII' : $charset);
                }
                $headers[$key] = (new MIME())->wrapHeaders($key, $text, $this->getEOL());
            }
        }

        return $headers;
    }

    /**
     * Add the proper set of MIME headers for this message to an array.
     *
     * @param array $headers the headers to add the MIME headers to
     *
     * @return array  the full set of headers including MIME headers
     */
    public function header(array $headers = []): array
    {
        /* Per RFC 2045 [4], this MUST appear in the message headers. */
        $headers['MIME-Version'] = '1.0';

        if ($this->_build) {
            return parent::header($headers);
        }
        $this->buildMessage();

        return $this->encode($this->header($headers), $this->getCharset());
    }

    /**
     * Return the entire message contents, including headers, as a string.
     *
     * @return string  the encoded, generated message
     */
    public function toString($headers = false): string
    {
        if ($this->_build) {
            return parent::toString($headers);
        }
        $this->buildMessage();

        return $this->toString($headers);
    }

    /**
     * Build message from current contents.
     */
    public function buildMessage()
    {
        if ($this->_build) {
            return;
        }

        if (empty($this->_flags['setType'])) {
            if (count($this->_parts) > 1) {
                $this->setType('multipart/mixed');
            } else {
                /* Copy the information from the single part to the current
                   base part. */
                if ($obVars = get_object_vars(reset($this->_parts))) {
                    foreach ($obVars as $key => $val) {
                        $this->$key = $val;
                    }
                }
            }
        }

        /* Set the build flag now. */
        $this->_build = true;
    }

    /**
     * Get a list of all MIME subparts.
     *
     * @return Part[]  an array of the MIME_Part subparts
     */
    public function getParts(): array
    {
        if ($this->_build) {
            return parent::getParts();
        }
        $this->buildMessage();

        return $this->getParts();
    }

    /**
     * Return the base part of the message. This public function does NOT
     * return a reference to make sure that the whole MIME_Message
     * object isn't accidentally modified.
     */
    public function getBasePart(): self
    {
        $this->buildMessage();

        return $this;
    }

    /**
     * Retrieve a specific MIME part.
     *
     * @param string $id the MIME_Part ID string
     *
     * @return false|Part|array|static  the MIME_Part requested, or false if the part
     *                    doesn't exist
     */
    public function &getPart($id): false|Part|array|static
    {
        if ($this->_build) {
            $part = parent::getPart($id);
        } else {
            $this->buildMessage();
            $part = $this->getPart($id);
        }
        if (is_a($part, self::class)) {
            $newpart = new Part();
            $skip = ['_build', '_defaultServer'];
            foreach (array_keys(get_object_vars($part)) as $key) {
                /* Ignore local variables that aren't a part of the original
                 * class. */
                if (!in_array($key, $skip)) {
                    $newpart->$key = &$part->$key;
                }
            }

            return $newpart;
        }

        return $part;
    }
}
