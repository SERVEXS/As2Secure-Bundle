<?php
/**
 * Created by PhpStorm.
 * User: westin
 * Date: 3/15/2015
 * Time: 11:30 AM
 */

namespace TechData\AS2SecureBundle\Factories;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use TechData\AS2SecureBundle\Factories\AbstractFactory;
use TechData\AS2SecureBundle\Factories\MDN as MDNFactory;
use TechData\AS2SecureBundle\Factories\Message as MessageFactory;
use TechData\AS2SecureBundle\Models\Request as RequestModel;

class Request extends AbstractFactory
{
    protected $request = null;

    /**
     * @var MDNFactory
     */
    private $mdnFactory;

    /**
     * @var MessageFactory
     */
    private $messageFactory;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    function __construct(MDNFactory $mdnFactory, MessageFactory $messageFactory, EventDispatcherInterface $eventDispatcher)
    {
        $this->mdnFactory = $mdnFactory;
        $this->messageFactory = $messageFactory;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param $content
     * @param $headers
     * @return RequestModel
     */
    public function build($content, $headers)
    {
        $request = new RequestModel($this->mdnFactory, $this->messageFactory, $this->eventDispatcher);
        $request->setPartnerFactory($this->getPartnerFactory());
        $request->setAdapterFactory($this->getAdapterFactory());
        $request->initialize($content, $headers);
        return $request;

    }
}