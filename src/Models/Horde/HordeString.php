<?php

namespace TechData\AS2SecureBundle\Models\Horde;

/**
 * The Horde_String:: class provides static methods for charset and locale safe
 * string manipulation.
 *
 * $Horde: framework/Util/Horde_String.php,v 1.43.6.37 2009/03/30 15:31:38 jan Exp $
 *
 * Copyright 2003-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Jan Schneider <jan@horde.org>
 *
 * @since   Horde 3.0
 */
class HordeString
{
    public static string $charset = 'UTF-8';

    /**
     * Caches the result of extension_loaded() calls.
     *
     * @param string $ext the extension name
     *
     * @return bool  Is the extension loaded?
     *
     * @see Util::extensionExists()
     */
    public static function extensionExists(string $ext): bool
    {
        static $cache = [];

        if (!isset($cache[$ext])) {
            $cache[$ext] = extension_loaded($ext);
        }

        return $cache[$ext];
    }

    /**
     * Sets a default charset that the Horde_String:: methods will use if none is
     * explicitly specified.
     *
     * @param string $charset the charset to use as the default one
     */
    public static function setDefaultCharset($charset): void
    {
        self::$charset = $charset;
        if (self::extensionExists('mbstring') && function_exists('mb_regex_encoding')) {
            $old_error = error_reporting(0);
            mb_regex_encoding(self::_mbstringCharset($charset));
            error_reporting($old_error);
        }
    }

    /**
     * Converts a string from one charset to another.
     *
     * Works only if either the iconv or the mbstring extension
     * are present and best if both are available.
     * The original string is returned if conversion failed or none
     * of the extensions were available.
     *
     * @param mixed $input The data to be converted. If $input is an array,
     *                      the array's values get converted recursively.
     * @param string $from the string's current charset
     * @param string $to The charset to convert the string to. If not
     *                      specified, the global variable
     *                      $_HORDE_STRING_CHARSET will be used.
     *
     * @return mixed  the converted input data
     */
    public static function convertCharset(mixed $input, $from, ?string $to = null)
    {
        /* Don't bother converting numbers. */
        if (is_numeric($input)) {
            return $input;
        }

        /* Get the user's default character set if none passed in. */
        if (is_null($to)) {
            $to = self::$charset;
        }

        /* If the from and to character sets are identical, return now. */
        $from = self::lower($from);
        $to = self::lower($to);
        if ($from == $to) {
            return $input;
        }

        if (is_array($input)) {
            $tmp = [];
            reset($input);
            foreach ($input as $key => $val) {
                $tmp[self::_convertCharset($key, $from, $to)] = self::convertCharset($val, $from, $to);
            }

            return $tmp;
        }
        if (is_object($input)) {
            $vars = get_object_vars($input);
            foreach ($vars as $key => $val) {
                $input->$key = self::convertCharset($val, $from, $to);
            }

            return $input;
        }

        if (!is_string($input)) {
            return $input;
        }

        return self::_convertCharset($input, $from, $to);
    }

    /**
     * Internal public function used to do charset conversion.
     *
     * @param string $input see Horde_String::convertCharset()
     * @param string $from see Horde_String::convertCharset()
     * @param string $to see Horde_String::convertCharset()
     *
     * @return string  the converted string
     */
    protected static function _convertCharset($input, $from, $to)
    {
        $output = '';
        $from_check = (($from === 'iso-8859-1') || ($from === 'us-ascii'));
        $to_check = (($to === 'iso-8859-1') || ($to === 'us-ascii'));

        /* Use utf8_[en|de]code() if possible and if the string isn't to
         * large (less than 16 MB = 16 * 1024 * 1024 = 16777216 bytes) - these
         * public functions use more memory. */
        if (strlen($input) < 16_777_216 || !(self::extensionExists('iconv') || self::extensionExists('mbstring'))) {
            if ($from_check && ($to === 'utf-8')) {
                return mb_convert_encoding($input, 'UTF-8', 'ISO-8859-1');
            }

            if (($from === 'utf-8') && $to_check) {
                return mb_convert_encoding($input, 'ISO-8859-1');
            }
        }

        /* First try iconv with transliteration. */
        if (($from !== 'utf7-imap')
            && ($to !== 'utf7-imap')
            && self::extensionExists('iconv')
        ) {
            /* We need to tack an extra character temporarily because of a bug
             * in iconv() if the last character is not a 7-bit ASCII
             * character. */
            if (PHP_MAJOR_VERSION < 8) {
                $oldTrackErrors = ini_set('track_errors', 1);
            }

            unset($php_errormsg);
            $output = @iconv($from, $to . '//TRANSLIT', $input . 'x');
            $output = self::substr($output, 0, -1, $to);

            if (PHP_MAJOR_VERSION < 8) {
                ini_set('track_errors', $oldTrackErrors);
            }
        }

        /* Next try mbstring. */
        if (!$output && self::extensionExists('mbstring')) {
            $old_error = error_reporting(0);
            $output = mb_convert_encoding($input, $to, self::_mbstringCharset($from));
            error_reporting($old_error);
        }

        /* At last try imap_utf7_[en|de]code if appropriate. */
        if (!$output && self::extensionExists('imap')) {
            if ($from_check && ($to === 'utf7-imap')) {
                return @imap_utf7_encode($input);
            }
            if (($from === 'utf7-imap') && $to_check) {
                return @imap_utf7_decode($input);
            }
        }

        return (!$output) ? $input : $output;
    }

