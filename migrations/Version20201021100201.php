<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201021100201 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE validation (uid VARCHAR(24) NOT NULL, dataset_name VARCHAR(100) NOT NULL, arguments JSON DEFAULT NULL, date_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status character varying(16) CHECK (status IN (\'waiting_for_args\',\'pending\',\'processing\',\'finished\',\'archived\',\'error\')), message TEXT DEFAULT NULL, date_start TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, date_finish TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, results TEXT DEFAULT NULL, PRIMARY KEY(uid))');
        $this->addSql('CREATE INDEX validation_uid_idx ON validation (uid)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE validation');
    }
}
