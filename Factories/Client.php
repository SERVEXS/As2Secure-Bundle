<?php
/**
 * Created by PhpStorm.
 * User: westin
 * Date: 3/15/2015
 * Time: 7:57 PM
 */

namespace TechData\AS2SecureBundle\Factories;

use TechData\AS2SecureBundle\Factories\Request as RequestFactory;
use TechData\AS2SecureBundle\Models\Client as ClientModel;

class Client
{

    /**
     * @var RequestFactory
     */
    private $requestFactory;

    function __construct(RequestFactory $requestFactory)
    {
        $this->requestFactory = $requestFactory;
    }


    /**
     * @return ClientModel
     */
    public function build()
    {
        $client = new ClientModel($this->requestFactory);
        return $client;
    }

}