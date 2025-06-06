<?php

namespace TechData\AS2SecureBundle\Models\Horde\MIME;

/*
 * The character(s) used internally for EOLs.
 */

use TechData\AS2SecureBundle\Models\Horde\HordeString;
use TechData\AS2SecureBundle\Models\Horde\MIME;
use TechData\AS2SecureBundle\Models\Horde\Util;
use TechData\AS2SecureBundle\Models\Mail\MimeDecode;

define('MIME_PART_EOL', "\n");

/*
 * The character string designated by RFCs 822/2045 to designate EOLs in MIME
 * messages.
 */
define('MIME_PART_RFC_EOL', "\r\n");

/* Default MIME parameters. */

/*
 * The default MIME character set.
 */
define('MIME_DEFAULT_CHARSET', 'us-ascii');

/*
 * The default MIME description.
 */
define('MIME_DEFAULT_DESCRIPTION', 'unnamed');

/*
 * The default MIME disposition.
 */
define('MIME_DEFAULT_DISPOSITION', 'inline');

/*
 * The default MIME encoding.
 */
define('MIME_DEFAULT_ENCODING', '7bit');

/**
 * The MIME_Part:: class provides a wrapper around MIME parts and methods
 * for dealing with them.
 *
 * $Horde: framework/MIME/MIME/Part.php,v 1.177.4.27 2009/06/17 19:49:13 slusarz Exp $
 *
 * Copyright 1999-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @author  Michael Slusarz <slusarz@horde.org>
 */
class Part
{
    /**
     * The type (ex.: text) of this part.
     * Per RFC 2045, the default is 'application'.
     */
    protected string $_type = 'application';

    /**
     * The subtype (ex.: plain) of this part.
     * Per RFC 2045, the default is 'octet-stream'.
     */
    protected string $_subtype = 'octet-stream';

    /**
     * The body of the part.
     */
    protected string $_contents = '';

    /**
     * The desired transfer encoding of this part.
     */
    protected string $_transferEncoding = MIME_DEFAULT_ENCODING;

    /**
     * The current transfer encoding of the contents of this part.
     */
    protected ?string $_currentEncoding = null;

    /**
     * Should the message be encoded via 7-bit?
     */
    protected bool $_encode7bit = true;

    /**
     * The description of this part.
     */
    protected string $_description = '';

    /**
     * The disposition of this part (inline or attachment).
     */
    protected string $_disposition = MIME_DEFAULT_DISPOSITION;

    /**
     * The disposition parameters of this part.
     */
    protected array $_dispositionParameters = [];

    /**
     * The content type parameters of this part.
     */
    protected array $_contentTypeParameters = [];

    /**
     * The subparts of this part.
     * @var mixed[]|Part[]
     */
    protected array $_parts = [];

    /**
     * Information/Statistics on the subpart.
     */
    protected array $_information = [];

    /**
     * The list of CIDs for this part.
     */
    protected array $_cids = [];

    /**
     * The MIME ID of this part.
     */
    protected ?string $_mimeid = null;

    /**
     * The sequence to use as EOL for this part.
     * The default is currently to output the EOL sequence internally as
     * just "\n" instead of the canonical "\r\n" required in RFC 822 & 2045.
     * To be RFC complaint, the full <CR><LF> EOL combination should be used
     * when sending a message.
     */
    protected string $_eol = MIME_PART_EOL;

    /**
     * Internal class flags.
     */
    protected array $_flags = [];

    /**
     * Part -> ID mapping cache.
     */
    protected array $_idmap = [];

    /**
     * Unique MIME_Part boundary string.
     */
    protected ?string $_boundary = null;

    /**
     * Default value for this Part's size.
     */
    protected int $_bytes = 0;

    /**
     * The content-ID for this part.
     */
    protected ?string $_contentid = null;

    /**
     * MIME_Part constructor.
     *
     * @param string|null $mimetype the content type of the part
     * @param string|null $contents the body of the part
     * @param string $charset the character set of the part
     * @param string|null $disposition the content disposition of the part
     * @param string|null $encoding the content encoding of the contents
     */
    public function __construct(
        ?string $mimetype = null,
        ?string $contents = null,
        string $charset = MIME_DEFAULT_CHARSET,
        ?string $disposition = null,
        ?string $encoding = null
    ) {
        /* Create the unique MIME_Part boundary string. */
        $this->_generateBoundary();

        /* The character set should always be set, even if we are dealing
         * with Content-Types other than text/*. */
        $this->setCharset($charset);

        if (!is_null($mimetype)) {
            $this->setType($mimetype);
        }
        if (!is_null($contents)) {
            $this->setContents($contents, $encoding);
        }
        if (!is_null($disposition)) {
            $this->setDisposition($disposition);
        }
    }

