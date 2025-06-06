<?php

namespace TechData\AS2SecureBundle\Models\Horde;

/* We need to (unfortunately) hard code these constants because they reside in
 * the imap module, which is not required for Horde.
 * These constants are found in the UW-imap c-client distribution:
 *   ftp://ftp.cac.washington.edu/imap/
 * The constants appear in the file include/mail.h */

use TechData\AS2SecureBundle\Models\Mail\RFC822;

if (!defined('TYPETEXT')) {
    /* Primary body types */
    define('TYPETEXT', 0);
    define('TYPEMULTIPART', 1);
    define('TYPEMESSAGE', 2);
    define('TYPEAPPLICATION', 3);
    define('TYPEAUDIO', 4);
    define('TYPEIMAGE', 5);
    define('TYPEVIDEO', 6);
    define('TYPEOTHER', 8);

    /* Body encodings */
    define('ENC7BIT', 0);
    define('ENC8BIT', 1);
    define('ENCBINARY', 2);
    define('ENCBASE64', 3);
    define('ENCQUOTEDPRINTABLE', 4);
    define('ENCOTHER', 5);
}

/*
 * Older versions of PHP's imap extension don't define TYPEMODEL.
 */
if (!defined('TYPEMODEL')) {
    define('TYPEMODEL', 7);
}

/*
 * Return a code for type()/encoding().
 */
define('MIME_CODE', 1);

/*
 * Return a string for type()/encoding().
 */
define('MIME_STRING', 2);

/**
 * The MIME:: class provides methods for dealing with MIME standards.
 *
 * $Horde: framework/MIME/MIME.php,v 1.139.4.47 2009/01/18 03:35:15 chuck Exp $
 *
 * Copyright 1999-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 *
 * @since   Horde 1.3
 */
class MIME
{
    /**
     * A listing of the allowed MIME types.
     */
    public array $mime_types = [
        TYPETEXT => 'text',
        TYPEMULTIPART => 'multipart',
        TYPEMESSAGE => 'message',
        TYPEAPPLICATION => 'application',
        TYPEAUDIO => 'audio',
        TYPEIMAGE => 'image',
        TYPEVIDEO => 'video',
        TYPEMODEL => 'model',
        TYPEOTHER => 'other',
    ];

    /**
     * A listing of the allowed MIME encodings.
     */
    public array $mime_encodings = [
        ENC7BIT => '7bit',
        ENC8BIT => '8bit',
        ENCBINARY => 'binary',
        ENCBASE64 => 'base64',
        ENCQUOTEDPRINTABLE => 'quoted-printable',
        ENCOTHER => 'unknown',
    ];

    /**
     * Filter for RFC822.
     */
    public string $rfc822_filter = "()<>@,;:\\\"[]\1\2\3\4\5\6\7\10\11\12\13\14\15\16\17\20\21\22\23\24\25\26\27\30\31\32\33\34\35\36\37\177";

    /**
     * Determines if a string contains 8-bit (non US-ASCII) characters.
     *
     * @param string $string the string to check
     * @param string|null $charset The charset of the string. Defaults to US-ASCII. Since Horde 3.2.2.
     *
     * @return bool  true if it does, false if it doesn't
     */
    public static function is8bit($string, ?string $charset = null): bool
    {
        /* ISO-2022-JP is a 7bit charset, but it is an 8bit representation so
         * it needs to be entirely encoded. */
        return is_string($string)
        && ((stripos('iso-2022-jp', $charset) !== false
                && str_contains($string, "\x1b\$B"))
            || preg_match('/[\x80-\xff]/', $string));
    }

