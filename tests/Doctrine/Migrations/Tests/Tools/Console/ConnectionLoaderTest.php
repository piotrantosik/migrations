<?php

declare(strict_types=1);

namespace Doctrine\Migrations\Tests\Tools\Console;

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\Configuration\Connection\ConnectionLoaderInterface;
use Doctrine\Migrations\Configuration\Connection\Loader\ConnectionConfigurationChainLoader;
use Doctrine\Migrations\Tools\Console\ConnectionLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputInterface;

class ConnectionLoaderTest extends TestCase
{
    /** @var Configuration */
    private $configuration;

    /** @var ConnectionConfigurationChainLoader */
    private $connectionConfigurationChainLoader;

    /** @var ConnectionLoader */
    private $connectionLoader;

    public function testGetConnection() : void
    {
        $input     = $this->createMock(InputInterface::class);
        $helperSet = $this->createMock(HelperSet::class);

        $connection = $this->createMock(Connection::class);

        $this->connectionConfigurationChainLoader->expects($this->once())
            ->method('chosen')
            ->wilLReturn($connection);

        self::assertSame($connection, $this->connectionLoader->getConnection($input, $helperSet));
    }

    protected function setUp() : void
    {
        $this->configuration = $this->createMock(Configuration::class);

        $this->connectionLoader = $this->getMockBuilder(ConnectionLoader::class)
            ->setConstructorArgs([$this->configuration])
            ->setMethods(['createConnectionConfigurationChainLoader'])
            ->getMock();

        $this->connectionConfigurationChainLoader = $this->createMock(
            ConnectionLoaderInterface::class
        );

        $this->connectionLoader->expects($this->once())
            ->method('createConnectionConfigurationChainLoader')
            ->willReturn($this->connectionConfigurationChainLoader);
    }
}
