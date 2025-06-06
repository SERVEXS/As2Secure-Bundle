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
    /**
     * @var PartnerModel[]
     */
    private array $loadedPartners = [];

    public function __construct(private readonly PartnerProvider $partnerProvider)
    {
    }

    public function getPartner($partnerId, bool $reload = false): PartnerModel
    {
        if ($reload || !array_key_exists(trim((string) $partnerId), $this->loadedPartners)) {
            $partnerData = $this->partnerProvider->getPartner($partnerId);
            $as2partner = $this->makeNewPartner($partnerData);
            $this->loadedPartners[trim((string) $partnerId)] = $as2partner;
        }

        return $this->loadedPartners[trim((string) $partnerId)];
    }

    /**
     * @param array|\stdClass $partnerData
     */
    private function makeNewPartner($partnerData): PartnerModel
    {
        if (!is_array($partnerData)) {
            $partnerData = (array) $partnerData;
        }

        return new PartnerModel($partnerData);
    }
}
