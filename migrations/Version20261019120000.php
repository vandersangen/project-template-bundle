<?php

declare(strict_types=1);

namespace DoctrineMigrations\ProjectTemplateBundle;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds the tables for feature flags (docs/feature-flags.md).
 *
 * feature_flags holds the flags themselves (cid, name, description) and is filled only from master data. The link
 * table feature_flag_audiences holds whether a flag is on for an audience and is written only from superbeheer,
 * through FeatureFlagService::switchFlag(). Both run in every consumer's database; a project without feature flags
 * keeps two empty tables. A database that already has a table of either name must rename or drop it first.
 */
final class Version20261019120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add feature_flags and feature_flag_audiences for switching feature flags on or off per audience';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE feature_flags (
            id INT AUTO_INCREMENT NOT NULL,
            cid VARCHAR(50) NOT NULL,
            name VARCHAR(100) NOT NULL,
            description TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE INDEX uniq_feature_flags_cid (cid),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        $this->addSql('CREATE TABLE feature_flag_audiences (
            id INT AUTO_INCREMENT NOT NULL,
            feature_flag_id INT NOT NULL,
            audience VARCHAR(20) NOT NULL,
            is_on TINYINT(1) NOT NULL,
            updated_at DATETIME NOT NULL,
            updated_by VARCHAR(180) NOT NULL,
            UNIQUE INDEX uniq_feature_flag_audiences_flag_audience (feature_flag_id, audience),
            PRIMARY KEY (id),
            CONSTRAINT fk_feature_flag_audiences_feature_flag FOREIGN KEY (feature_flag_id)
                REFERENCES feature_flags (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE feature_flag_audiences');
        $this->addSql('DROP TABLE feature_flags');
    }
}
