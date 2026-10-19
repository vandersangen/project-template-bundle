<?php

declare(strict_types=1);

namespace DoctrineMigrations\ProjectTemplateBundle;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds feature_flags: the switch per feature flag and audience (docs/feature-flags.md).
 *
 * It runs in every consumer's database; a project without feature flags keeps an empty table. A database that
 * already has the table is left alone.
 */
final class Version20261019120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add feature_flags for switching feature flags on or off per audience';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('feature_flags')) {
            return;
        }

        $this->addSql('CREATE TABLE feature_flags (
            id INT AUTO_INCREMENT NOT NULL,
            feature_flag VARCHAR(50) NOT NULL,
            audience VARCHAR(20) NOT NULL,
            is_on TINYINT(1) NOT NULL,
            updated_at DATETIME NOT NULL,
            updated_by VARCHAR(180) NOT NULL,
            UNIQUE INDEX uniq_feature_flags_flag_audience (feature_flag, audience),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE feature_flags');
    }
}
