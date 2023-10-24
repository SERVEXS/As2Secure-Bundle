<?php

namespace TechData\AS2SecureBundle\Factories;

use TechData\AS2SecureBundle\Interfaces\PartnerProvider;
use TechData\AS2SecureBundle\Models\Partner as PartnerModel;

/**
 * Description of Partner
 *
 * @author wpigott
 */
class Partner
{
    private PartnerProvider $partnerProvider;

    private array $loadedPartners = [];

    public function __construct(PartnerProvider $partnerProvider)
    {
        $this->partnerProvider = $partnerProvider;
    }

    /**
     * @return PartnerModel
     */
    public function getPartner($partnerId, bool $reload = false)
    {
        if ($reload || !array_key_exists(trim($partnerId), $this->loadedPartners)) {
            $partnerData = $this->partnerProvider->getPartner($partnerId);
            $as2partner = $this->makeNewPartner($partnerData);
            $this->loadedPartners[trim($partnerId)] = $as2partner;
        }

        return $this->loadedPartners[trim($partnerId)];
    }

    /**
     * @param array|\stdClass $partnerData
     */
    private function makeNewPartner($partnerData): PartnerModel
    {
        if (!is_array($partnerData)) {
            $partnerData = (array) $partnerData;
        }

        return new PartnerModel((array) $partnerData);
    }
}
