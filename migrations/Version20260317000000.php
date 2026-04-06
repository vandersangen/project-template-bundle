<?php

declare(strict_types=1);

namespace DoctrineMigrations\ProjectTemplateBundle;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260317000000 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Rename payment_subscriptions.interval to subscription_interval (interval is a reserved word in MySQL 8)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment_subscriptions CHANGE `interval` subscription_interval VARCHAR(20) NOT NULL');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment_subscriptions CHANGE subscription_interval `interval` VARCHAR(20) NOT NULL');
    }
}
