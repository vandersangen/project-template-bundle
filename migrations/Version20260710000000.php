<?php

declare(strict_types=1);

namespace DoctrineMigrations\ProjectTemplateBundle;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Reconciles databases that executed the original versions of
 * Version20260314000000 / Version20260601120000 before those migrations were
 * edited in place (June 9): the address-line columns were replaced by
 * street/house-number columns and invoices moved from pdf_path to
 * pdf_content, but already-migrated databases never received those renames.
 * Every step checks the live schema first, so the migration is a no-op on
 * databases that are already up to date.
 */
final class Version20260710000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Reconcile tenants/invoice_templates/invoices schemas for databases that ran'
            . ' the pre-edit versions of the payment and invoicing migrations';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('tenants')) {
            $table = $schema->getTable('tenants');
            if (!$table->hasColumn('billing_street')) {
                if ($table->hasColumn('billing_address_line1')) {
                    $this->addSql(
                        'ALTER TABLE tenants CHANGE billing_address_line1 billing_street VARCHAR(255) DEFAULT NULL',
                    );
                } else {
                    $this->addSql('ALTER TABLE tenants ADD billing_street VARCHAR(255) DEFAULT NULL');
                }
            }
            if (!$table->hasColumn('billing_house_number')) {
                $this->addSql('ALTER TABLE tenants ADD billing_house_number VARCHAR(20) DEFAULT NULL');
            }
            if ($table->hasColumn('billing_address_line2')) {
                $this->addSql('ALTER TABLE tenants DROP COLUMN billing_address_line2');
            }
        }

        if ($schema->hasTable('invoice_templates')) {
            $table = $schema->getTable('invoice_templates');
            if (!$table->hasColumn('street')) {
                if ($table->hasColumn('address_line1')) {
                    $this->addSql(
                        'ALTER TABLE invoice_templates CHANGE address_line1 street VARCHAR(255) DEFAULT NULL',
                    );
                } else {
                    $this->addSql('ALTER TABLE invoice_templates ADD street VARCHAR(255) DEFAULT NULL');
                }
            }
            if (!$table->hasColumn('house_number')) {
                $this->addSql('ALTER TABLE invoice_templates ADD house_number VARCHAR(20) DEFAULT NULL');
            }
            if ($table->hasColumn('address_line2')) {
                $this->addSql('ALTER TABLE invoice_templates DROP COLUMN address_line2');
            }
        }

        if ($schema->hasTable('invoices')) {
            $table = $schema->getTable('invoices');
            if (!$table->hasColumn('pdf_content')) {
                $this->addSql('ALTER TABLE invoices ADD pdf_content LONGBLOB DEFAULT NULL');
            }
            if ($table->hasColumn('pdf_path')) {
                $this->addSql('ALTER TABLE invoices DROP COLUMN pdf_path');
            }
        }
    }

    public function down(Schema $schema): void
    {
        // Irreversible by design: the up() migration only reconciles schema
        // drift and cannot know which pre-edit state a database came from.
    }
}
