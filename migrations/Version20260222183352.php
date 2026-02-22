<?php

declare(strict_types=1);

namespace DoctrineMigrations\ProjectTemplateBundle;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260222183352 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE mails (id INT AUTO_INCREMENT NOT NULL, sender VARCHAR(255) NOT NULL, receiver JSON NOT NULL, cc JSON DEFAULT NULL, bcc JSON DEFAULT NULL, title VARCHAR(255) NOT NULL, body LONGTEXT NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, sent_at DATETIME DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE queue_job_logs (id INT AUTO_INCREMENT NOT NULL, message_class VARCHAR(255) NOT NULL, message_data JSON NOT NULL, stdout LONGTEXT DEFAULT NULL, stderr LONGTEXT DEFAULT NULL, status VARCHAR(20) NOT NULL, started_at DATETIME NOT NULL, completed_at DATETIME DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE mails');
        $this->addSql('DROP TABLE queue_job_logs');
    }
}
