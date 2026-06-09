<?php

declare(strict_types=1);

namespace DoctrineMigrations\ProjectTemplateBundle;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260601120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create invoicing tables (invoices, invoice_items, invoice_templates, invoice_number_sequences)'
            . ' and add attachments column to mails';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE invoices ('
            . 'id INT AUTO_INCREMENT NOT NULL, '
            . 'number VARCHAR(64) NOT NULL, '
            . 'owner_key VARCHAR(191) NOT NULL, '
            . 'source_type VARCHAR(32) DEFAULT NULL, '
            . 'source_id VARCHAR(191) DEFAULT NULL, '
            . 'issuer JSON NOT NULL, '
            . 'customer JSON NOT NULL, '
            . 'currency VARCHAR(3) NOT NULL, '
            . 'net_cents INT NOT NULL, '
            . 'vat_cents INT NOT NULL, '
            . 'gross_cents INT NOT NULL, '
            . 'vat_rate INT NOT NULL, '
            . 'vat_mode VARCHAR(16) NOT NULL, '
            . 'status VARCHAR(16) NOT NULL, '
            . 'footer_text LONGTEXT DEFAULT NULL, '
            . 'accent_color VARCHAR(16) DEFAULT NULL, '
            . 'pdf_content LONGBLOB DEFAULT NULL, '
            . 'issued_at DATETIME NOT NULL, '
            . 'created_at DATETIME NOT NULL, '
            . 'UNIQUE INDEX uniq_invoice_source (owner_key, source_type, source_id), '
            . 'UNIQUE INDEX uniq_invoice_number (owner_key, number), '
            . 'INDEX idx_invoices_owner_key (owner_key), '
            . 'PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4',
        );

        $this->addSql(
            'CREATE TABLE invoice_items ('
            . 'id INT AUTO_INCREMENT NOT NULL, '
            . 'invoice_id INT NOT NULL, '
            . 'description VARCHAR(512) NOT NULL, '
            . 'quantity INT NOT NULL, '
            . 'unit_price_cents INT NOT NULL, '
            . 'net_cents INT NOT NULL, '
            . 'vat_cents INT NOT NULL, '
            . 'gross_cents INT NOT NULL, '
            . 'vat_rate INT NOT NULL, '
            . 'INDEX idx_invoice_items_invoice (invoice_id), '
            . 'PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4',
        );
        $this->addSql(
            'ALTER TABLE invoice_items ADD CONSTRAINT fk_invoice_items_invoice '
            . 'FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE',
        );

        $this->addSql(
            'CREATE TABLE invoice_templates ('
            . 'id INT AUTO_INCREMENT NOT NULL, '
            . 'owner_key VARCHAR(191) NOT NULL, '
            . 'enabled TINYINT(1) NOT NULL, '
            . 'logo_path VARCHAR(1024) DEFAULT NULL, '
            . 'company_name VARCHAR(255) DEFAULT NULL, '
            . 'street VARCHAR(255) DEFAULT NULL, '
            . 'house_number VARCHAR(20) DEFAULT NULL, '
            . 'postal_code VARCHAR(20) DEFAULT NULL, '
            . 'city VARCHAR(100) DEFAULT NULL, '
            . 'country VARCHAR(100) DEFAULT NULL, '
            . 'vat_number VARCHAR(50) DEFAULT NULL, '
            . 'coc_number VARCHAR(50) DEFAULT NULL, '
            . 'iban VARCHAR(50) DEFAULT NULL, '
            . 'email VARCHAR(255) DEFAULT NULL, '
            . 'footer_text LONGTEXT DEFAULT NULL, '
            . 'accent_color VARCHAR(16) DEFAULT NULL, '
            . 'number_prefix VARCHAR(16) DEFAULT NULL, '
            . 'vat_rate INT NOT NULL, '
            . 'vat_mode VARCHAR(16) NOT NULL, '
            . 'created_at DATETIME NOT NULL, '
            . 'updated_at DATETIME DEFAULT NULL, '
            . 'UNIQUE INDEX uniq_invoice_template_owner (owner_key), '
            . 'PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4',
        );

        $this->addSql(
            'CREATE TABLE invoice_number_sequences ('
            . 'id INT AUTO_INCREMENT NOT NULL, '
            . 'owner_key VARCHAR(191) NOT NULL, '
            . 'year INT NOT NULL, '
            . 'counter INT NOT NULL, '
            . 'UNIQUE INDEX uniq_invoice_sequence_owner_year (owner_key, year), '
            . 'PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4',
        );

        $this->addSql('ALTER TABLE mails ADD attachments JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mails DROP COLUMN attachments');
        $this->addSql('ALTER TABLE invoice_items DROP FOREIGN KEY fk_invoice_items_invoice');
        $this->addSql('DROP TABLE invoice_number_sequences');
        $this->addSql('DROP TABLE invoice_templates');
        $this->addSql('DROP TABLE invoice_items');
        $this->addSql('DROP TABLE invoices');
    }
}
