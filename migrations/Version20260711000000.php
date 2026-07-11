<?php

declare(strict_types=1);

namespace DoctrineMigrations\ProjectTemplateBundle;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260711000000 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Create email_templates table (per-owner lifecycle e-mail overrides)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE email_templates ('
            . 'id INT AUTO_INCREMENT NOT NULL, '
            . 'owner_key VARCHAR(191) NOT NULL, '
            . 'template_key VARCHAR(64) NOT NULL, '
            . 'subject VARCHAR(255) DEFAULT NULL, '
            . 'body_html LONGTEXT DEFAULT NULL, '
            . 'enabled TINYINT(1) NOT NULL, '
            . 'created_at DATETIME NOT NULL, '
            . 'updated_at DATETIME DEFAULT NULL, '
            . 'UNIQUE INDEX uniq_email_template_owner_key (owner_key, template_key), '
            . 'PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4',
        );
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE email_templates');
    }
}