    /**
     * Set the content-disposition of this part.
     *
     * @param string $disposition the content-disposition to set (inline or attachment)
     */
    public function setDisposition(string $disposition): void
    {
        $disposition = HordeString::lower($disposition);

        if (in_array($disposition, ['inline', 'attachment'])) {
            $this->_disposition = $disposition;
        }
    }

    /**
     * Get the content-disposition of this part.
     *
     * @return string  the part's content-disposition
     */
    public function getDisposition(): string
    {
        return $this->_disposition;
    }

    /**
     * Set the name of this part.
     * TODO: MIME encode here instead of in header() - add a charset
     * parameter.
     *
     * @param string|null $name the name to set
     */
    public function setName(?string $name): void
    {
        $this->setContentTypeParameter('name', $name);
    }

    /**
     * Get the name of this part.
     *
     * @param bool $decode MIME decode description?
     * @param bool $default If the name parameter doesn't exist, should we
     *                          use the default name from the description
     *                          parameter?
     *
     * @return string  the name of the part
     */
    public function getName(bool $decode = false, bool $default = false): string
    {
        $name = $this->getContentTypeParameter('name');

        if ($default && empty($name)) {
            $name = preg_replace('|\W|', '_', $this->getDescription(false, true));
        }

        if ($decode) {
            return trim(MIME::decode($name));
        }

        return $name;
    }

    /**
     * Set the body contents of this part.
     *
     * @param string|null $contents the part body
     * @param string|null $encoding the current encoding of the contents
     */
    public function setContents(?string $contents, ?string $encoding = null): void
    {
        $this->_contents = $contents;
        $this->_flags['contentsSet'] = true;
        $this->_currentEncoding = (is_null($encoding)) ? $this->getCurrentEncoding() : (new MIME())->encoding(
            $encoding,
            MIME_STRING
        );
    }

    /**
     * Add to the body contents of this part.
     *
     * @param string|null $contents the contents to append to the current part
     *                          body
     * @param string|null $encoding The current encoding of the contents. If not
     *                          specified, will try to auto determine the
     *                          encoding.
     */
    public function appendContents(?string $contents, ?string $encoding = null): void
    {
        $this->setContents($this->_contents . $contents, $encoding);
    }

    /**
     * Clears the body contents of this part.
     */
    public function clearContents(): void
    {
        $this->_contents = '';
        $this->_flags['contentsSet'] = false;
        $this->_currentEncoding = null;
    }

    /**
     * Return the body of the part.
     *
     * @return string  the raw body of the part
     */
    public function getContents(): string
    {
        return $this->_contents ?? '';
    }

    /**
     * Returns the contents in strict RFC 822 & 2045 output - namely, all
     * newlines end with the canonical <CR><LF> sequence.
     *
     * @return string  the entire MIME part
     */
    public function getCanonicalContents(): string
    {
        return $this->replaceEOL($this->_contents, MIME_PART_RFC_EOL);
    }

    /**
     * Transfer encode the contents (to the transfer encoding identified via
     * getTransferEncoding()) and set as the part's new contents.
     */
    public function transferEncodeContents(): void
    {
        $contents = $this->transferEncode();
        $this->_currentEncoding = $this->_flags['lastTransferEncode'];
        $this->setContents($contents, $this->_currentEncoding);
        $this->setTransferEncoding($this->_currentEncoding);
    }

    /**
     * Transfer decode the contents and set them as the new contents.
     */
    public function transferDecodeContents(): void
    {
        $contents = $this->transferDecode();
        $this->_currentEncoding = $this->_flags['lastTransferDecode'];
        $this->setTransferEncoding($this->_currentEncoding);

        /* Don't set contents if they are empty, because this will do stuff
           like reset the internal bytes field, even though we shouldn't do
           that (the user has their reasons to set the bytes field to a
           non-zero value without putting the contents into this part. */
        if ($contents !== '') {
            $this->setContents($contents, $this->_currentEncoding);
        }
    }

    /**
     * Set the mimetype of this part.
     *
     * @param string $mimetype The mimetype to set (ex.: text/plain).
     */
    public function setType(string $mimetype): void
    {
        /* RFC 2045: Any entity with unrecognized encoding must be treated
           as if it has a Content-Type of "application/octet-stream"
           regardless of what the Content-Type field actually says. */
        if ($this->_transferEncoding === 'x-unknown') {
            return;
        }

        /* Set the 'setType' flag. */
        $this->_flags['setType'] = true;

        [$this->_type, $this->_subtype] = explode('/', HordeString::lower($mimetype));
        if ($type = (new MIME())->type($this->_type, MIME_STRING)) {
            $this->_type = $type;

            /* Set the boundary string for 'multipart/*' parts. */
            if ($type === 'multipart') {
                if (!$this->getContentTypeParameter('boundary')) {
                    $this->setContentTypeParameter('boundary', $this->_generateBoundary());
                }
            } else {
                $this->clearContentTypeParameter('boundary');
            }
        } else {
            $this->_type = 'x-unknown';
            $this->clearContentTypeParameter('boundary');
        }
    }

