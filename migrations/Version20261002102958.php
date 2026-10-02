<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Initial schema of the "validation" table.
 *
 * Idempotent so that it also aligns the databases created before the migrations were used
 * (sql/validator-api.0.1.sql or doctrine:schema:update).
 */
final class Version20261002102958 extends AbstractMigration
{
    private const STATUSES = "'waiting_for_args', 'pending', 'processing', 'finished', 'error', 'archived'";

    public function getDescription(): string
    {
        return 'Initial schema of the validation table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS validation (
                uid VARCHAR(24) NOT NULL,
                dataset_name VARCHAR(100) NOT NULL,
                arguments JSON DEFAULT NULL,
                date_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                status VARCHAR(16) DEFAULT 'waiting_for_args' NOT NULL,
                message TEXT DEFAULT NULL,
                date_start TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                date_finish TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                results JSON DEFAULT NULL,
                document_info JSON DEFAULT NULL,
                delete_data BOOLEAN DEFAULT false NOT NULL,
                PRIMARY KEY (uid)
            )
            SQL);

        // columns added after sql/validator-api.0.1.sql
        $this->addSql('ALTER TABLE validation ADD COLUMN IF NOT EXISTS document_info JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE validation ADD COLUMN IF NOT EXISTS delete_data BOOLEAN DEFAULT false');

        // status : not null with a default value and restricted to Validation::STATUS_*
        $this->addSql("UPDATE validation SET status = 'waiting_for_args' WHERE status IS NULL");
        $this->addSql("ALTER TABLE validation ALTER status SET DEFAULT 'waiting_for_args'");
        $this->addSql('ALTER TABLE validation ALTER status SET NOT NULL');
        $this->addSql('ALTER TABLE validation DROP CONSTRAINT IF EXISTS validation_status_check');
        $this->addSql('ALTER TABLE validation ADD CONSTRAINT validation_status_check CHECK (status IN ('.self::STATUSES.'))');

        // delete_data : not null (was nullable)
        $this->addSql('UPDATE validation SET delete_data = false WHERE delete_data IS NULL');
        $this->addSql('ALTER TABLE validation ALTER delete_data SET DEFAULT false');
        $this->addSql('ALTER TABLE validation ALTER delete_data SET NOT NULL');

        $this->addSql('CREATE INDEX IF NOT EXISTS validation_uid_idx ON validation (uid)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE validation');
    }
}