    /**
     * Makes a string lowercase.
     *
     * @param string $string the string to be converted
     * @param bool $locale if true the string will be converted based on a
     *                          given charset, locale independent else
     * @param string $charset If $locale is true, the charset to use when
     *                          converting. If not provided the current charset.
     *
     * @return string  The string with lowercase characters
     */
    public static function lower($string, $locale = false, $charset = null)
    {
        static $lowers;

        if ($locale) {
            /* The existence of mb_strtolower() depends on the platform. */
            if (self::extensionExists('mbstring')
                && function_exists('mb_strtolower')
            ) {
                if (is_null($charset)) {
                    $charset = self::$charset;
                }
                $old_error = error_reporting(0);
                $ret = mb_strtolower($string, self::_mbstringCharset($charset));
                error_reporting($old_error);
                if (!empty($ret)) {
                    return $ret;
                }
            }

            return strtolower($string);
        }

        if (!isset($lowers)) {
            $lowers = [];
        }
        if (!isset($lowers[$string])) {
            $language = setlocale(LC_CTYPE, 0);
            setlocale(LC_CTYPE, 'C');
            $lowers[$string] = strtolower($string);
            setlocale(LC_CTYPE, $language);
        }

        return $lowers[$string];
    }

    /**
     * Makes a string uppercase.
     *
     * @param string $string the string to be converted
     * @param bool $locale if true the string will be converted based on a
     *                          given charset, locale independent else
     * @param string $charset If $locale is true, the charset to use when
     *                          converting. If not provided the current charset.
     *
     * @return string  The string with uppercase characters
     */
    public static function upper($string, $locale = false, $charset = null)
    {
        static $uppers;

        if ($locale) {
            /* The existence of mb_strtoupper() depends on the
             * platform. */
            if (function_exists('mb_strtoupper')) {
                if (is_null($charset)) {
                    $charset = self::$charset;
                }
                $old_error = error_reporting(0);
                $ret = mb_strtoupper($string, self::_mbstringCharset($charset));
                error_reporting($old_error);
                if (!empty($ret)) {
                    return $ret;
                }
            }

            return strtoupper($string);
        }

        if (!isset($uppers)) {
            $uppers = [];
        }
        if (!isset($uppers[$string])) {
            $language = setlocale(LC_CTYPE, 0);
            setlocale(LC_CTYPE, 'C');
            $uppers[$string] = strtoupper($string);
            setlocale(LC_CTYPE, $language);
        }

        return $uppers[$string];
    }

    /**
     * Returns a string with the first letter capitalized if it is
     * alphabetic.
     *
     * @param string $string the string to be capitalized
     * @param bool $locale if true the string will be converted based on a
     *                          given charset, locale independent else
     * @param string|null $charset the charset to use, defaults to current charset
     *
     * @return string  the capitalized string
     */
    public static function ucfirst($string, bool $locale = false, ?string $charset = null): string
    {
        if ($locale) {
            $first = self::substr($string, 0, 1, $charset);
            if (self::isAlpha($first, $charset)) {
                $string = self::upper($first, true, $charset) . self::substr(
                    $string,
                    1,
                    null,
                    $charset
                );
            }
        } else {
            $string = self::upper($string[0]) . substr($string, 1);
        }

        return $string;
    }