    /**
     * Get the full MIME Content-Type of this part.
     *
     * @param bool $charset append character set information to the end of
     *                          the content type if this is a text/* part
     *
     * @return string|false  The mimetype of this part (ex.: text/plain; charset=us-ascii).
     */
    public function getType(bool $charset = false): false|string
    {
        if (!isset($this->_type, $this->_subtype)) {
            return false;
        }
        $ptype = $this->getPrimaryType();
        $type = $ptype . '/' . $this->getSubType();
        if ($charset && ($ptype === 'text')) {
            $type .= '; charset=' . $this->getCharset();
        }

        return $type;
    }

    /**
     * If the subtype of a MIME part is unrecognized by an application, the
     * default type should be used instead (See RFC 2046).  This method
     * returns the default subtype for a particular primary MIME Type.
     *
     * @return string  The default mimetype of this part (ex.: text/plain).
     */
    public function getDefaultType(): string
    {
        return match ($this->getPrimaryType()) {
            /* RFC 2046 (4.1.4): text parts default to text/plain. */
            'text' => 'text/plain',
            /* RFC 2046 (4.2, 4.3, 4.4, 4.5.3, 5.2.4): all others default to application/octet-stream. */
            'multipart' => 'multipart/mixed',
            /* RFC 2046 (5.1.3): multipart parts default to multipart/mixed. */
            default => 'application/octet-stream',
        };
    }

    /**
     * Get the primary type of this part.
     *
     * @return string  the primary MIME type of this part
     */
    public function getPrimaryType(): string
    {
        return $this->_type;
    }

    /**
     * Get the subtype of this part.
     *
     * @return string  the MIME subtype of this part
     */
    public function getSubType(): string
    {
        return $this->_subtype;
    }

    /**
     * Set the character set of this part.
     *
     * @param string $charset the character set of this part
     */
    public function setCharset(string $charset): void
    {
        $this->setContentTypeParameter('charset', $charset);
    }

    /**
     * Get the character set to use for of this part.  Returns a charset for
     * all types (not just 'text/*') since we use this charset to determine
     * how to encode text in MIME headers.
     *
     * @return string|null  The character set of this part.  Returns null if there
     *                 is no character set.
     */
    public function getCharset(): ?string
    {
        $charset = $this->getContentTypeParameter('charset');

        return (empty($charset)) ? null : $charset;
    }

    /**
     * Set the description of this part.
     *
     * @param string $description the description of this part
     */
    public function setDescription(string $description): void
    {
        $this->_description = MIME::encode($description, $this->getCharset());
    }

    /**
     * Get the description of this part.
     *
     * @param bool $decode MIME decode description?
     * @param bool $default If the name parameter doesn't exist, should we
     *                          use the default name from the description
     *                          parameter?
     *
     * @return string  the description of this part
     */
    public function getDescription(bool $decode = false, bool $default = false): string
    {
        $desc = $this->_description;

        if ($default && empty($desc)) {
            $desc = $this->getName();
            if (empty($desc)) {
                $desc = MIME_DEFAULT_DESCRIPTION;
            }
        }

        if ($decode) {
            return MIME::decode($desc);
        }

        return $desc;
    }

    /**
     * Set the transfer encoding to use for this part.
     *
     * @param string|null $encoding the transfer encoding to use
     */
    public function setTransferEncoding(?string $encoding): void
    {
        if ($mime_encoding = (new MIME())->encoding($encoding, MIME_STRING)) {
            $this->_transferEncoding = $mime_encoding;
        } else {
            /* RFC 2045: Any entity with unrecognized encoding must be treated
               as if it has a Content-Type of "application/octet-stream"
               regardless of what the Content-Type field actually says. */
            $this->setType('application/octet-stream');
            $this->_transferEncoding = 'x-unknown';
        }
    }

    /**
     * Add a MIME subpart.
     *
     * @param self|MIME_Part $mime_part add a MIME_Part subpart to the current
     *                              MIME_Part
     * @param string|null $index the index of the added MIME_Part
     */
    public function addPart($mime_part, ?string $index = null): void
    {
        /* Add the part to the parts list. */
        if (is_null($index)) {
            $id = array_key_last($this->_parts) + 1;
            $ptr = &$this->_parts;
        } else {
            $ptr = &$this->_partFind($index, $this->_parts, true);
            if ($pos = strrpos($index, '.')) {
                $id = substr($index, $pos + 1);
            } else {
                $id = $index;
            }
        }

        /* Set the MIME ID if it has not already been set. */
        if ($mime_part->getMIMEId() === null) {
            $mime_part->setMIMEId($id);
        }

        /* Store the part now. */
        $ptr[$id] = $mime_part;

        /* Clear the ID -> Part mapping cache. */
        $this->_idmap = [];
    }

