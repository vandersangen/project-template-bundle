<?php

declare(strict_types=1);

namespace DoctrineMigrations\ProjectTemplateBundle;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260302120000 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Create crons table for Cron module';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE crons (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, command VARCHAR(255) NOT NULL, command_arguments JSON DEFAULT NULL, schedule VARCHAR(100) NOT NULL, enabled TINYINT(1) NOT NULL DEFAULT 1, last_run_at DATETIME DEFAULT NULL, next_run_at DATETIME DEFAULT NULL, timezone VARCHAR(63) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE crons');
    }
}