    /**
     * Returns part of a string.
     *
     * @param string $string the string to be converted
     * @param int $start the part's start position, zero based
     * @param int|null $length the part's length
     * @param string $charset the charset to use when calculating the part's
     *                         position and length, defaults to current
     *                         charset
     *
     * @return string  the string's part
     */
    public static function substr(string $string, int $start, ?int $length = null, ?string $charset = null): string
    {
        if (is_null($length)) {
            $length = self::length($string, $charset) - $start;
        }

        if ($length == 0) {
            return '';
        }

        /* Try iconv. */
        if (function_exists('iconv_substr')) {
            if (is_null($charset)) {
                $charset = self::$charset;
            }

            $old_error = error_reporting(0);
            $ret = iconv_substr($string, $start, $length, $charset);
            error_reporting($old_error);
            /* iconv_substr() returns false on failure. */
            if ($ret !== false) {
                return $ret;
            }
        }

        /* Try mbstring. */
        if (self::extensionExists('mbstring')) {
            if (is_null($charset)) {
                $charset = self::$charset;
            }
            $old_error = error_reporting(0);
            $ret = mb_substr($string, $start, $length, self::_mbstringCharset($charset));
            error_reporting($old_error);
            /* mb_substr() returns empty string on failure. */
            if ($ret !== '') {
                return $ret;
            }
        }

        return substr($string, $start, $length);
    }

    /**
     * Returns the character (not byte) length of a string.
     *
     * @param string $string the string to return the length of
     * @param string|null $charset the charset to use when calculating the string's
     *                        length
     *
     * @return string|int  the string's part
     */
    public static function length($string, ?string $charset = null)
    {
        if (is_null($charset)) {
            $charset = self::$charset;
        }
        $charset = self::lower($charset);
        if (in_array($charset, ['utf-8', 'utf8'])) {
            return strlen(mb_convert_encoding($string, 'ISO-8859-1'));
        }
        if (self::extensionExists('mbstring')) {
            $old_error = error_reporting(0);
            $ret = mb_strlen($string, self::_mbstringCharset($charset));
            error_reporting($old_error);
            if (!empty($ret)) {
                return $ret;
            }
        }

        return strlen($string);
    }

    /**
     * Returns the numeric position of the first occurrence of $needle
     * in the $haystack string.
     *
     * @param string $haystack the string to search through
     * @param string $needle the string to search for
     * @param int $offset allows to specify which character in haystack
     *                          to start searching
     * @param string|null $charset the charset to use when searching for the
     *                          $needle string
     *
     * @return int|false  the position of first occurrence
     */
    public static function pos($haystack, $needle, int $offset = 0, ?string $charset = null): int|false
    {
        if (self::extensionExists('mbstring')) {
            if (is_null($charset)) {
                $charset = self::$charset;
            }

            if (PHP_MAJOR_VERSION < 8) {
                $track_errors = ini_set('track_errors', 1);
            }

            $old_error = error_reporting(0);
            $ret = mb_strpos($haystack, $needle, $offset, self::_mbstringCharset($charset));
            error_reporting($old_error);

            if (PHP_MAJOR_VERSION < 8) {
                ini_set('track_errors', $track_errors);
            }

            if (!isset($php_errormsg)) {
                return $ret;
            }
        }

        return strpos($haystack, $needle, $offset);
    }