    /**
     * Encodes a string containing non-ASCII characters according to RFC 2047.
     *
     * @param string $text the text to encode
     * @param string $charset the character set of the text
     *
     * @return string  the text, encoded only if it contains non-ASCII characters
     */
    public static function encode($text, ?string $charset = null): string
    {
        if (is_null($charset)) {
            $charset = 'UTF-8';
        }
        $charset = HordeString::lower($charset);

        $line = '';

        /* Return if nothing needs to be encoded. */
        if (($charset === 'us-ascii') || !self::is8bit($text, $charset)) {
            return $text;
        }

        /* Get the list of elements in the string. */
        $size = preg_match_all('/([^\s]+)([\s]*)/', $text, $matches, PREG_SET_ORDER);

        foreach ($matches as $key => $val) {
            if (self::is8bit($val[1], $charset)) {
                if ((($key + 1) < $size) && self::is8bit($matches[$key + 1][1], $charset)) {
                    $line .= self::_encode($val[1] . $val[2], $charset) . ' ';
                } else {
                    $line .= self::_encode($val[1], $charset) . $val[2];
                }
            } else {
                $line .= $val[1] . $val[2];
            }
        }

        return rtrim($line);
    }

    /**
     * Internal recursive public function to RFC 2047 encode a string.
     *
     * @param string $text the text to encode
     * @param string $charset the character set of the text
     *
     * @return string  the text, encoded only if it contains non-ASCII
     *                 characters
     */
    protected static function _encode($text, $charset): string
    {
        $encoded = trim(base64_encode($text));
        $c_size = strlen($charset) + 7;

        if ((strlen($encoded) + $c_size) > 75) {
            $parts = explode("\r\n", rtrim(chunk_split($encoded, (int) ((75 - $c_size) / 4) * 4)));
        } else {
            $parts[] = $encoded;
        }

        $p_size = count($parts);
        $out = '';
        foreach ($parts as $key => $val) {
            $out .= '=?' . $charset . '?b?' . $val . '?=';
            if ($p_size > $key + 1) {
                /* RFC 2047 [2] states that no encoded word can be more than
                 * 75 characters long. If longer, you must split the word with
                 * CRLF SPACE. */
                $out .= "\r\n ";
            }
        }

        return $out;
    }

    /**
     * Encodes a line via quoted-printable encoding.
     * Wraps lines at 76 characters.
     *
     * @param string $text the text to encode
     * @param string $eol the EOL sequence to use
     *
     * @return string  the quoted-printable encoded string
     */
    public static function quotedPrintableEncode($text, $eol): string
    {
        $line = $output = '';
        $curr_length = 0;

        /* We need to go character by character through the data. */
        $length = strlen($text);
        for ($i = 0; $i < $length; ++$i) {
            $char = $text[$i];

            /* If we have reached the end of the line, reset counters. */
            if ($char === "\n") {
                $output .= $eol;
                $curr_length = 0;
                continue;
            }
            if ($char === "\r") {
                continue;
            }

            $ascii = ord($char);
            $char_len = 1;

            /* Spaces or tabs at the end of the line are NOT allowed. Also,
             * ASCII characters below 32 or above 126 AND 61 must be
             * encoded. */
            if ((($ascii === 32)
                    && ($i + 1 !== $length)
                    && (($text[$i + 1] === "\n") || ($text[$i + 1] === "\r")))
                || (($ascii < 32) || ($ascii > 126) || ($ascii === 61))
            ) {
                $char_len = 3;
                $char = '=' . HordeString::upper(sprintf('%02s', dechex($ascii)));
            }

            /* Lines must be 76 characters or less. */
            $curr_length += $char_len;
            if ($curr_length > 76) {
                $output .= '=' . $eol;
                $curr_length = $char_len;
            }
            $output .= $char;
        }

        return $output;
    }

    /**
     * Encodes a string containing email addresses according to RFC 2047.
     *
     * This differs from MIME::encode() because it keeps email addresses legal,
     * only encoding the personal information.
     *
     * @param string|array $addresses the email addresses to encode
     * @param string|null $charset the character set of the text
     * @param string|null $defaultServer the default domain to append to mailboxes
     *
     * @return string  The text, encoded only if it contains non-ascii
     *                 characters
     */
    public static function encodeAddress(string|array $addresses, ?string $charset = null, ?string $defaultServer = null): string
    {
        if (is_array($addresses)) {
            $addr_arr = $addresses;
        } else {
            /* parseAddressList() does not process the null entry
             * 'undisclosed-recipients:;' correctly. */
            if (preg_match('/undisclosed-recipients:\s*;/i', trim($addresses))) {
                return $addresses;
            }

            $parser = new RFC822();
            $addr_arr = $parser->parseAddressList($addresses, $defaultServer, true, false);
        }

        $text = '';
        if (is_array($addr_arr)) {
            foreach ($addr_arr as $addr) {
                // Check for groups.
                if (!empty($addr->groupname)) {
                    $text .= self::encode($addr->groupname, $charset) . ': ' . self::encodeAddress($addr->addresses) . '; ';
                } else {
                    if (empty($addr->personal)) {
                        $personal = '';
                    } else {
                        if (str_starts_with($addr->personal, '"') && str_ends_with($addr->personal, '"')) {
                            $addr->personal = stripslashes(substr($addr->personal, 1, -1));
                        }
                        $personal = self::encode($addr->personal, $charset);
                    }
                    $text .= (new self())->trimEmailAddress(
                        (new self())->rfc822WriteAddress($addr->mailbox, $addr->host, $personal)) . ', ';
                }
            }
        }

        return rtrim($text, ' ,');
    }