    /**
     * Get a list of all MIME subparts.
     *
     * @return array  an array of the MIME_Part subparts
     */
    public function getParts(): array
    {
        return $this->_parts;
    }

    /**
     * Retrieve a specific MIME part.
     *
     * @param string $id the MIME_Part ID string
     *
     * @return false|Part|array|static  the MIME_Part requested, or false if the part
     *                    doesn't exist
     */
    public function getPart($id): false|Part|array|static
    {
        $mimeid = $this->getMIMEId();

        /* This will convert '#.0' to simply '#', which is how the part is
         * internally stored. */
        $search_id = $id;
        if (($str = strrchr($id, '.'))
            && ($str === '.0')
        ) {
            $search_id = substr($search_id, 0, -2);
        }

        /* Return this part if:
           1) There is only one part (e.g. the MIME ID is 0, or the
              MIME ID is 1 and there are no subparts.
           2) $id matches this parts MIME ID. */
        if (($search_id == 0)
            || (($search_id == 1) && !count($this->_parts))
            || (!empty($mimeid) && ($search_id == $mimeid))
        ) {
            $part = $this;
        } else {
            $part = $this->_partFind($id, $this->_parts);
        }

        if ($part
            && ($search_id != $id)
            && ($part->getType() === 'message/rfc822')
        ) {
            $ret_part = (new Util())->cloneObject($part);
            $ret_part->_parts = [];

            return $ret_part;
        }

        return $part;
    }

    /**
     * Remove a MIME_Part subpart.
     *
     * @param string $id the MIME Part to delete
     */
    public function removePart(string $id): void
    {
        if ($ptr = &$this->_partFind($id, $this->_parts)) {
            unset($ptr);
            $this->_idmap = [];
        }
    }

    /**
     * Alter a current MIME subpart.
     *
     * @param string $id the MIME Part ID to alter
     * @param MIME_Part|self $mime_part the MIME Part to store
     */
    public function alterPart(string $id, $mime_part): void
    {
        if ($ptr = &$this->_partFind($id, $this->_parts)) {
            $ptr = $mime_part;
            $this->_idmap = [];
        }
    }

    /**
     * Function used to find a specific MIME Part by ID.
     *
     * @param string $id the MIME_Part ID string
     * @param array &$parts A list of MIME_Part objects
     * @param bool $retarray Return a pointer to the array that stores
     *                           (would store) the part rather than the part
     *                           itself?
     */
    public function &_partFind($id, array &$parts, bool $retarray = false)
    {
        /* Pointers don't persist through sessions; therefore, we must make
         * sure that the IdMap is destroyed at the end of each request.
         * How can we do this? We check to see if $_idmap contains an array
         * of MIME_Parts or an array of arrays. */
        $check = reset($this->_idmap);
        if (empty($check) || !is_a($check, 'MIME_Part')) {
            $this->_idmap = [];
            $this->_generateIdMap($this->_parts);
        }

        if ($retarray) {
            if ($pos = strrpos($id, '.')) {
                $id = substr($id, 0, $pos);
            } else {
                return $parts;
            }
        }

        if (isset($this->_idmap[$id])) {
            return $this->_idmap[$id];
        }
        $part = false;

        return $part;
    }

    /**
     * Generates a mapping of MIME_Parts with their MIME IDs.
     *
     * @param array &$parts An array of MIME_Parts to map
     */
    protected function _generateIdMap(array &$parts): void
    {
        if (!empty($parts)) {
            foreach (array_keys($parts) as $key) {
                $ptr = &$parts[$key];
                $this->_idmap[$ptr->getMIMEId()] = &$ptr;
                $this->_generateIdMap($ptr->_parts);
            }
        }
    }

    /**
     * Add information about the MIME_Part.
     *
     * @param string $label the information label
     * @param mixed $data the information to store
     */
    public function setInformation(string $label, mixed $data): void
    {
        $this->_information[$label] = $data;
    }

    /**
     * Retrieve information about the MIME_Part.
     *
     * @param string $label the information label
     *
     * @return mixed  The information requested.
     *                Returns false if $label is not set.
     */
    public function getInformation(string $label): mixed
    {
        return $this->_information[$label] ?? false;
    }

    /**
     * Add a disposition parameter to this part.
     *
     * @param string $label the disposition parameter label
     * @param string $data the disposition parameter data
     */
    public function setDispositionParameter(string $label, string $data): void
    {
        $this->_dispositionParameters[$label] = $data;
    }

    /**
     * Get a disposition parameter from this part.
     *
     * @param string $label the disposition parameter label
     *
     * @return string|false  The data requested.
     *                 Returns false if $label is not set.
     */
    public function getDispositionParameter(string $label): false|string
    {
        return $this->_dispositionParameters[$label] ?? false;
    }