    /**
     * Returns a string padded to a certain length with another string.
     *
     * This method behaves exactly like str_pad but is multibyte safe.
     *
     * @param string $input the string to be padded
     * @param int $length the length of the resulting string
     * @param string $pad The string to pad the input string with. Must
     *                         be in the same charset as the input string.
     * @param int $type The padding type. One of STR_PAD_LEFT,
     *                         STR_PAD_RIGHT, or STR_PAD_BOTH.
     * @param string|null $charset the charset of the input and the padding
     *                         strings
     *
     * @return string  the padded string
     */
    public static function pad($input, $length, string $pad = ' ', int $type = STR_PAD_RIGHT, ?string $charset = null)
    {
        $mb_length = self::length($input, $charset);
        $sb_length = strlen($input);
        $pad_length = self::length($pad, $charset);

        /* Return if we already have the length. */
        if ($mb_length >= $length) {
            return $input;
        }

        /* Shortcut for single byte strings. */
        if ($mb_length == $sb_length && $pad_length == strlen($pad)) {
            return str_pad($input, $length, $pad, $type);
        }

        $output = '';

        switch ($type) {
            case STR_PAD_LEFT:
                $left = $length - $mb_length;
                $output = self::substr(
                    str_repeat($pad, ceil($left / $pad_length)),
                    0,
                    $left,
                    $charset
                ) . $input;
                break;
            case STR_PAD_BOTH:
                $left = floor(($length - $mb_length) / 2);
                $right = ceil(($length - $mb_length) / 2);
                $output = self::substr(str_repeat($pad, ceil($left / $pad_length)), 0, $left, $charset) . $input . self::substr(
                    str_repeat($pad, ceil($right / $pad_length)),
                    0,
                    $right,
                    $charset
                );
                break;
            case STR_PAD_RIGHT:
                $right = $length - $mb_length;
                $output = $input . self::substr(str_repeat($pad, ceil($right / $pad_length)), 0, $right, $charset);
                break;
        }

        return $output;
    }

    /**
     * Wraps the text of a message.
     *
     * @param string $string horde_String containing the text to wrap
     * @param int $width wrap the string at this number of
     *                               characters
     * @param string $break character(s) to use when breaking lines
     * @param bool $cut whether to cut inside words if a line
     *                               can't be wrapped
     * @param string|null $charset character set to use when breaking lines
     * @param bool $line_folding Whether to apply line folding rules per
     *                               RFC 822 or similar. The correct break
     *                               characters including leading whitespace
     *                               have to be specified too.
     *
     * @return string  horde_String containing the wrapped text
     *
     * @since Horde 3.2
     */
    public static function wordwrap(
        $string,
        int $width = 75,
        string $break = "\n",
        bool $cut = false,
        ?string $charset = null,
        bool $line_folding = false
    ) {
        /* Get the user's default character set if none passed in. */
        if (is_null($charset)) {
            $charset = self::$charset;
        }
        $charset = self::_mbstringCharset($charset);
        $string = self::convertCharset($string, $charset, 'utf-8');
        $wrapped = '';

        while (self::length($string, 'utf-8') > $width) {
            $line = self::substr($string, 0, $width, 'utf-8');
            $string = self::substr($string, self::length($line, 'utf-8'), null, 'utf-8');
            // Make sure didn't cut a word, unless we want hard breaks anyway.
            if (!$cut && preg_match('/^(.+?)(\s|\r?\n)/u', $string, $match)) {
                $line .= $match[1];
                $string = self::substr($string, self::length($match[1], 'utf-8'), null, 'utf-8');
            }
            // Wrap at existing line breaks.
            if (preg_match('/^(.*?)(\r?\n)(.*)$/u', $line, $match)) {
                $wrapped .= $match[1] . $match[2];
                $string = $match[3] . $string;
                continue;
            }
            // Wrap at the last colon or semicolon followed by a whitespace if
            // doing line folding.
            if ($line_folding
                && preg_match('/^(.*?)(;|:)(\s+.*)$/u', $line, $match)
            ) {
                $wrapped .= $match[1] . $match[2] . $break;
                $string = $match[3] . $string;
                continue;
            }
            // Wrap at the last whitespace of $line.
            if ($line_folding) {
                $sub = '(.+[^\s])';
            } else {
                $sub = '(.*)';
            }
            if (preg_match('/^' . $sub . '(\s+)(.*)$/u', $line, $match)) {
                $wrapped .= $match[1] . $break;
                $string = ($line_folding ? $match[2] : '') . $match[3] . $string;
                continue;
            }
            // Hard wrap if necessary.
            if ($cut) {
                $wrapped .= self::substr($line, 0, $width, 'utf-8') . $break;
                $string = self::substr($line, $width, null, 'utf-8') . $string;
                continue;
            }
            $wrapped .= $line;
        }

        return self::convertCharset($wrapped . $string, 'utf-8', $charset);
    }

