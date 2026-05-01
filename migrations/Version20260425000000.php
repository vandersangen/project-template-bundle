<?php

declare(strict_types=1);

namespace DoctrineMigrations\ProjectTemplateBundle;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260425000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add pending_plan_change_data column to payment_subscriptions for plan switching';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment_subscriptions ADD pending_plan_change_data JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment_subscriptions DROP COLUMN pending_plan_change_data');
    }
}