    /**
     * Get all parameters from the Content-Disposition header.
     *
     * @return array  an array of all the parameters
     *                Returns the empty array if no parameters set
     */
    public function getAllDispositionParameters(): array
    {
        return $this->_dispositionParameters;
    }

    /**
     * Add a content type parameter to this part.
     *
     * @param string $label the disposition parameter label
     * @param string|null $data the disposition parameter data
     */
    public function setContentTypeParameter(string $label, ?string $data): void
    {
        $this->_contentTypeParameters[$label] = $data;
    }

    /**
     * Clears a content type parameter from this part.
     *
     * @param string $label the disposition parameter label
     */
    public function clearContentTypeParameter(string $label): void
    {
        unset($this->_contentTypeParameters[$label]);
    }

    /**
     * Get a content type parameter from this part.
     *
     * @param string $label the content type parameter label
     *
     * @return string|false  The data requested.
     *                 Returns false if $label is not set.
     */
    public function getContentTypeParameter(string $label): false|string
    {
        return $this->_contentTypeParameters[$label] ?? false;
    }

    /**
     * Get all parameters from the Content-Type header.
     *
     * @return array  an array of all the parameters
     *                Returns the empty array if no parameters set
     */
    public function getAllContentTypeParameters(): array
    {
        return $this->_contentTypeParameters;
    }

    /**
     * Sets a new string to use for EOLs.
     *
     * @param string $eol the string to use for EOLs
     */
    public function setEOL(string $eol): void
    {
        $this->_eol = $eol;
    }

    /**
     * Get the string to use for EOLs.
     *
     * @return string  the string to use for EOLs
     */
    public function getEOL(): string
    {
        return $this->_eol;
    }

    /**
     * Add the appropriate MIME headers for this part to an existing array.
     *
     * @param array $headers an array of any other headers for the part
     *
     * @return array  the headers, with the MIME headers added
     */
    public function header(array $headers = []): array
    {
        $eol = $this->getEOL();
        $ptype = $this->getPrimaryType();

        /* Get the character set for this part. */
        $charset = $this->getCharset();

        /* Get the Content-Type - this is ALWAYS required. */
        $ctype = $this->getType(true);
        foreach ($this->getAllContentTypeParameters() as $key => $value) {
            /* Skip the charset key since that would have already been
             * added to $ctype by getType(). */
            if ($key === 'charset') {
                continue;
            }
            $encode_2231 = MIME::encodeRFC2231($key, $value, $charset);
            /* Try to work around non RFC 2231-compliant MUAs by sending both
             * a RFC 2047-like parameter name and then the correct RFC 2231
             * parameter.  See:
             *   http://lists.horde.org/archives/dev/Week-of-Mon-20040426/014240.html */
            /*if (!empty($GLOBALS['conf']['mailformat']['brokenrfc2231']) &&
                ((strpos($encode_2231, '*=') !== false) ||
                 (strpos($encode_2231, '*0=') !== false))) {
                $ctype .= '; ' . $key . '="' . MIME::encode($value, $charset) . '"';
            }*/
            $ctype .= '; ' . $encode_2231;
        }
        $headers['Content-Type'] = (new MIME())->wrapHeaders('Content-Type', $ctype, $eol);

        /* Get the description, if any. */
        if ($descrip = $this->getDescription()) {
            $headers['Content-Description'] = (new MIME())->wrapHeaders(
                'Content-Description',
                MIME::encode($descrip, $charset),
                $eol
            );
        }

        /* message/* parts require no additional header information. */
        if ($ptype === 'message') {
            return $headers;
        }

        /* Don't show Content-Disposition for multipart messages unless
           there is a name parameter. */
        $name = $this->getName();
        if (($ptype !== 'multipart') || !empty($name)) {
            $disp = $this->getDisposition();

            /* Add any disposition parameter information, if available. */
            if (!empty($name)) {
                $encode_2231 = MIME::encodeRFC2231('filename', $name, $charset);
                /* Same broken RFC 2231 workaround as above. */
                /*if (!empty($GLOBALS['conf']['mailformat']['brokenrfc2231']) &&
                    ((strpos($encode_2231, '*=') !== false) ||
                     (strpos($encode_2231, '*0=') !== false))) {
                    $disp .= '; filename="' . MIME::encode($name, $charset) . '"';
                }*/
                $disp .= '; ' . $encode_2231;
            }

            $headers['Content-Disposition'] = (new MIME())->wrapHeaders('Content-Disposition', $disp, $eol);
        }

        /* Add transfer encoding information. */
        $headers['Content-Transfer-Encoding'] = $this->getTransferEncoding();

        /* Add content ID information. */
        if (!is_null($this->_contentid)) {
            $headers['Content-ID'] = $this->_contentid;
        }

        return $headers;
    }

