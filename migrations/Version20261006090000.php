<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * PostGIS extension in the "public" schema, required by validator-cli.jar ("geometry" type, search_path
 * "validation<uid>, public") and /health/db.
 *
 * Never blocks the start of the API (bin/application.sh) :
 * - already installed : nothing is done (no privilege required, ex : managed databases)
 * - not available on the server (ex : CI with postgres:15) : warning
 * - creation not allowed for the database user : warning, the extension must be created by an administrator
 *   ("CREATE EXTENSION postgis SCHEMA public", or from the console of the managed database).
 */
final class Version20261006090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'PostGIS extension';
    }

    /**
     * Not transactional : a failure of CREATE EXTENSION must not abort the migrations.
     */
    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $installed = $this->connection->fetchAssociative(
            "SELECT e.extversion AS version, n.nspname AS schema FROM pg_extension e JOIN pg_namespace n ON n.oid = e.extnamespace WHERE e.extname = 'postgis'"
        );
        if (false !== $installed) {
            $this->write(sprintf('PostGIS %s already installed (schema %s)', $installed['version'], $installed['schema']));
            $this->warnIf('public' !== $installed['schema'], sprintf('PostGIS is installed in the schema "%s" : validator-cli.jar requires it in "public"', $installed['schema']));

            return;
        }

        $available = $this->connection->fetchOne("SELECT default_version FROM pg_available_extensions WHERE name = 'postgis'");
        if (false === $available) {
            $this->warnIf(true, 'PostGIS is not available on the database server : validator-cli.jar and /health/db require it');

            return;
        }

        try {
            $this->connection->executeStatement('CREATE EXTENSION IF NOT EXISTS postgis SCHEMA public');
            $this->write(sprintf('PostGIS %s created in the schema public', $available));
        } catch (\Throwable $e) {
            $this->warnIf(true, sprintf(
                'PostGIS could not be created (%s) : it must be created by an administrator of the database ("CREATE EXTENSION postgis SCHEMA public"), validator-cli.jar and /health/db require it',
                $e->getMessage()
            ));
        }
    }

    public function down(Schema $schema): void
    {
        // the extension may be used by other applications or by the validations : not removed
    }
}
