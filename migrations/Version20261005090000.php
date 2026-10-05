<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * OIDC authentication : owner of the validations and sessions of the users logged in with the browser.
 */
final class Version20261005090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Owner of the validations and sessions table (OIDC authentication)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE validation ADD COLUMN IF NOT EXISTS owner VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE validation ADD COLUMN IF NOT EXISTS owner_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE INDEX IF NOT EXISTS validation_owner_idx ON validation (owner)');

        // see Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler::createTable()
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS sessions (
                sess_id VARCHAR(128) NOT NULL PRIMARY KEY,
                sess_data BYTEA NOT NULL,
                sess_lifetime INTEGER NOT NULL,
                sess_time INTEGER NOT NULL
            )
            SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS sessions_sess_lifetime_idx ON sessions (sess_lifetime)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS sessions');
        $this->addSql('DROP INDEX IF EXISTS validation_owner_idx');
        $this->addSql('ALTER TABLE validation DROP COLUMN IF EXISTS owner_name');
        $this->addSql('ALTER TABLE validation DROP COLUMN IF EXISTS owner');
    }
}
