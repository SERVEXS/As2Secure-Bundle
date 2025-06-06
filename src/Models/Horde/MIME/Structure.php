<?php

namespace TechData\AS2SecureBundle\Models\Horde\MIME;

use TechData\AS2SecureBundle\Models\Horde\HordeString;
use TechData\AS2SecureBundle\Models\Horde\MIME;
use TechData\AS2SecureBundle\Models\Mail\MimeDecode;

/**
 * $Horde: framework/MIME/MIME/Structure.php,v 1.87.10.31 2009/01/06 15:23:20 jan Exp $
 *
 * The MIME_Structure:: class provides methods for dealing with MIME mail.
 *
 * The default character set to use for messages should be defined in the
 * variable $GLOBALS['mime_structure']['default_charset'] (defaults to US-ASCII
 * per RFC 2045).
 *
 * TODO: Convert to OO
 *
 * Copyright 1999-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @author  Michael Slusarz <slusarz@horde.org>
 */
class Structure
{
    /**
     * Given the results of imap_fetchstructure(), parse the structure
     * of the message, figuring out correct bodypart numbers, etc.
     *
     * @param \stdClass $body the result of imap_fetchstructure()
     *
     * @return &MIME_Message  The message parsed into a MIME_Message object
     */
    public static function &parse($body)
    {
        $msgOb = new Message();
        $msgOb->addPart(self::_parse($body));
        $msgOb->buildMessage();

        $ptr = [&$msgOb];
        self::addMultipartInfo($ptr);

        return $msgOb;
    }

    /**
     * Given the results of imap_fetchstructure(), parse the structure
     * of the message, figuring out correct bodypart numbers, etc.
     *
     * @param \stdClass $body the result of imap_fetchstructure()
     * @param string $ref the current bodypart
     *
     * @return Part  a MIME_Part object
     */
    protected static function &_parse($body, $ref = 0)
    {
        static $message, $multipart;

        if (!isset($message)) {
            $message = (new MIME())->type('message');
            $multipart = (new MIME())->type('multipart');
        }

        $mime_part = new Part();

        /* Top multiparts don't get their own line. */
        if (empty($ref)
            && (!isset($body->type) || ($body->type != $multipart))
        ) {
            $ref = 1;
        }

        self::_setInfo($body, $mime_part, $ref);

        if (isset($body->type) && ($body->type == $message)
            && $body->ifsubtype && ($body->subtype === 'RFC822')
        ) {
            $mime_part->setMIMEId($ref . '.0');
        } else {
            $mime_part->setMIMEId($ref);
        }

        /* Deal with multipart data. */
        if (isset($body->parts)) {
            $sub_id = 1;
            reset($body->parts);
            foreach ($body->parts as $sub_part) {
                /* Are we dealing with a multipart message? */
                if (isset($body->type) && ($body->type == $message)
                    && isset($sub_part->type) && ($sub_part->type == $multipart)
                ) {
                    $sub_ref = $ref;
                } else {
                    $sub_ref = (empty($ref)) ? $sub_id : $ref . '.' . $sub_id;
                }
                $mime_part->addPart(self::_parse($sub_part, $sub_ref), $sub_id++);
            }
        }

        return $mime_part;
    }

    /**
     * Given a mime part from imap_fetchstructure(), munge it into a
     * useful form and make sure that any parameters which are missing
     * are given default values.
     *
     * To specify the default character set, define the global variable
     * $GLOBALS['mime_strucutre']['default_charset'].
     *
     * @param \stdClass $part the original part info
     * @param MIME_Part &$ob A MIME_Part object
     * @param string $ref the ID of this part
     */
    protected static function _setInfo($part, &$ob, $ref)
    {
        /* Store Content-type information. */
        $primary_type = (isset($part->type)) ? (new MIME())->type($part->type, MIME_STRING) : 'text';
        $sec_type = ($part->ifsubtype && $part->subtype) ? $part->subtype : 'x-unknown';
        $ob->setType($primary_type . '/' . $sec_type);

        /* Set transfer encoding. */
        if (isset($part->encoding)) {
            $encoding = $part->encoding;
            $ob->setTransferEncoding($encoding);
        } else {
            $encoding = null;
        }

        /* Set transfer disposition. */
        $ob->setDisposition(($part->ifdisposition) ? $part->disposition : MIME_DEFAULT_DISPOSITION);

        /* If 'body' is set, set as the contents of the part. */
        if (isset($part->body)) {
            $ob->setContents($part->body, $encoding);
        }

        /* If 'bytes' is set, store as information variable. */
        if (isset($part->bytes)) {
            $ob->setBytes($part->bytes);
        }

        /* Set the part's identification string, if available. */
        if (!empty($ref) && $part->ifid) {
            $ob->setContentID($part->id);
        }

        /* Go through the content-type parameters, if any. */
        foreach (self::_getParameters($part, 1) as $key => $val) {
            if ($key === 'charset') {
                $ob->setCharset($val);
            } else {
                $ob->setContentTypeParameter($key, $val);
            }
        }

        /* Set the default character set. */
        /*if (($ob->getPrimaryType() == 'text') &&
            (String::lower($ob->getCharset()) == 'us-ascii') &&
            isset($GLOBALS['mime_structure']['default_charset'])) {
            $ob->setCharset($GLOBALS['mime_structure']['default_charset']);
        }*/

        /* Go through the disposition parameters, if any. */
        foreach (self::_getParameters($part, 2) as $key => $val) {
            $ob->setDispositionParameter($key, $val);
        }

        /* Set the name. */
        if ($fname = $ob->getContentTypeParameter('filename')) {
            $ob->setName($fname);
        } elseif ($fname = $ob->getDispositionParameter('filename')) {
            $ob->setName($fname);
        }

        /* Set the description. */
        if (isset($part->description)) {
            $ob->setDescription(preg_replace('/\s+/', ' ', (string) $part->description));
        }
    }

