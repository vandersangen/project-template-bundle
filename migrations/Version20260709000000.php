<?php

declare(strict_types=1);

namespace DoctrineMigrations\ProjectTemplateBundle;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260709000000 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Create shopify_connections table (per-tenant Shopify custom app credentials)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE shopify_connections ('
            . 'id INT AUTO_INCREMENT NOT NULL, '
            . 'tenant_id INT NOT NULL, '
            . 'shop_domain VARCHAR(255) NOT NULL, '
            . 'access_token LONGTEXT NOT NULL, '
            . 'api_key VARCHAR(255) DEFAULT NULL, '
            . 'api_secret LONGTEXT DEFAULT NULL, '
            . 'shop_name VARCHAR(255) DEFAULT NULL, '
            . 'shop_id VARCHAR(64) DEFAULT NULL, '
            . 'status VARCHAR(16) NOT NULL, '
            . 'last_error LONGTEXT DEFAULT NULL, '
            . 'last_verified_at DATETIME DEFAULT NULL, '
            . 'created_at DATETIME NOT NULL, '
            . 'updated_at DATETIME DEFAULT NULL, '
            . 'UNIQUE INDEX uniq_shopify_connection_tenant (tenant_id), '
            . 'PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4',
        );
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE shopify_connections');
    }
}
