<?php

declare(strict_types=1);

namespace Doctrine\Migrations;

use DateTime;
use DateTimeInterface;
use function file_put_contents;
use function is_dir;
use function sprintf;

final class FileQueryWriter implements QueryWriter
{
    /** @var null|OutputWriter */
    private $outputWriter;

    /** @var MigrationFileBuilder */
    private $migrationFileBuilder;

    public function __construct(
        OutputWriter $outputWriter,
        MigrationFileBuilder $migrationFileBuilder
    ) {
        $this->outputWriter         = $outputWriter;
        $this->migrationFileBuilder = $migrationFileBuilder;
    }

    /**
     * @param mixed[] $queriesByVersion
     */
    public function write(
        string $path,
        string $direction,
        array $queriesByVersion,
        ?DateTimeInterface $now = null
    ) : bool {
        $now = $now ?? new DateTime();

        $string = $this->migrationFileBuilder
            ->buildMigrationFile($queriesByVersion, $direction, $now);

        $path = $this->buildMigrationFilePath($path, $now);

        if ($this->outputWriter !== null) {
            $this->outputWriter->write(
                "\n" . sprintf('Writing migration file to "<info>%s</info>"', $path)
            );
        }

        return file_put_contents($path, $string) !== false;
    }

    private function buildMigrationFilePath(string $path, DateTimeInterface $now) : string
    {
        if (is_dir($path)) {
            $path = realpath($path);
            $path = $path . '/doctrine_migration_' . $now->format('YmdHis') . '.sql';
        }

        return $path;
    }
}
