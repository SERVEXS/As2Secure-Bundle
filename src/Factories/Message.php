<?php
/**
 * Created by PhpStorm.
 * User: westin
 * Date: 3/15/2015
 * Time: 11:30 AM
 */

namespace TechData\AS2SecureBundle\Factories;

use TechData\AS2SecureBundle\Factories\MDN as MDNFactory;
use TechData\AS2SecureBundle\Models\Message as MessageModel;

class Message extends AbstractFactory
{
    private MDNFactory $mdnFactory;

    public function __construct(MDNFactory $mdnFactory)
    {
        $this->mdnFactory = $mdnFactory;
    }

    /**
     * @param null $data
     */
    public function build($data = null, array $params = []): MessageModel
    {
        $message = new MessageModel($this->mdnFactory);
        $message->setPartnerFactory($this->getPartnerFactory());
        $message->setAdapterFactory($this->getAdapterFactory());
        $message->initialize($data, $params);

        return $message;
    }
}