    /**
     * Return the entire part in MIME format. Includes headers on request.
     *
     * @param bool $headers Include the MIME headers?
     *
     * @return string  the MIME string
     */
    public function toString(bool $headers = true): string
    {
        $eol = $this->getEOL();
        $ptype = $this->getPrimaryType();

        if ($headers) {
            $text = '';
            foreach ($this->header() as $key => $val) {
                $text .= $key . ': ' . $val . $eol;
            }
            $text .= $eol;
        }

        /* Any information about a message/* is embedded in the message
           contents themself. Simply output the contents of the part
           directly and return. */
        if ($ptype === 'message') {
            if (isset($text)) {
                return $text . $this->_contents;
            }

            return $this->_contents;
        }

        if (isset($text)) {
            $text .= $this->transferEncode();
        } else {
            $text = $this->transferEncode();
        }

        /* Deal with multipart messages. */
        if ($ptype === 'multipart') {
            $boundary = trim($this->getContentTypeParameter('boundary'), '"');
            if ($this->_contents === '') {
                $text .= 'This message is in MIME format.' . $eol;
            }
            reset($this->_parts);

            foreach ($this->_parts as $part) {
                if (!$part instanceof self) {
                    [,$part] = $part;
                }

                $text .= $eol . '--' . $boundary . $eol;
                $oldEOL = $part->getEOL();
                $part->setEOL($eol);
                $text .= $part->toString(true);
                $part->setEOL($oldEOL);
            }

            $text .= $eol . '--' . $boundary . '--' . $eol;
        }

        return $text;
    }

    /**
     * Returns the encoded part in strict RFC 822 & 2045 output - namely, all
     * newlines end with the canonical <CR><LF> sequence.
     *
     * @param bool $headers Include the MIME headers?
     *
     * @return string  the entire MIME part
     */
    public function toCanonicalString(bool $headers = true): string
    {
        $string = $this->toString($headers);

        return $this->replaceEOL($string, MIME_PART_RFC_EOL);
    }

    /**
     * Should we make sure the message is encoded via 7-bit (e.g. to adhere
     * to mail delivery standards such as RFC 2821)?
     *
     * @param bool $use7bit Use 7-bit encoding?
     */
    public function strict7bit(bool $use7bit): void
    {
        $this->_encode7bit = $use7bit;
    }

    /**
     * Get the transfer encoding for the part based on the user requested
     * transfer encoding and the current contents of the part.
     *
     * @return string  the transfer-encoding of this part
     */
    public function getTransferEncoding(): string
    {
        $encoding = $this->_transferEncoding;
        $ptype = $this->getPrimaryType();
        $text = str_replace($this->getEOL(), ' ', $this->_contents);

        /* If there are no contents, return whatever the current value of
           $_transferEncoding is. */
        if (empty($text)) {
            return $encoding;
        }

        switch ($ptype) {
            case 'message':
                /* RFC 2046 [5.2.1] - message/rfc822 messages only allow 7bit,
                   8bit, and binary encodings. If the current encoding is either
                   base64 or q-p, switch it to 8bit instead.
                   RFC 2046 [5.2.2, 5.2.3, 5.2.4] - All other message/* messages
                   only allow 7bit encodings. */
                $encoding = ($this->getSubType() === 'rfc822') ? '8bit' : '7bit';
                break;

            case 'text':
                $eol = $this->getEOL();
                if (MIME::is8bit($text)) {
                    $encoding = ($this->_encode7bit) ? 'quoted-printable' : '8bit';
                } elseif (preg_match('/(?:' . $eol . '|^)[^' . $eol . ']{999,}(?:' . $eol . '|$)/', $this->_contents)) {
                    /* If the text is longer than 998 characters between
                     * linebreaks, use quoted-printable encoding to ensure the
                     * text will not be chopped (i.e. by sendmail if being sent
                     * as mail text). */
                    $encoding = 'quoted-printable';
                }
                break;

            default:
                if (MIME::is8bit($text)) {
                    $encoding = ($this->_encode7bit) ? 'base64' : '8bit';
                }
                break;
        }

        /* Need to do one last check for binary data if encoding is 7bit or
         * 8bit.  If the message contains a NULL character at all, the message
         * MUST be in binary format. RFC 2046 [2.7, 2.8, 2.9]. Q-P and base64
         * can handle binary data fine so no need to switch those encodings. */
        if ((($encoding === '8bit') || ($encoding === '7bit'))
            && preg_match('/\x00/', $text)
        ) {
            $encoding = ($this->_encode7bit) ? 'base64' : 'binary';
        }

        return $encoding;
    }

    /**
     * Retrieves the current encoding of the contents in the object.
     *
     * @return string  the current encoding
     */
    public function getCurrentEncoding(): string
    {
        return (is_null($this->_currentEncoding)) ? $this->_transferEncoding : $this->_currentEncoding;
    }

