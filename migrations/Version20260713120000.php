<?php

declare(strict_types=1);

namespace DoctrineMigrations\ProjectTemplateBundle;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds optional TOTP two-factor authentication columns to the users table.
 *
 * 2FA is opt-in: totp_enabled defaults to false, and the secret/backup-code
 * columns stay null until a user completes enrollment. Secrets are stored
 * encrypted at rest (see TotpService); backup codes are stored as hashes.
 *
 * Every step checks the live schema first, so the migration is a no-op on
 * databases that already have the columns.
 */
final class Version20260713120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional TOTP two-factor authentication columns to users';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('users')) {
            return;
        }

        $table = $schema->getTable('users');

        if (!$table->hasColumn('totp_enabled')) {
            $this->addSql('ALTER TABLE users ADD totp_enabled TINYINT(1) NOT NULL DEFAULT 0');
        }
        if (!$table->hasColumn('totp_secret')) {
            $this->addSql('ALTER TABLE users ADD totp_secret VARCHAR(255) DEFAULT NULL');
        }
        if (!$table->hasColumn('totp_pending_secret')) {
            $this->addSql('ALTER TABLE users ADD totp_pending_secret VARCHAR(255) DEFAULT NULL');
        }
        if (!$table->hasColumn('totp_backup_codes')) {
            $this->addSql('ALTER TABLE users ADD totp_backup_codes JSON DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('users')) {
            return;
        }

        $table = $schema->getTable('users');

        if ($table->hasColumn('totp_backup_codes')) {
            $this->addSql('ALTER TABLE users DROP COLUMN totp_backup_codes');
        }
        if ($table->hasColumn('totp_pending_secret')) {
            $this->addSql('ALTER TABLE users DROP COLUMN totp_pending_secret');
        }
        if ($table->hasColumn('totp_secret')) {
            $this->addSql('ALTER TABLE users DROP COLUMN totp_secret');
        }
        if ($table->hasColumn('totp_enabled')) {
            $this->addSql('ALTER TABLE users DROP COLUMN totp_enabled');
        }
    }
}
