<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260731145532 extends AbstractMigration {
    #[\Override]
    public function getDescription(): string {
        return 'Add hitobitoProvider, hitobitoEventId and hitobitoLastSyncTime to camp';
    }

    public function up(Schema $schema): void {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE camp ADD hitobitoProvider VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE camp ADD hitobitoEventId VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE camp ADD hitobitoLastSyncTime TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX hitobitoprovider_hitobitoeventid_unique ON camp (hitobitoProvider, hitobitoEventId)');
    }

    #[\Override]
    public function down(Schema $schema): void {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX hitobitoprovider_hitobitoeventid_unique');
        $this->addSql('ALTER TABLE camp DROP hitobitoProvider');
        $this->addSql('ALTER TABLE camp DROP hitobitoEventId');
        $this->addSql('ALTER TABLE camp DROP hitobitoLastSyncTime');
    }
}