    /**
     * Encodes the contents with the part's transfer encoding.
     *
     * @return string  the encoded text
     */
    public function transferEncode(): string
    {
        $encoding = $this->getTransferEncoding();
        $eol = $this->getEOL();

        /* Set the 'lastTransferEncode' flag so that transferEncodeContents()
           can save a call to getTransferEncoding(). */
        $this->_flags['lastTransferEncode'] = $encoding;

        /* If contents are empty, or contents are already encoded to the
           correct encoding, return now. */
        if ($this->_contents === '' || ($encoding === $this->_currentEncoding)) {
            return $this->_contents;
        }

        switch ($encoding) {
            /* Base64 Encoding: See RFC 2045, section 6.8 */
            case 'base64':
                /* Keeping these two lines separate seems to use much less
                   memory than combining them (as of PHP 4.3). */
                $encoded_contents = base64_encode($this->_contents);

                return rtrim(chunk_split($encoded_contents, 76, $eol), $eol);

                /* Quoted-Printable Encoding: See RFC 2045, section 6.7 */
            case 'quoted-printable':
                $output = MIME::quotedPrintableEncode($this->_contents, $eol);
                if (($eollength = HordeString::length($eol))
                    && (substr($output, $eollength * -1) === $eol)
                ) {
                    return substr($output, 0, $eollength * -1);
                }

                return $output;

            default:
                return $this->replaceEOL($this->_contents);
        }
    }

    /**
     * Decodes the contents of the part to either a 7bit or 8bit encoding.
     *
     * @return string  The decoded text.
     *                 Returns the empty string if there is no text to decode.
     */
    public function transferDecode(): string
    {
        $encoding = $this->getCurrentEncoding();

        /* If the contents are empty, return now. */
        if ($this->_contents === '') {
            $this->_flags['lastTransferDecode'] = $encoding;

            return $this->_contents;
        }

        switch ($encoding) {
            case 'base64':
                $message = base64_decode($this->_contents);
                $this->_flags['lastTransferDecode'] = '8bit';
                break;

            case 'quoted-printable':
                $message = preg_replace("/=\r?\n/", '', $this->_contents);
                $message = $this->replaceEOL($message);
                $message = quoted_printable_decode($message);
                $this->_flags['lastTransferDecode'] = (MIME::is8bit($message)) ? '8bit' : '7bit';
                break;

                /* Support for uuencoded encoding - although not required by RFCs,
                   some mailers may still encode this way. */
            case 'uuencode':
            case 'x-uuencode':
            case 'x-uue':
                if (function_exists('convert_uudecode')) {
                    $message = convert_uuencode($this->_contents);
                } else {
                    $files = (new MimeDecode(''))->uudecode($this->_contents);
                    $message = $files[0]['filedata'];
                }
                $this->_flags['lastTransferDecode'] = '8bit';
                break;

            default:
                if (isset($this->_flags['lastTransferDecode'])
                    && ($this->_flags['lastTransferDecode'] != $encoding)
                ) {
                    $message = $this->replaceEOL($this->_contents);
                } else {
                    $message = $this->_contents;
                }
                $this->_flags['lastTransferDecode'] = $encoding;
                break;
        }

        return $message;
    }

    /**
     * Split the contents of the current Part into its respective subparts,
     * if it is multipart MIME encoding. Unlike the imap_*() public functions, this
     * will preserve all MIME header information.
     *
     * The boundary content-type parameter must be set for this public function to
     * work correctly.
     *
     * @return bool  True if the contents were successfully split.
     *                  False if any error occurred.
     */
    public function splitContents(): bool
    {
        if (!($boundary = $this->getContentTypeParameter('boundary'))) {
            return false;
        }

        if ($this->_contents === '') {
            return false;
        }

        $eol = $this->getEOL();

        $boundary = '--' . $boundary;
        if (str_starts_with($this->_contents, $boundary)) {
            $pos1 = 0;
        } else {
            $pos1 = strpos($this->_contents, $eol . $boundary);
        }
        if ($pos1 === false) {
            return false;
        }
        $pos1 = strpos($this->_contents, $eol, $pos1 + 1);
        if ($pos1 === false) {
            return false;
        }
        $pos1 += strlen($eol);

        reset($this->_parts);
        $part_ptr = key($this->_parts);

        while ($pos2 = strpos($this->_contents, $eol . $boundary, $pos1)) {
            $this->_parts[$part_ptr]->setContents(substr($this->_contents, $pos1, $pos2 - $pos1));
            $this->_parts[$part_ptr]->splitContents();
            next($this->_parts);
            $part_ptr = key($this->_parts);
            if (is_null($part_ptr)) {
                return false;
            }
            $pos1 = strpos($this->_contents, $eol, $pos2 + 1);
            if ($pos1 === false) {
                return true;
            }
            $pos1 += strlen($eol);
        }

        return true;
    }