    /**
     * Decodes an RFC 2047-encoded string.
     *
     * @param string $string the text to decode
     * @param string|null $to_charset the charset that the text should be decoded
     *                            to
     *
     * @return string  the decoded text
     */
    public static function decode(string $string, ?string $to_charset = null): string
    {
        if (($pos = strpos($string, '=?')) === false) {
            return $string;
        }

        /* Take out any spaces between multiple encoded words. */
        $string = preg_replace('|\?=\s+=\?|', '?==?', $string);

        /* Save any preceding text. */
        $preceding = substr($string, 0, $pos);

        $search = substr($string, $pos + 2);
        $d1 = strpos($search, '?');
        if ($d1 === false) {
            return $string;
        }

        $charset = substr($string, $pos + 2, $d1);
        $search = substr($search, $d1 + 1);

        $d2 = strpos($search, '?');
        if ($d2 === false) {
            return $string;
        }

        $encoding = substr($search, 0, $d2);
        $search = substr($search, $d2 + 1);

        $end = strpos($search, '?=');
        if ($end === false) {
            $end = strlen($search);
        }

        $encoded_text = substr($search, 0, $end);
        $rest = substr($string, strlen($preceding . $charset . $encoding . $encoded_text) + 6);

        if (is_null($to_charset)) {
            $to_charset = 'UTF-8';
        }

        switch ($encoding) {
            case 'Q':
            case 'q':
                $encoded_text = str_replace('_', ' ', $encoded_text);
                $decoded = preg_replace('/=([0-9a-f]{2})/ie', 'chr(0x\1)', $encoded_text);
                $decoded = HordeString::convertCharset($decoded, $charset, $to_charset);
                break;

            case 'B':
            case 'b':
                $decoded = base64_decode($encoded_text);
                $decoded = HordeString::convertCharset($decoded, $charset, $to_charset);
                break;

            default:
                $decoded = '=?' . $charset . '?' . $encoding . '?' . $encoded_text . '?=';
                break;
        }

        return $preceding . $decoded . self::decode($rest, $to_charset);
    }

    /**
     * Decodes an RFC 2047-encoded address string.
     *
     * @param string $string the text to decode
     * @param string|null $to_charset the charset that the text should be decoded to
     */
    public static function decodeAddrString($string, ?string $to_charset = null): string
    {
        $addr_list = [];
        foreach ((new self())->parseAddressList($string) as $ob) {
            $ob->personal = isset($ob->personal) ? self::decode($ob->personal, $to_charset) : '';
            $addr_list[] = $ob;
        }

        return (new self())->addrArray2String($addr_list);
    }

