<?php
/**
 * Created by PhpStorm.
 * User: westin
 * Date: 3/15/2015
 * Time: 7:57 PM
 */

namespace TechData\AS2SecureBundle\Factories;

use TechData\AS2SecureBundle\Models\Client as ClientModel;

class Client
{
    public function __construct(private readonly Request $requestFactory)
    {
    }

    public function build(): ClientModel
    {
        return new ClientModel($this->requestFactory);
    }
}
