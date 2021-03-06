<?php

declare(strict_types=1);

namespace Doctrine\Migrations\Tools\Console\Helper;

use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\OutputWriter;
use Symfony\Component\Console\Input\InputInterface;

interface ConfigurationHelperInterface
{
    public function getMigrationConfig(
        InputInterface $input,
        OutputWriter $outputWriter
    ) : Configuration;
}