    /**
     * Get all parameters for a given portion of a message.
     *
     * @param \stdClass $part the original part info
     * @param int $type The parameter type to retrieve.
     *                        1 = content
     *                        2 = disposition
     *
     * @return array  an array of parameter key/value pairs
     */
    protected static function _getParameters($part, $type)
    {
        $param_list = [];

        $ptype = ($type == 1) ? 'parameters' : 'dparameters';
        $pexists = 'if' . $ptype;

        if ($part->$pexists) {
            $attr_list = $rfc2231_list = [];
            foreach ($part->$ptype as $param) {
                $param->value = str_replace(["\t", '\"'], [' ', '"'], (string) $param->value);
                /* Look for an asterisk in the attribute name.  If found we
                 * know we have RFC 2231 information. */
                $pos = strpos((string) $param->attribute, '*');
                if ($pos) {
                    $attr = substr((string) $param->attribute, 0, $pos);
                    $rfc2231_list[$attr][] = $param->attribute . '=' . $param->value;
                } else {
                    $attr_list[$param->attribute] = $param->value;
                }
            }

            foreach ($rfc2231_list as $val) {
                $res = MIME::decodeRFC2231(implode(' ', $val));
                if ($res) {
                    $attr_list[$res['attribute']] = $res['value'];
                }
            }

            foreach ($attr_list as $attr => $val) {
                $field = HordeString::lower($attr);
                if ($field === 'type') {
                    if ($type = (new MIME())->type($val)) {
                        $param_list['type'] = $type;
                    }
                } else {
                    $param_list[$field] = $val;
                }
            }
        }

        return $param_list;
    }

    /**
     * Set the special information for certain MIME types.
     *
     * @since Horde 3.2
     *
     * @param array|Part[] &$parts The list of parts contained within the multipart
     *                       object
     * @param array $info information about the multipart structure
     */
    public static function addMultipartInfo(&$parts, $info = [])
    {
        if (empty($parts)) {
            return;
        }

        reset($parts);
        foreach ($parts as $part) {
            $new_info = $info;

            if (isset($info['alt'])) {
                $part->setInformation('alternative', empty($info['alt']) ? '-' : $info['alt']);
            }
            if (isset($info['related'])) {
                $part->setInformation('related_part', $info['related']->getMIMEId());
                if ($id = $part->getContentID()) {
                    $info['related']->addCID([$part->getMIMEId() => $id]);
                }
            }
            if (isset($info['rfc822'])) {
                $part->setInformation('rfc822_part', $info['rfc822']);
            }

            switch ($part->getType()) {
                case 'multipart/alternative':
                    $new_info['alt'] = $part->getMIMEId();
                    break;

                case 'multipart/related':
                    $new_info['related'] = &$part;
                    break;

                case 'message/rfc822':
                    $new_info['rfc822'] = $part->getMIMEId();
                    $part->setInformation('header', true);
                    break;
            }

            $parts = $part->getParts();
            self::addMultipartInfo($parts, $new_info);
        }
    }

    /**
     * Attempts to build a MIME_Message object from a text message.
     *
     * @param string $text the text of the MIME message
     *
     * @return MIME_Message  a MIME_Message object, or false on error
     */
    public static function &parseTextMIMEMessage($text)
    {
        /* Set up the options for the mimeDecode class. */
        $decode_args = [
            'include_bodies' => true,
            'decode_bodies' => false,
            'decode_headers' => false,
        ];

        $mimeDecode = new MimeDecode($text);
        if (!($structure = $mimeDecode->decode($decode_args))) {
            $message = false;
        } else {
            /* Put the object into imap_parsestructure() form. */
            self::_convertMimeDecodeData($structure);
            $message = self::parse($structure);
        }

        return $message;
    }

