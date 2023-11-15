<?php
/**
 * Created by PhpStorm.
 * User: westin
 * Date: 3/15/2015
 * Time: 5:39 PM
 */

namespace TechData\AS2SecureBundle\Factories;

use TechData\AS2SecureBundle\Factories\Partner as PartnerFactory;
use TechData\AS2SecureBundle\Models\Adapter as AdapterModel;
use TechData\AS2SecureBundle\Models\AS2Exception;

class Adapter
{
    public function __construct(private readonly PartnerFactory $partnerFactory, private $AS2_DIR_BIN)
    {
    }

    /**
     * @throws AS2Exception
     */
    public function build($partner_from, $partner_to): AdapterModel
    {
        $adapter = new AdapterModel($this->partnerFactory, $this->AS2_DIR_BIN);
        $adapter->initialize($partner_from, $partner_to);

        return $adapter;
    }
}