    /**
     * Encodes a string pursuant to RFC 2231.
     *
     * @param string $name the parameter name
     * @param string $string the string to encode
     * @param string $charset the charset the text should be encoded with
     * @param string $lang the language to use when encoding
     */
    public static function encodeRFC2231($name, $string, $charset, $lang = null): string
    {
        $encode = $wrap = false;
        $output = [];

        if (self::is8bit($string, $charset)) {
            $string = HordeString::lower($charset) . '\'' . (($lang === null) ? '' : HordeString::lower($lang)) . '\'' . rawurlencode($string);
            $encode = true;
        }

        // 4 = '*', 2x '"', ';'
        $lines = [$string];
        $pre_len = strlen($name) + 4 + (($encode) ? 1 : 0);
        if (($pre_len + strlen($string)) > 76) {
            while ($string) {
                $chunk = 76 - $pre_len;
                $pos = min($chunk, strlen($string) - 1);
                if (($chunk == $pos) && ($pos > 2)) {
                    for ($i = 0; $i <= 2; ++$i) {
                        if ($string[$pos - $i] === '%') {
                            $pos -= $i + 1;
                            break;
                        }
                    }
                }
                $lines[] = substr($string, 0, $pos + 1);
                $string = substr($string, $pos + 1);
            }
            $wrap = true;
        }

        $i = 0;
        foreach ($lines as $val) {
            $output[] =
                $name .
                (($wrap) ? ('*' . $i++) : '') .
                (($encode) ? '*' : '') .
                '="' . $val . '"';
        }

        return implode('; ', $output);
    }

    /**
     * Decodes an RFC 2231-encoded string.
     *
     * @param string $string the entire string to decode, including the
     *                            parameter name
     * @param string|null $to_charset the charset the text should be decoded to
     *
     * @return array  the decoded text, or the original string if it was not
     *                encoded
     */
    public static function decodeRFC2231($string, ?string $to_charset = null)
    {
        if (($pos = strpos($string, '*')) === false) {
            return [];
        }

        if (!isset($to_charset)) {
            $to_charset = 'UTF-8';
        }

        $attribute = substr($string, 0, $pos);
        $charset = $lang = null;
        $output = '';

        /* Get the character set and language used in the encoding, if
         * any. */
        if (preg_match("/^[^=]+\*\=([^']*)'([^']*)'/", $string, $matches)) {
            $charset = $matches[1];
            $lang = $matches[2];
            $string = str_replace($charset . "'" . $lang . "'", '', $string);
        }

        $lines = preg_split('/\s*' . preg_quote($attribute) . '(?:\*\d)*/', $string);
        foreach ($lines as $line) {
            $pos = strpos($line, '*=');
            if ($pos === 0) {
                $line = substr($line, 2);
                $line = str_replace(['_', '='], ['%20', '%'], $line);
                $output .= urldecode($line);
            } else {
                $line = substr($line, 1);
                $output .= $line;
            }
        }

        /* RFC 2231 uses quoted printable encoding. */
        if (!is_null($charset)) {
            $output = HordeString::convertCharset($output, $charset, $to_charset);
        }

        return [
            'attribute' => $attribute,
            'value' => $output,
        ];
    }

    /**
     * If an email address has no personal information, get rid of any angle
     * brackets (<>) around it.
     *
     * @param string $address the address to trim
     *
     * @return string  the trimmed address
     */
    public function trimEmailAddress($address): string
    {
        $address = trim($address);

        if (str_starts_with($address, '<') && str_ends_with($address, '>')) {
            $address = substr($address, 1, -1);
        }

        return $address;
    }

    /**
     * Builds an RFC 822 compliant email address.
     *
     * @param string $mailbox mailbox name
     * @param string|null $host domain name of mailbox's host
     * @param string $personal personal name phrase
     *
     * @return string  the correctly escaped and quoted
     *                 "$personal <$mailbox@$host>" string
     */
    public function rfc822WriteAddress(string $mailbox, ?string $host = null, string $personal = ''): string
    {
        $address = '';

        if ($personal !== '') {
            $address .= $this->_rfc822Encode($personal, 'personal');
            $address .= ' <';
        }

        if (!is_null($host)) {
            $address .= $this->_rfc822Encode($mailbox);
            if (!str_starts_with($host, '@')) {
                $address .= '@' . $host;
            }
        }

        if ($personal !== '') {
            $address .= '>';
        }

        return $address;
    }

