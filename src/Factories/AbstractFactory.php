<?php
/**
 * Created by PhpStorm.
 * User: westin
 * Date: 3/15/2015
 * Time: 11:35 AM
 */

namespace TechData\AS2SecureBundle\Factories;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use TechData\AS2SecureBundle\Factories\Adapter as AdapterFactory;
use TechData\AS2SecureBundle\Factories\Partner as PartnerFactory;

abstract class AbstractFactory
{
    private EventDispatcherInterface $eventDispatcher;

    private Partner $partnerFactory;

    private Adapter $adapterFactory;

    protected function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->eventDispatcher;
    }

    public function setEventDispatcher(EventDispatcherInterface $EventDispatcher): void
    {
        $this->eventDispatcher = $EventDispatcher;
    }

    public function getPartnerFactory(): PartnerFactory
    {
        return $this->partnerFactory;
    }

    public function setPartnerFactory(PartnerFactory $partnerFactory): void
    {
        $this->partnerFactory = $partnerFactory;
    }

    public function getAdapterFactory(): Adapter
    {
        return $this->adapterFactory;
    }

    public function setAdapterFactory(AdapterFactory $adapterFactory): void
    {
        $this->adapterFactory = $adapterFactory;
    }
}