    /**
     * Convert the output from mimeDecode::decode() into a structure that
     * matches imap_fetchstructure() output.
     *
     * @param \stdClass &$ob The output from mimeDecode::decode()
     */
    protected static function _convertMimeDecodeData(&$ob)
    {
        /* Primary content-type. */
        if (!isset($ob->ctype_primary)) {
            $ob->ctype_primary = 'application';
            $ob->ctype_secondary = 'octet-stream';
        }
        $ob->type = (int) (new MIME())->type($ob->ctype_primary);

        /* Secondary content-type. */
        if (isset($ob->ctype_secondary)) {
            $ob->subtype = HordeString::upper($ob->ctype_secondary);
            $ob->ifsubtype = 1;
        } else {
            $ob->ifsubtype = 0;
        }

        /* Content transfer encoding. */
        if (isset($ob->headers['content-transfer-encoding'])) {
            $ob->encoding = (new MIME())->encoding($ob->headers['content-transfer-encoding']);
        }

        /* Content-type and Disposition parameters. */
        $param_types = ['ctype_parameters' => 'parameters',
            'd_parameters' => 'dparameters'];
        foreach ($param_types as $param_key => $param_value) {
            $if_var = 'if' . $param_value;
            if (isset($ob->$param_key)) {
                $ob->$if_var = 1;
                $ob->$param_value = [];
                foreach ($ob->$param_key as $key => $val) {
                    $newOb = new \stdClass();
                    $newOb->attribute = $key;
                    $newOb->value = $val;
                    $ob->$param_value[] = $newOb;
                }
            } else {
                $ob->$if_var = 0;
            }
        }

        /* Content-Disposition. */
        if (isset($ob->headers['content-disposition'])) {
            $ob->ifdisposition = 1;
            $hdr = $ob->headers['content-disposition'];
            $pos = strpos((string) $hdr, ';');
            if ($pos !== false) {
                $hdr = substr((string) $hdr, 0, $pos);
            }
            $ob->disposition = $hdr;
        } else {
            $ob->ifdisposition = 0;
        }

        /* Content-ID. */
        if (isset($ob->headers['content-id'])) {
            $ob->ifid = 1;
            $ob->id = $ob->headers['content-id'];
        } else {
            $ob->ifid = 0;
        }

        /* Get file size (if 'body' text is set). */
        if (isset($ob->body)) {
            $ob->bytes = strlen((string) $ob->body);
        }

        /* Process parts also. */
        if (isset($ob->parts)) {
            reset($ob->parts);
            foreach ($ob->parts as $part) {
                self::_convertMimeDecodeData($part);
            }
        }
    }

    /**
     * Builds an array consisting of MIME header/value pairs.
     *
     * @param string $headers A text string containing the headers (e.g.
     *                            output from imap_fetchheader()).
     * @param bool $decode Should the headers be decoded?
     * @param bool $lowercase Should the keys be in lowercase?
     *
     * @return array  An array consisting of the header name as the key and
     *                the header value as the value.
     *                A header with multiple entries will be stored in
     *                'value' as an array.
     */
    public static function parseMIMEHeaders($headers, $decode = true, $lowercase = false)
    {
        $header = $headval = '';
        $ob = $toprocess = [];

        foreach (explode("\n", $headers) as $val) {
            $val = rtrim($val);
            if (preg_match("/^([^\s]+)\:\s*(.*)/", $val, $matches)) {
                if (!empty($header)) {
                    $toprocess[] = [$header, $headval];
                }
                $header = $matches[1];
                $headval = $matches[2];
            } else {
                $val = ltrim($val);
                if ($val) {
                    $headval .= ' ' . ltrim($val);
                } else {
                    break;
                }
            }
        }

        if (!empty($header)) {
            $toprocess[] = [$header, $headval];
        }

        foreach ($toprocess as $val) {
            if ($decode) {
                // Fields defined in RFC 2822 that contain address information
                if (in_array(HordeString::lower($val[0]), ['from', 'to', 'cc', 'bcc', 'reply-to', 'resent-to', 'resent-cc', 'resent-bcc', 'resent-from', 'sender'])) {
                    $val[1] = MIME::decodeAddrString($val[1]);
                } else {
                    $val[1] = MIME::decode($val[1]);
                }
            }

            if (isset($ob[$val[0]])) {
                if (!is_array($ob[$val[0]])) {
                    $temp = $ob[$val[0]];
                    $ob[$val[0]] = [];
                    $ob[$val[0]][] = $temp;
                }
                $ob[$val[0]][] = $val[1];
            } else {
                $ob[$val[0]] = $val[1];
            }
        }

        return ($lowercase) ? array_change_key_case($ob) : $ob;
    }
}
