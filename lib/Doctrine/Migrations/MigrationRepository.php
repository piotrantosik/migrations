<?php

declare(strict_types=1);

namespace Doctrine\Migrations;

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\Exception\DuplicateMigrationVersion;
use Doctrine\Migrations\Exception\MigrationClassNotFound;
use Doctrine\Migrations\Exception\UnknownMigrationVersion;
use Doctrine\Migrations\Finder\MigrationFinder;
use const SORT_STRING;
use function array_keys;
use function array_map;
use function array_search;
use function array_unshift;
use function class_exists;
use function count;
use function end;
use function get_class;
use function implode;
use function ksort;
use function sprintf;
use function substr;

/**
 * @internal
 */
class MigrationRepository
{
    /** @var Configuration */
    private $configuration;

    /** @var Connection */
    private $connection;

    /** @var MigrationFinder */
    private $migrationFinder;

    /** @var VersionFactory */
    private $versionFactory;

    /** @var Version[] */
    private $migrations = [];

    public function __construct(
        Configuration $configuration,
        Connection $connection,
        MigrationFinder $migrationFinder,
        VersionFactory $versionFactory
    ) {
        $this->configuration   = $configuration;
        $this->connection      = $connection;
        $this->migrationFinder = $migrationFinder;
        $this->versionFactory  = $versionFactory;
    }

    /**
     * @return string[]
     */
    public function findMigrations(string $path) : array
    {
        return $this->migrationFinder->findMigrations($path, $this->configuration->getMigrationsNamespace());
    }

    /** @return Version[] */
    public function registerMigrationsFromDirectory(string $path) : array
    {
        return $this->registerMigrations($this->findMigrations($path));
    }

    /** @throws MigrationException */
    public function registerMigration(string $version, string $migrationClassName) : Version
    {
        $this->ensureMigrationClassExists($migrationClassName);

        if (isset($this->migrations[$version])) {
            throw DuplicateMigrationVersion::new(
                $version,
                get_class($this->migrations[$version])
            );
        }

        $version = $this->versionFactory->createVersion($version, $migrationClassName);

        $this->migrations[$version->getVersion()] = $version;

        ksort($this->migrations, SORT_STRING);

        return $version;
    }

    /**
     * @param string[] $migrations
     *
     * @return Version[]
     */
    public function registerMigrations(array $migrations) : array
    {
        $versions = [];

        foreach ($migrations as $version => $class) {
            $versions[] = $this->registerMigration((string) $version, $class);
        }

        return $versions;
    }

    public function getCurrentVersion() : string
    {
        $this->configuration->createMigrationTable();

        if (! $this->configuration->isMigrationTableCreated() && $this->configuration->isDryRun()) {
            return '0';
        }

        $this->configuration->connect();

        $this->loadMigrationsFromDirectory();

        $where = null;

        if (! empty($this->migrations)) {
            $migratedVersions = [];

            foreach ($this->migrations as $migration) {
                $migratedVersions[] = sprintf("'%s'", $migration->getVersion());
            }

            $where = sprintf(
                ' WHERE %s IN (%s)',
                $this->configuration->getQuotedMigrationsColumnName(),
                implode(', ', $migratedVersions)
            );
        }

        $sql = sprintf(
            'SELECT %s FROM %s%s ORDER BY %s DESC',
            $this->configuration->getQuotedMigrationsColumnName(),
            $this->configuration->getMigrationsTableName(),
            $where,
            $this->configuration->getQuotedMigrationsColumnName()
        );

        $sql    = $this->connection->getDatabasePlatform()->modifyLimitQuery($sql, 1);
        $result = $this->connection->fetchColumn($sql);

        return $result !== false ? (string) $result : '0';
    }

    public function getVersion(string $version) : Version
    {
        $this->loadMigrationsFromDirectory();

        if (! isset($this->migrations[$version])) {
            throw UnknownMigrationVersion::new($version);
        }

        return $this->migrations[$version];
    }

    public function hasVersion(string $version) : bool
    {
        $this->loadMigrationsFromDirectory();

        return isset($this->migrations[$version]);
    }

