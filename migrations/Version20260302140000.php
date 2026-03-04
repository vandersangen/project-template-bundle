<?php

declare(strict_types=1);

namespace DoctrineMigrations\ProjectTemplateBundle;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260302140000 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Create super_admin_users table for SuperAdmin module';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE super_admin_users (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_super_admin_username (username), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE super_admin_users');
    }
}
