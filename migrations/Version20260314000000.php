<?php

declare(strict_types=1);

namespace DoctrineMigrations\ProjectTemplateBundle;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260314000000 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Add tenants, payment_subscriptions and payments tables for the payment module';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS tenants (
                id INT AUTO_INCREMENT NOT NULL,
                name VARCHAR(255) NOT NULL,
                company_name VARCHAR(255) DEFAULT NULL,
                vat_number VARCHAR(50) DEFAULT NULL,
                billing_email VARCHAR(255) DEFAULT NULL,
                billing_street VARCHAR(255) DEFAULT NULL,
                billing_house_number VARCHAR(20) DEFAULT NULL,
                billing_city VARCHAR(100) DEFAULT NULL,
                billing_postal_code VARCHAR(20) DEFAULT NULL,
                billing_country VARCHAR(2) DEFAULT NULL,
                owner_user_id INT NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME DEFAULT NULL,
                INDEX IDX_TENANTS_OWNER (owner_user_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS payment_subscriptions (
                id INT AUTO_INCREMENT NOT NULL,
                tenant_id INT NOT NULL,
                user_id INT NOT NULL,
                tool_user_reference VARCHAR(255) NOT NULL,
                payment_api_subscription_id INT DEFAULT NULL,
                provider VARCHAR(20) NOT NULL,
                status VARCHAR(30) NOT NULL,
                amount_cents INT NOT NULL,
                currency VARCHAR(3) NOT NULL,
                `interval` VARCHAR(20) NOT NULL,
                description VARCHAR(255) DEFAULT NULL,
                checkout_url LONGTEXT DEFAULT NULL,
                provider_subscription_id VARCHAR(255) DEFAULT NULL,
                provider_customer_id VARCHAR(255) DEFAULT NULL,
                next_billing_date DATETIME DEFAULT NULL,
                failed_charge_count INT NOT NULL DEFAULT 0,
                max_charges INT DEFAULT NULL,
                charge_count INT NOT NULL DEFAULT 0,
                ends_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME DEFAULT NULL,
                INDEX IDX_PAYMENT_SUBSCRIPTIONS_TENANT (tenant_id),
                INDEX IDX_PAYMENT_SUBSCRIPTIONS_USER (user_id),
                INDEX IDX_PAYMENT_SUBSCRIPTIONS_STATUS (status),
                UNIQUE INDEX UNIQ_PAYMENT_API_SUB_ID (payment_api_subscription_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS payments (
                id INT AUTO_INCREMENT NOT NULL,
                tenant_id INT NOT NULL,
                user_id INT NOT NULL,
                subscription_id INT DEFAULT NULL,
                payment_api_payment_id INT DEFAULT NULL,
                provider_payment_id VARCHAR(255) DEFAULT NULL,
                provider VARCHAR(20) NOT NULL,
                status VARCHAR(20) NOT NULL,
                amount_cents INT NOT NULL,
                currency VARCHAR(3) NOT NULL,
                description VARCHAR(255) DEFAULT NULL,
                checkout_url LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME DEFAULT NULL,
                INDEX IDX_PAYMENTS_TENANT (tenant_id),
                INDEX IDX_PAYMENTS_USER (user_id),
                INDEX IDX_PAYMENTS_SUBSCRIPTION (subscription_id),
                INDEX IDX_PAYMENTS_STATUS (status),
                UNIQUE INDEX UNIQ_PAYMENT_API_PAY_ID (payment_api_payment_id),
                CONSTRAINT FK_PAYMENTS_SUBSCRIPTION FOREIGN KEY (subscription_id)
                    REFERENCES payment_subscriptions (id) ON DELETE SET NULL,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
        SQL);
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS payments');
        $this->addSql('DROP TABLE IF EXISTS payment_subscriptions');
        $this->addSql('DROP TABLE IF EXISTS tenants');
    }
}