    /**
     * Explodes an RFC 2822 string, ignoring a delimiter if preceded
     * by a "\" character, or if the delimiter is inside single or
     * double quotes.
     *
     * @param string $string the RFC 822 string
     * @param string $delimiters A string containing valid delimiters.
     *                           Defaults to ','.
     *
     * @return array  the exploded string in an array
     */
    public function rfc822Explode(string $string, string $delimiters = ','): array
    {
        $emails = [];
        $pos = 0;
        $in_group = $in_quote = false;
        $prev = null;

        if ($string === '') {
            return [$string];
        }

        $char = $string[0];
        if ($char === '"') {
            $in_quote = true;
        } elseif ($char === ':') {
            $in_group = true;
        } elseif (str_contains($delimiters, $char)) {
            $emails[] = '';
            $pos = 1;
        }

        for ($i = 1, $iMax = strlen($string); $i < $iMax; ++$i) {
            $char = $string[$i];
            if ($char === '"') {
                if ($prev !== '\\') {
                    $in_quote = !$in_quote;
                }
            } elseif ($in_group) {
                if ($char === ';') {
                    $emails[] = substr($string, $pos, $i - $pos + 1);
                    $pos = $i + 1;
                    $in_group = false;
                }
            } elseif (!$in_quote) {
                if ($char === ':') {
                    $in_group = true;
                } elseif (str_contains($delimiters, $char)
                    && $prev !== '\\'
                ) {
                    $emails[] = substr($string, $pos, $i - $pos);
                    $pos = $i + 1;
                }
            }
            $prev = $char;
        }

        if ($pos != $i) {
            /* The string ended without a delimiter. */
            $emails[] = substr($string, $pos, $i - $pos);
        }

        return $emails;
    }

    /**
     * Takes an address object, as returned by imap_header() for example, and
     * formats it as a string.
     *
     * Object format for the address "John Doe <john_doe@example.com>" is:
     * <pre>
     *   $object->personal = Personal name ("John Doe")
     *   $object->mailbox  = The user's mailbox ("john_doe")
     *   $object->host     = The host the mailbox is on ("example.com")
     * </pre>
     *
     * @param \stdClass $ob the address object to be turned into a string
     * @param mixed $filter A user@example.com style bare address to ignore.
     *                       Either single string or an array of strings.  If
     *                       the address matches $filter, an empty string will
     *                       be returned.
     *
     * @return string  The formatted address (Example: John Doe
     *                 <john_doe@example.com>).
     */
    public function addrObject2String($ob, mixed $filter = '')
    {
        /* If the personal name is set, decode it. */
        $ob->personal = isset($ob->personal) ? self::decode($ob->personal) : '';

        /* If both the mailbox and the host are empty, return an empty
           string.  If we just let this case fall through, the call to
           MIME::rfc822WriteAddress() will end up return just a '@', which
           is undesirable. */
        if (empty($ob->mailbox) && empty($ob->host)) {
            return '';
        }

        /* Make sure these two variables have some sort of value. */
        if (!isset($ob->mailbox)) {
            $ob->mailbox = '';
        } elseif ($ob->mailbox === 'undisclosed-recipients') {
            return '';
        }
        if (!isset($ob->host)) {
            $ob->host = '';
        }

        /* Filter out unwanted addresses based on the $filter string. */
        if ($filter) {
            if (!is_array($filter)) {
                $filter = [$filter];
            }
            foreach ($filter as $f) {
                if (strcasecmp($f, $ob->mailbox . '@' . $ob->host) === 0) {
                    return '';
                }
            }
        }

        /* Return the trimmed, formatted email address. */
        return $this->trimEmailAddress($this->rfc822WriteAddress($ob->mailbox, $ob->host, $ob->personal));
    }

    /**
     * Takes an array of address objects, as returned by imap_headerinfo(),
     * for example, and passes each of them through MIME::addrObject2String().
     *
     * @param array $addresses the array of address objects
     * @param mixed $filter A user@example.com style bare address to
     *                          ignore.  If any address matches $filter, it
     *                          will not be included in the final string.
     *
     * @return string  All of the addresses in a comma-delimited string.
     *                 Returns the empty string on error/no addresses found.
     */
    public function addrArray2String($addresses, mixed $filter = '')
    {
        $addrList = [];

        if (!is_array($addresses)) {
            return '';
        }

        foreach ($addresses as $addr) {
            $val = $this->addrObject2String($addr, $filter);
            if (!empty($val)) {
                $bareAddr = HordeString::lower($this->bareAddress($val));
                if (!isset($addrList[$bareAddr])) {
                    $addrList[$bareAddr] = $val;
                }
            }
        }

        if (empty($addrList)) {
            return '';
        }

        return implode(', ', $addrList);
    }

