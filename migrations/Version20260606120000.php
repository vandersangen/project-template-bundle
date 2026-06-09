<?php

declare(strict_types=1);

namespace DoctrineMigrations\ProjectTemplateBundle;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260606120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store invoice PDFs as a database blob (pdf_content) instead of a file path, '
            . 'and replace the address-line model with separate street/house-number fields '
            . 'on invoice_templates and tenants.';
    }

    public function up(Schema $schema): void
    {
        // Invoices: file path -> binary blob.
        $this->addSql('ALTER TABLE invoices DROP COLUMN pdf_path');
        $this->addSql('ALTER TABLE invoices ADD pdf_content LONGBLOB DEFAULT NULL');

        // Invoice templates: address lines -> street + house number.
        $this->addSql('ALTER TABLE invoice_templates DROP COLUMN address_line1');
        $this->addSql('ALTER TABLE invoice_templates DROP COLUMN address_line2');
        $this->addSql('ALTER TABLE invoice_templates ADD street VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE invoice_templates ADD house_number VARCHAR(20) DEFAULT NULL');

        // Tenants: billing address lines -> billing street + house number.
        $this->addSql('ALTER TABLE tenants DROP COLUMN billing_address_line1');
        $this->addSql('ALTER TABLE tenants DROP COLUMN billing_address_line2');
        $this->addSql('ALTER TABLE tenants ADD billing_street VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE tenants ADD billing_house_number VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenants DROP COLUMN billing_street');
        $this->addSql('ALTER TABLE tenants DROP COLUMN billing_house_number');
        $this->addSql('ALTER TABLE tenants ADD billing_address_line1 VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE tenants ADD billing_address_line2 VARCHAR(255) DEFAULT NULL');

        $this->addSql('ALTER TABLE invoice_templates DROP COLUMN street');
        $this->addSql('ALTER TABLE invoice_templates DROP COLUMN house_number');
        $this->addSql('ALTER TABLE invoice_templates ADD address_line1 VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE invoice_templates ADD address_line2 VARCHAR(255) DEFAULT NULL');

        $this->addSql('ALTER TABLE invoices DROP COLUMN pdf_content');
        $this->addSql('ALTER TABLE invoices ADD pdf_path VARCHAR(1024) DEFAULT NULL');
    }
}
