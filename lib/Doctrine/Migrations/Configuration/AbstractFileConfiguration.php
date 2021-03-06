<?php

declare(strict_types=1);

namespace Doctrine\Migrations\Configuration;

use Doctrine\Migrations\Configuration\Exception\FileAlreadyLoaded;
use Doctrine\Migrations\Configuration\Exception\InvalidConfigurationKey;
use Doctrine\Migrations\Configuration\Exception\UnknownConfigurationValue;
use Doctrine\Migrations\Exception\MigrationException;
use InvalidArgumentException;
use function dirname;
use function file_exists;
use function getcwd;
use function in_array;
use function realpath;
use function strcasecmp;

abstract class AbstractFileConfiguration extends Configuration
{
    /** @var array */
    private const ALLOWED_CONFIGURATION_KEYS = [
        'migrations_namespace',
        'table_name',
        'column_name',
        'organize_migrations',
        'name',
        'migrations_directory',
        'migrations',
        'custom_template',
    ];

    /** @var string */
    private $file;

    /** @var bool */
    private $loaded = false;

    /**
     * @param mixed[] $config
     */
    protected function setConfiguration(array $config) : void
    {
        foreach ($config as $configurationKey => $configurationValue) {
            if (! in_array($configurationKey, self::ALLOWED_CONFIGURATION_KEYS, true)) {
                throw InvalidConfigurationKey::new($configurationKey);
            }
        }

        if (isset($config['migrations_namespace'])) {
            $this->setMigrationsNamespace($config['migrations_namespace']);
        }

        if (isset($config['table_name'])) {
            $this->setMigrationsTableName($config['table_name']);
        }

        if (isset($config['column_name'])) {
            $this->setMigrationsColumnName($config['column_name']);
        }

        if (isset($config['organize_migrations'])) {
            $this->setMigrationOrganization($config['organize_migrations']);
        }

        if (isset($config['name'])) {
            $this->setName($config['name']);
        }

        if (isset($config['migrations_directory'])) {
            $this->loadMigrationsFromDirectory($config['migrations_directory']);
        }

        if (isset($config['migrations'])) {
            $this->loadMigrations($config['migrations']);
        }

        if (! isset($config['custom_template'])) {
            return;
        }

        $this->setCustomTemplate($config['custom_template']);
    }

    private function loadMigrationsFromDirectory(string $migrationsDirectory) : void
    {
        $this->setMigrationsDirectory($migrationsDirectory);
        $this->registerMigrationsFromDirectory($migrationsDirectory);
    }

    /** @param string[][] $migrations */
    private function loadMigrations(array $migrations) : void
    {
        foreach ($migrations as $migration) {
            $this->registerMigration(
                $migration['version'],
                $migration['class']
            );
        }
    }

    private function setMigrationOrganization(string $migrationOrganization) : void
    {
        if (strcasecmp($migrationOrganization, static::VERSIONS_ORGANIZATION_BY_YEAR) === 0) {
            $this->setMigrationsAreOrganizedByYear();
        } elseif (strcasecmp($migrationOrganization, static::VERSIONS_ORGANIZATION_BY_YEAR_AND_MONTH) === 0) {
            $this->setMigrationsAreOrganizedByYearAndMonth();
        } else {
            throw UnknownConfigurationValue::new('organize_migrations', $migrationOrganization);
        }
    }

    /** @throws MigrationException */
    public function load(string $file) : void
    {
        if ($this->loaded) {
            throw FileAlreadyLoaded::new();
        }

        $path = getcwd() . '/' . $file;

        if (file_exists($path)) {
            $file = $path;
        }

        $this->file = $file;

        if (! file_exists($file)) {
            throw new InvalidArgumentException('Given config file does not exist');
        }

        $this->doLoad($file);
        $this->loaded = true;
    }

    protected function getDirectoryRelativeToFile(string $file, string $input) : string
    {
        $path = realpath(dirname($file) . '/' . $input);

        return ($path !== false) ? $path : $input;
    }

    public function getFile() : string
    {
        return $this->file;
    }

    /**
     * Abstract method that each file configuration driver must implement to
     * load the given configuration file whether it be xml, yaml, etc. or something
     * else.
     */
    abstract protected function doLoad(string $file) : void;
}
