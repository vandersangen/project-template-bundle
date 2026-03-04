<?php

declare(strict_types=1);

namespace DoctrineMigrations\ProjectTemplateBundle;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260303184213 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE crons CHANGE enabled enabled TINYINT NOT NULL');
        $this->addSql('ALTER TABLE super_admin_users RENAME INDEX uniq_super_admin_username TO UNIQ_1EB5D2E0F85E0677');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE crons CHANGE enabled enabled TINYINT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE super_admin_users RENAME INDEX uniq_1eb5d2e0f85e0677 TO UNIQ_super_admin_username');
    }
}
