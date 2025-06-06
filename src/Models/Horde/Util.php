<?php

namespace TechData\AS2SecureBundle\Models\Horde;

/*
 * Error code for a missing driver configuration.
 */
define('HORDE_ERROR_DRIVER_CONFIG_MISSING', 1);

/*
 * Error code for an incomplete driver configuration.
 */
define('HORDE_ERROR_DRIVER_CONFIG', 2);

/**
 * The Util:: class provides generally useful methods of different kinds.
 *
 * $Horde: framework/Horde_Util/Horde_Util.php,v 1.384.6.37 2009/07/21 18:17:23 slusarz Exp $
 *
 * Copyright 1999-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @author  Jon Parise <jon@horde.org>
 *
 * @since   Horde 3.0
 */
class Util
{
    /**
     * @var array<string, bool>|null
     */
    protected static ?array $files = null;

    /**
     * @var array<string, bool>|null
     */
    protected static ?array $securedel = null;

    protected static array $cache = [];

    /**
     * @template T of object
     * Returns an object's clone.
     *
     * @param T &$obj The object to clone
     *
     * @return T  the cloned object
     */
    public function &cloneObject(&$obj)
    {
        if (!is_object($obj)) {
            $bt = debug_backtrace();
            if (isset($bt[1])) {
                $caller = $bt[1]['public function'];
                if (isset($bt[1]['class'])) {
                    $caller = $bt[1]['class'] . $bt[1]['type'] . $caller;
                }
            } else {
                $caller = 'main';
            }
            $caller .= ' on line ' . $bt[0]['line'] . ' of ' . $bt[0]['file'];

            return $obj;
        }

        $unserialized = unserialize(serialize($obj));

        return $unserialized;
    }

    /**
     * Creates a temporary filename for the lifetime of the script, and
     * (optionally) register it to be deleted at request shutdown.
     *
     * @param string $prefix prefix to make the temporary name more
     *                         recognizable
     * @param bool $delete Delete the file at the end of the request?
     * @param string $dir directory to create the temporary file in
     * @param bool $secure If deleting file, should we securely delete the
     *                         file?
     *
     * @return string   Returns the full path-name to the temporary file.
     *                  Returns false if a temp file could not be created.
     */
    public function getTempFile(string $prefix = '', bool $delete = true, string $dir = '', bool $secure = false)
    {
        if (empty($dir) || !is_dir($dir)) {
            $tmp_dir = sys_get_temp_dir();
        } else {
            $tmp_dir = $dir;
        }

        if (empty($tmp_dir)) {
            return false;
        }

        $tmp_file = tempnam($tmp_dir, $prefix);

        /* If the file was created, then register it for deletion and return. */
        if (empty($tmp_file)) {
            return false;
        }

        if ($delete) {
            (new self())->deleteAtShutdown($tmp_file, true, $secure);
        }

        return $tmp_file;
    }

    /**
     * Removes given elements at request shutdown.
     *
     * If called with a filename will delete that file at request shutdown; if
     * called with a directory will remove that directory and all files in that
     * directory at request shutdown.
     *
     * If called with no arguments, return all elements to be deleted (this
     * should only be done by Horde_Util::_deleteAtShutdown).
     *
     * The first time it is called, it initializes the array and registers
     * Horde_Util::_deleteAtShutdown() as a shutdown public function - no need to do so
     * manually.
     *
     * The second parameter allows the unregistering of previously registered
     * elements.
     *
     * @param string|null $filename the filename to be deleted at the end of the
     *                           request
     * @param bool $register if true, then register the element for
     *                           deletion, otherwise, unregister it
     * @param bool $secure If deleting file, should we securely delete
     *                           the file?
     */
    public function deleteAtShutdown(
        ?string $filename = null,
        bool $register = true,
        bool $secure = false
    ): void {
        /* Initialization of variables and shutdown public functions. */
        if (is_null(self::$files)) {
            self::$files = [];
            self::$securedel = [];
            register_shutdown_function([self::class, '_deleteAtShutdown']);
        }

        if ($filename) {
            if ($register) {
                self::$files[$filename] = true;
                if ($secure) {
                    self::$securedel[$filename] = true;
                }
            } else {
                unset(
                    self::$files[$filename],
                    self::$securedel[$filename]
                );
            }
        }
    }

    /**
     * Deletes registered files at request shutdown.
     *
     * This public function should never be called manually; it is registered as a
     * shutdown public function by Horde_Util::deleteAtShutdown() and called automatically
     * at the end of the request. It will retrieve the list of folders and
     * files to delete from Horde_Util::deleteAtShutdown()'s static array, and then
     * iterate through, deleting folders recursively.
     *
     * Contains code from gpg_public functions.php.
     * Copyright 2002-2003 Braverock Ventures
     */
    public function _deleteAtShutdown()
    {
        foreach (self::$files as $file => $val) {
            /* Delete files */
            if ($val && file_exists($file)) {
                /* Should we securely delete the file by overwriting the data
                   with a random string? */
                if (isset(self::$securedel[$file])) {
                    $filesize = filesize($file);
                    /* See http://www.cs.auckland.ac.nz/~pgut001/pubs/secure_del.html.
                     * We save the random overwrites for efficiency reasons. */
                    $patterns = [
                        "\x55",
                        "\xaa",
                        "\x92\x49\x24",
                        "\x49\x24\x92",
                        "\x24\x92\x49",
                        "\x00",
                        "\x11",
                        "\x22",
                        "\x33",
                        "\x44",
                        "\x55",
                        "\x66",
                        "\x77",
                        "\x88",
                        "\x99",
                        "\xaa",
                        "\xbb",
                        "\xcc",
                        "\xdd",
                        "\xee",
                        "\xff",
                        "\x92\x49\x24",
                        "\x49\x24\x92",
                        "\x24\x92\x49",
                        "\x6d\xb6\xdb",
                        "\xb6\xdb\x6d",
                        "\xdb\x6d\xb6",
                    ];
                    $fp = fopen($file, 'r+');
                    foreach ($patterns as $pattern) {
                        $pattern = substr(str_repeat($pattern, floor($filesize / strlen($pattern)) + 1), 0, $filesize);
                        fwrite($fp, $pattern);
                        fseek($fp, 0);
                    }
                    fclose($fp);
                }
                @unlink($file);
            }
        }
    }

    /**
     * Caches the result of extension_loaded() calls.
     *
     * @param string $ext the extension name
     *
     * @return bool  Is the extension loaded?
     */
    public function extensionExists(string $ext): bool
    {
        if (!isset($cache[$ext])) {
            $cache[$ext] = extension_loaded($ext);
        }

        return $cache[$ext];
    }
}
