<?php

declare(strict_types=1);

namespace Doctrine\Migrations\Tests\Tools\Console\Command;

use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Migration;
use Doctrine\Migrations\MigrationRepository;
use Doctrine\Migrations\Tools\Console\Command\MigrateCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateCommandTest extends TestCase
{
    /** @var DependencyFactory */
    protected $dependencyFactory;

    /** @var MigrationRepository */
    private $migrationRepository;

    /** @var MigrateCommand */
    private $migrateCommand;

    public function testExecuteCouldNotResolveAlias() : void
    {
        $input  = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);

        $input->expects($this->once())
            ->method('getArgument')
            ->with('version')
            ->willReturn('1234');

        $this->configuration->expects($this->once())
            ->method('setIsDryRun')
            ->with(false);

        $this->configuration->expects($this->once())
            ->method('resolveVersionAlias')
            ->with('1234')
            ->willReturn('');

        self::assertEquals(1, $this->migrateCommand->execute($input, $output));
    }

    public function testExecutedUnavailableMigrationsCancel() : void
    {
        $input  = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);

        $input->expects($this->once())
            ->method('getArgument')
            ->with('version')
            ->willReturn('prev');

        $this->configuration->expects($this->once())
            ->method('setIsDryRun')
            ->with(false);

        $this->configuration->expects($this->once())
            ->method('resolveVersionAlias')
            ->with('prev')
            ->willReturn('1234');

        $this->migrationRepository->expects($this->once())
            ->method('getExecutedUnavailableMigrations')
            ->willReturn(['1235']);

        $this->migrateCommand->expects($this->once())
            ->method('canExecute')
            ->willReturn(false);

        self::assertEquals(1, $this->migrateCommand->execute($input, $output));
    }

    public function testExecuteWriteSql() : void
    {
        $input  = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);

        $migration = $this->createMock(Migration::class);

        $this->dependencyFactory->expects($this->once())
            ->method('getMigration')
            ->willReturn($migration);

        $migration->expects($this->once())
            ->method('writeSqlFile')
            ->with('test', '1234');

        $input->expects($this->once())
            ->method('getArgument')
            ->with('version')
            ->willReturn('prev');

        $input->expects($this->at(1))
            ->method('getOption')
            ->with('write-sql')
            ->willReturn('test');

        $this->configuration->expects($this->once())
            ->method('setIsDryRun')
            ->with(false);

        $this->configuration->expects($this->once())
            ->method('resolveVersionAlias')
            ->with('prev')
            ->willReturn('1234');

        $this->migrationRepository->expects($this->once())
            ->method('getExecutedUnavailableMigrations')
            ->willReturn(['1235']);

        $this->migrateCommand->expects($this->once())
            ->method('canExecute')
            ->willReturn(true);

        self::assertEquals(0, $this->migrateCommand->execute($input, $output));
    }

    public function testExecuteMigrate() : void
    {
        $input  = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);

        $migration = $this->createMock(Migration::class);

        $this->dependencyFactory->expects($this->once())
            ->method('getMigration')
            ->willReturn($migration);

        $input->expects($this->once())
            ->method('getArgument')
            ->with('version')
            ->willReturn('prev');

        $this->configuration->expects($this->once())
            ->method('setIsDryRun')
            ->with(false);

        $this->configuration->expects($this->once())
            ->method('resolveVersionAlias')
            ->with('prev')
            ->willReturn('1234');

        $this->migrationRepository->expects($this->once())
            ->method('getExecutedUnavailableMigrations')
            ->willReturn(['1235']);

        $this->migrateCommand->expects($this->once())
            ->method('canExecute')
            ->willReturn(true);

        $migration->expects($this->once())
            ->method('setNoMigrationException')
            ->with(false);

        $migration->expects($this->once())
            ->method('migrate')
            ->with('1234', false, false);

        self::assertEquals(0, $this->migrateCommand->execute($input, $output));
    }

    protected function setUp() : void
    {
        $this->configuration       = $this->createMock(Configuration::class);
        $this->migrationRepository = $this->createMock(MigrationRepository::class);
        $this->dependencyFactory   = $this->createMock(DependencyFactory::class);

        $this->migrateCommand = $this->getMockBuilder(MigrateCommand::class)
            ->setMethods(['canExecute'])
            ->getMock();

        $this->migrateCommand->setMigrationConfiguration($this->configuration);
        $this->migrateCommand->setMigrationRepository($this->migrationRepository);
        $this->migrateCommand->setDependencyFactory($this->dependencyFactory);
    }
}