    /**
     * Replace newlines in this part's contents with those specified by either
     * the given newline sequence or the part's current EOL setting.
     *
     * @param string $text the text to replace
     * @param string|null $eol The EOL sequence to use. If not present, uses the
     *                      part's current EOL setting.
     *
     * @return string  the text with the newlines replaced by the desired
     *                 newline sequence
     */
    public function replaceEOL(string $text, ?string $eol = null): string
    {
        if (is_null($eol)) {
            $eol = $this->getEOL();
        }

        return preg_replace("/\r?\n/", $eol, $text);
    }

    /**
     * Return the unique MIME_Part boundary string generated for this object.
     * This may not be the boundary string used when building the message
     * since a user defined 'boundary' Content-Type parameter will override
     * this value.
     *
     * @return string  the unique boundary string
     */
    public function getUniqueID(): ?string
    {
        return $this->_boundary;
    }

    /**
     * Determine the size of a MIME_Part and its child members.
     *
     * @return int  size of the MIME_Part, in bytes
     */
    public function getBytes(): int
    {
        $bytes = 0;

        if (empty($this->_flags['contentsSet']) && $this->_bytes) {
            $bytes = $this->_bytes;
        } elseif ($this->getPrimaryType() === 'multipart') {
            reset($this->_parts);
            foreach ($this->_parts as $part) {
                /* Skip multipart entries (since this may result in double
                   counting). */
                if ($part->getPrimaryType() !== 'multipart') {
                    $bytes += $part->getBytes();
                }
            }
        } else {
            if ($this->getPrimaryType() === 'text') {
                $bytes = HordeString::length($this->_contents, $this->getCharset());
            } else {
                $bytes = strlen($this->_contents);
            }
        }

        return $bytes;
    }

    /**
     * Explicitly set the size (in bytes) of this part. This value will only
     * be returned (via getBytes()) if there are no contents currently set.
     * This public function is useful for setting the size of the part when the
     * contents of the part are not fully loaded (i.e. creating a MIME_Part
     * object from IMAP header information without loading the data of the
     * part).
     *
     * @param int $bytes the size of this part in bytes
     */
    public function setBytes(int $bytes): void
    {
        $this->_bytes = $bytes;
    }

    /**
     * Add to the list of CIDs for this part.
     *
     * @param array $cids A list of MIME IDs of the part.
     *                     Key - MIME ID
     *                     Value - CID for the part
     */
    public function addCID(array $cids = []): void
    {
        $this->_cids += $cids;
    }

    /**
     * Returns the list of CIDs for this part.
     *
     * @return array  the list of CIDs for this part
     */
    public function getCIDList(): array
    {
        asort($this->_cids, SORT_STRING);

        return $this->_cids;
    }

    /**
     * Sets the Content-ID header for this part.
     *
     * @param string|null $cid Use this CID (if not already set).  Else, generate a
     *                     random CID.
     */
    public function setContentID(?string $cid = null): ?string
    {
        if (is_null($this->_contentid)) {
            $this->_contentid = (is_null($cid)) ? (base_convert(microtime(), 10, 36) . '@' . $_SERVER['SERVER_NAME']) : $cid;
        }

        return $this->_contentid;
    }

    /**
     * Returns the Content-ID for this part.
     *
     * @return string|null  the Content-ID for this part
     */
    public function getContentID(): ?string
    {
        return $this->_contentid;
    }

    /**
     * Alter the MIME ID of this part.
     *
     * @param string $mimeid the MIME ID
     */
    public function setMIMEId($mimeid): void
    {
        $this->_mimeid = $mimeid;
    }

    /**
     * Returns the MIME ID of this part.
     */
    public function getMIMEId(): ?string
    {
        return $this->_mimeid;
    }

    /**
     * Returns the relative MIME ID of this part.
     * e.g., if the base part has MIME ID of 2, and you want the first
     * subpart of the base part, the relative MIME ID is 2.1.
     *
     * @param string $id the relative part ID
     *
     * @return string  the relative MIME ID
     */
    public function getRelativeMIMEId($id): string
    {
        $rel = $this->getMIMEId();

        return (empty($rel)) ? $id : $rel . '.' . $id;
    }

    /**
     * Returns a mapping of all MIME IDs to their content-types.
     *
     * @return array  KEY: MIME ID, VALUE: Content type
     */
    public function contentTypeMap(): array
    {
        $map = [$this->getMIMEId() => $this->getType()];
        foreach ($this->_parts as $val) {
            $map += $val->contentTypeMap();
        }

        return $map;
    }

    /**
     * Generate the unique boundary string (if not already done).
     */
    protected function _generateBoundary(): ?string
    {
        if (is_null($this->_boundary)) {
            $this->_boundary = '=_' . base_convert(microtime(), 10, 36);
        }

        return $this->_boundary;
    }
}