    public function hasVersionMigrated(Version $version) : bool
    {
        $this->configuration->connect();
        $this->configuration->createMigrationTable();

        $sql = sprintf(
            'SELECT %s FROM %s WHERE %s = ?',
            $this->configuration->getQuotedMigrationsColumnName(),
            $this->configuration->getMigrationsTableName(),
            $this->configuration->getQuotedMigrationsColumnName()
        );

        $version = $this->connection->fetchColumn($sql, [$version->getVersion()]);

        return $version !== false;
    }

    /**
     * @return Version[]
     */
    public function getMigrations() : array
    {
        $this->loadMigrationsFromDirectory();

        return $this->migrations;
    }

    /** @return string[] */
    public function getAvailableVersions() : array
    {
        $availableVersions = [];

        $this->loadMigrationsFromDirectory();

        foreach ($this->migrations as $migration) {
            $availableVersions[] = $migration->getVersion();
        }

        return $availableVersions;
    }

    /** @return string[] */
    public function getMigratedVersions() : array
    {
        $this->configuration->createMigrationTable();

        if (! $this->configuration->isMigrationTableCreated() && $this->configuration->isDryRun()) {
            return [];
        }

        $this->configuration->connect();

        $sql = sprintf(
            'SELECT %s FROM %s',
            $this->configuration->getQuotedMigrationsColumnName(),
            $this->configuration->getMigrationsTableName()
        );

        $result = $this->connection->fetchAll($sql);

        return array_map('current', $result);
    }

    public function getNumberOfAvailableMigrations() : int
    {
        $this->loadMigrationsFromDirectory();

        return count($this->migrations);
    }

    public function getLatestVersion() : string
    {
        $this->loadMigrationsFromDirectory();

        $versions = array_keys($this->migrations);
        $latest   = end($versions);

        return $latest !== false ? (string) $latest : '0';
    }

    public function getNumberOfExecutedMigrations() : int
    {
        $this->configuration->connect();
        $this->configuration->createMigrationTable();

        $sql = sprintf(
            'SELECT COUNT(%s) FROM %s',
            $this->configuration->getQuotedMigrationsColumnName(),
            $this->configuration->getMigrationsTableName()
        );

        $result = $this->connection->fetchColumn($sql);

        return $result !== false ? (int) $result : 0;
    }

    public function getRelativeVersion(string $version, int $delta) : ?string
    {
        $this->loadMigrationsFromDirectory();

        $versions = array_map('strval', array_keys($this->migrations));

        array_unshift($versions, '0');

        $offset = array_search($version, $versions, true);

        if ($offset === false || ! isset($versions[$offset + $delta])) {
            // Unknown version or delta out of bounds.
            return null;
        }

        return $versions[$offset + $delta];
    }

    public function getDeltaVersion(string $delta) : ?string
    {
        $symbol = substr($delta, 0, 1);
        $number = (int) substr($delta, 1);

        if ($number <= 0) {
            return null;
        }

        if ($symbol === '+' || $symbol === '-') {
            return $this->getRelativeVersion($this->getCurrentVersion(), (int) $delta);
        }

        return null;
    }

    public function getPrevVersion() : ?string
    {
        return $this->getRelativeVersion($this->getCurrentVersion(), -1);
    }

    public function getNextVersion() : ?string
    {
        return $this->getRelativeVersion($this->getCurrentVersion(), 1);
    }

    private function loadMigrationsFromDirectory() : void
    {
        $migrationsDirectory = $this->configuration->getMigrationsDirectory();

        if (count($this->migrations) !== 0 || $migrationsDirectory === null) {
            return;
        }

        $this->registerMigrationsFromDirectory($migrationsDirectory);
    }

    /** @throws MigrationException */
    private function ensureMigrationClassExists(string $class) : void
    {
        if (! class_exists($class)) {
            throw MigrationClassNotFound::new(
                $class,
                $this->configuration->getMigrationsNamespace()
            );
        }
    }
}