    /**
     * Wraps the text of a message.
     *
     * @param string $text horde_String containing the text to wrap
     * @param int $length wrap $text at this number of characters
     * @param string $break_char character(s) to use when breaking lines
     * @param string|null $charset character set to use when breaking lines
     * @param bool $quote ignore lines that are wrapped with the '>'
     *                            character (RFC 2646)? If true, we don't
     *                            remove any padding whitespace at the end of
     *                            the string
     *
     * @return string  horde_String containing the wrapped text
     */
    public static function wrap(
        string $text,
        int $length = 80,
        string $break_char = "\n",
        ?string $charset = null,
        bool $quote = false
    ): string {
        $paragraphs = [];

        foreach (preg_split('/\r?\n/', $text) as $input) {
            if ($quote && str_starts_with($input, '>')) {
                $line = $input;
            } else {
                /* We need to handle the Usenet-style signature line
                 * separately; since the space after the two dashes is
                 * REQUIRED, we don't want to trim the line. */
                if ($input !== '-- ') {
                    $input = rtrim($input);
                }
                $line = self::wordwrap($input, $length, $break_char, false, $charset);
            }

            $paragraphs[] = $line;
        }

        return implode($break_char, $paragraphs);
    }

    /**
     * Returns true if the every character in the parameter is an alphabetic
     * character.
     *
     * @param string $string The string to test
     * @param string|null $charset The charset to use when testing the string
     *
     * @return bool  true if the parameter was alphabetic only
     */
    public static function isAlpha($string, ?string $charset = null): bool
    {
        if (!self::extensionExists('mbstring')) {
            return ctype_alpha((string) $string);
        }

        $charset = self::_mbstringCharset($charset);
        $old_charset = mb_regex_encoding();
        $old_error = error_reporting(0);

        if ($charset !== $old_charset) {
            mb_regex_encoding($charset);
        }
        $alpha = !mb_ereg_match('[^[:alpha:]]', (string) $string);
        if ($charset !== $old_charset) {
            mb_regex_encoding($old_charset);
        }

        error_reporting($old_error);

        return $alpha;
    }

    /**
     * Returns true if ever character in the parameter is a lowercase letter in
     * the current locale.
     *
     * @param string $string The string to test
     * @param string|null $charset The charset to use when testing the string
     *
     * @return bool  true if the parameter was lowercase
     */
    public static function isLower(string $string, ?string $charset = null): bool
    {
        return (self::lower($string, true, $charset) === $string)
            && self::isAlpha($string, $charset);
    }

    /**
     * Returns true if every character in the parameter is an uppercase letter
     * in the current locale.
     *
     * @param string $string the string to test
     * @param string|null $charset the charset to use when testing the string
     *
     * @return bool  true if the parameter was uppercase
     */
    public static function isUpper($string, ?string $charset = null): bool
    {
        return (self::upper($string, true, $charset) === $string)
            && self::isAlpha($string, $charset);
    }

    /**
     * Performs a multibyte safe regex match search on the text provided.
     *
     * @param string $text the text to search
     * @param array $regex The regular expressions to use, without perl
     *                         regex delimiters (e.g. '/' or '|').
     * @param string|null $charset the character set of the text
     *
     * @return array  the matches array from the first regex that matches
     *
     * @since Horde 3.1
     */
    public static function regexMatch($text, array $regex, ?string $charset = null)
    {
        if (!empty($charset)) {
            $regex = self::convertCharset($regex, $charset, 'utf-8');
            $text = self::convertCharset($text, $charset, 'utf-8');
        }

        $matches = [];
        foreach ($regex as $val) {
            if (preg_match('/' . $val . '/u', (string) $text, $matches)) {
                break;
            }
        }

        if (!empty($charset)) {
            $matches = self::convertCharset($matches, 'utf-8', $charset);
        }

        return $matches;
    }

    /**
     * Workaround charsets that don't work with mbstring public functions.
     *
     * @param string $charset the original charset
     *
     * @return string  the charset to use with mbstring public functions
     */
    protected static function _mbstringCharset(string $charset): string
    {
        /* mbstring public functions do not handle the 'ks_c_5601-1987' &
         * 'ks_c_5601-1989' charsets. However, these charsets are used, for
         * example, by various versions of Outlook to send Korean characters.
         * Use UHC (CP949) encoding instead. See, e.g.,
         * http://lists.w3.org/Archives/Public/ietf-charsets/2001AprJun/0030.html */
        if (in_array(self::lower($charset), ['ks_c_5601-1987', 'ks_c_5601-1989'])) {
            $charset = 'UHC';
        }

        return $charset;
    }
}