    /**
     * Returns the bare address.
     *
     * @param string $address the address string
     * @param string|null $defaultServer the default domain to append to mailboxes
     * @param bool $multiple Should we return multiple results?
     *
     * @return mixed  If $multiple is false, returns the mailbox@host e-mail
     *                address.  If $multiple is true, returns an array of
     *                these addresses.
     */
    public function bareAddress($address, ?string $defaultServer = null, bool $multiple = false): mixed
    {
        $addressList = [];

        $from = $this->parseAddressList($address, $defaultServer);

        foreach ($from as $entry) {
            if (isset($entry->mailbox)
                && $entry->mailbox !== 'undisclosed-recipients'
                && $entry->mailbox !== 'UNEXPECTED_DATA_AFTER_ADDRESS'
            ) {
                if (isset($entry->host)) {
                    $addressList[] = $entry->mailbox . '@' . $entry->host;
                } else {
                    $addressList[] = $entry->mailbox;
                }
            }
        }

        return $multiple ? $addressList : array_pop($addressList);
    }

    /**
     * Parses a list of email addresses into its parts.
     *
     * Works with and without the imap extension being available and parses
     * distribution lists as well.
     *
     * @param string $address the address string
     * @param string|null $defaultServer the default domain to append to mailboxes
     * @param bool $validate whether to validate the address(es)
     *
     * @return array  a list of objects with the possible properties 'mailbox',
     *                'host', 'personal', 'adl', and 'comment'
     *
     *@since Horde 3.2
     * @see   http://www.php.net/imap_rfc822_parse_adrlist
     */
    public function parseAddressList($address, ?string $defaultServer = null, bool $validate = false)
    {
        if (preg_match('/undisclosed-recipients:\s*;/i', trim($address))) {
            return [];
        }

        /* Use built-in IMAP public function only if available and if not parsing
         * distribution lists because it doesn't parse distribution lists
         * properly. */
        if (!$validate
            && !str_contains($address, ':')
            && (new Util())->extensionExists('imap')
        ) {
            return imap_rfc822_parse_adrlist($address, $defaultServer);
        }

        return (new RFC822())->parseAddressList($address, $defaultServer, false, $validate);
    }

    /**
     * Quotes and escapes the given string if necessary using rules contained
     * in RFC 2822 [3.2.5].
     *
     * @param string $str the string to be quoted and escaped
     * @param string $type either 'address' or 'personal'
     *
     * @return string  the correctly quoted and escaped string
     */
    protected function _rfc822Encode($str, $type = 'address')
    {
        // Excluded (in ASCII): 0-8, 10-31, 34, 40-41, 44, 58-60, 62, 64,
        // 91-93, 127
        $filter = "\0\1\2\3\4\5\6\7\10\12\13\14\15\16\17\20\21\22\23\24\25\26\27\30\31\32\33\34\35\36\37\"(),:;<>@[\\]\177";

        switch ($type) {
            case 'address':
                // RFC 2822 [3.4.1]: (HTAB, SPACE) not allowed in address
                $filter .= "\11\40";
                break;

            case 'personal':
                // RFC 2822 [3.4]: Period not allowed in display name
                $filter .= '.';
                break;

            default:
                // BC: $filter was passed in explicitly
                $filter = $type;
        }

        // Strip double quotes if they are around the string already.
        // If quoted, we know that the contents are already escaped, so
        // unescape now.
        if (str_starts_with($str, '"') && str_ends_with($str, '"')) {
            $str = stripslashes(substr($str, 1, -1));
        }

        if (strcspn($str, $filter) !== strlen($str)) {
            return '"' . addcslashes($str, '\\"') . '"';
        }

        return $str;
    }

    /**
     * Returns the MIME type for the given input.
     *
     * @param mixed $input either the MIME code or type string
     * @param int|null $format If MIME_CODE, return code. If MIME_STRING, returns lowercase string.
     *
     * @return mixed  see above
     */
    public function type($input, ?int $format = null): mixed
    {
        return $this->_getCode($input, $format, 'mime_types');
    }

