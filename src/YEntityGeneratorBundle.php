<?php

namespace Uay\YEntityGeneratorBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Uay\YEntityGeneratorBundle\DependencyInjection\UayEntitiesExtension;

class YEntityGeneratorBundle extends Bundle
{
    public function getContainerExtension()
    {
        return new UayEntitiesExtension();
    }
}
