<?php
/**
 * Created by PhpStorm.
 * User: westin
 * Date: 3/15/2015
 * Time: 11:30 AM
 */

namespace TechData\AS2SecureBundle\Factories;

use TechData\AS2SecureBundle\Models\AS2Exception;
use TechData\AS2SecureBundle\Models\Horde\MIME\Horde_MIME_Message;
use TechData\AS2SecureBundle\Models\Horde\MIME\Horde_MIME_Part;
use TechData\AS2SecureBundle\Models\MDN as MDNModel;

class MDN extends AbstractFactory
{
    public function __construct()
    {
    }

    /**
     * @param null $data
     *
     * @throws AS2Exception
     */
    public function build($data = null, array $params = []): MDNModel
    {
        $originalMessageId = '';
        if ($data instanceof Horde_MIME_Message) {
            /* @var $part Horde_MIME_Part */
            foreach ($data->getParts() as $part) {
                if ($part->getType() === 'message/disposition-notification') {
                    $headers = (new \Mail_mimeDecode($data->getParts()[2]->getContents()))->decode()->headers;
                    $originalMessageId = $headers['original-message-id'];
                }
            }
        }

        $mdn = new MDNModel();
        $mdn->setPartnerFactory($this->getPartnerFactory());
        $mdn->setAdapterFactory($this->getAdapterFactory());
        $mdn->initialize($data, $params);

        $mdn->setAttribute('original-message-id', $originalMessageId);

        return $mdn;
    }
}