    /**
     * Returns the MIME encoding for the given input.
     *
     * @param mixed $input either the MIME code or encoding string
     * @param int|null $format If MIME_CODE, return code.
     *                         If MIME_STRING, returns lowercase string.
     *                         If not set, returns the opposite value.
     *
     * @return mixed  see above
     */
    public function encoding($input, ?int $format = null): mixed
    {
        return $this->_getCode($input, $format, 'mime_encodings');
    }

    /**
     * Retrieves MIME encoding/type data from the internal arrays.
     *
     * @param mixed $input either the MIME code or encoding string
     * @param string $format If MIME_CODE, returns code.
     *                        If MIME_STRING, returns lowercase string.
     *                        If null, returns the oppposite value.
     * @param string $type the name of the internal array
     *
     * @return mixed  see above
     */
    protected function _getCode($input, $format, $type)
    {
        $numeric = is_numeric($input);
        if (!$numeric) {
            $input = HordeString::lower($input);
        }

        switch ($format) {
            case MIME_CODE:
                if ($numeric) {
                    return $input;
                }
                break;

            case MIME_STRING:
                if (!$numeric) {
                    return $input;
                }
                break;
        }

        $vars = get_class_vars(__CLASS__);

        if ($numeric) {
            if (isset($vars[$type][$input])) {
                return $vars[$type][$input];
            }
        } elseif ($search = array_search($input, $vars[$type])) {
            return $search;
        }

        return null;
    }

    /**
     * Generates a Message-ID string conforming to RFC 2822 [3.6.4] and the
     * standards outlined in 'draft-ietf-usefor-message-id-01.txt'.
     *
     * @param string  a message ID string
     */
    public function generateMessageID(): string
    {
        return '<' . date('YmdHis') . '.'
        . substr(str_pad(base_convert(microtime(), 10, 36), 16, uniqid(mt_rand()), STR_PAD_LEFT), -16)
        . '@' . $_SERVER['SERVER_NAME'] . '>';
    }

    /**
     * Adds proper linebreaks to a header string.
     * RFC 2822 says headers SHOULD only be 78 characters a line, but also
     * says that a header line MUST not be more than 998 characters.
     *
     * @param string $header the header name
     * @param string $text the text of the header field
     * @param string $eol the EOL string to use
     *
     * @return string  the header text, with linebreaks inserted
     */
    public function wrapHeaders(string $header, string $text, string $eol = "\r\n"): string
    {
        $header = rtrim($header);
        $text = rtrim($text);

        /* Remove any existing linebreaks. */
        $text = $header . ': ' . preg_replace("/\r?\n\s?/", ' ', $text);

        $eollength = strlen($eol);
        $header_lower = strtolower($header);

        if (!in_array($header_lower, ['content-type', 'content-disposition'])) {
            /* Wrap the line. */
            $line = wordwrap($text, 75, $eol . ' ');

            /* Make sure there are no empty lines. */
            $line = preg_replace('/' . $eol . ' ' . $eol . ' /', '/' . $eol . ' /', $line);

            return substr($line, strlen($header) + 2);
        }

        /* Split the line by the RFC parameter separator ';'. */
        $params = preg_split("/\s*;\s*/", $text);

        $line = '';
        $length = 1000 - $eollength;
        $paramCount = count($params);

        foreach ($params as $count => $val) {
            /* If longer than RFC allows, then simply chop off the excess. */
            $moreParams = (($count + 1) !== $paramCount);
            $maxlength = $length - (!empty($line) ? 1 : 0) - (($moreParams) ? 1 : 0);
            if (strlen($val) > $maxlength) {
                $val = substr($val, 0, $maxlength);

                /* If we have an opening quote, add a closing quote after
                 * chopping the rest of the text. */
                if (str_contains($val, '"')) {
                    $val = substr($val, 0, -1);
                    $val .= '"';
                }
            }

            if (!empty($line)) {
                $line .= ' ';
            }
            $line .= $val . (($moreParams) ? ';' : '') . $eol;
        }

        return substr($line, strlen($header) + 2, $eollength * -1);
    }
}
