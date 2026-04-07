<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260407070145 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE estado_vuelo ADD CONSTRAINT FK_BCF365874FF34720 FOREIGN KEY (vuelo_id) REFERENCES vuelo (id)');
        $this->addSql('ALTER TABLE estado_vuelo ADD CONSTRAINT FK_BCF365879F5A440B FOREIGN KEY (estado_id) REFERENCES estado (id)');
        $this->addSql('ALTER TABLE vuelo ADD hora_salida_programada DATETIME DEFAULT NULL, ADD hora_llegada_programada DATETIME DEFAULT NULL, ADD hora_salida_real DATETIME DEFAULT NULL, ADD hora_llegada_real DATETIME DEFAULT NULL, DROP hora_salida, DROP hora_llegada');
        $this->addSql('ALTER TABLE vuelo ADD CONSTRAINT FK_B608E37522552C49 FOREIGN KEY (estado_actual_id) REFERENCES estado (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE estado_vuelo DROP FOREIGN KEY FK_BCF365874FF34720');
        $this->addSql('ALTER TABLE estado_vuelo DROP FOREIGN KEY FK_BCF365879F5A440B');
        $this->addSql('ALTER TABLE vuelo DROP FOREIGN KEY FK_B608E37522552C49');
        $this->addSql('ALTER TABLE vuelo ADD hora_salida DATETIME DEFAULT NULL, ADD hora_llegada DATETIME DEFAULT NULL, DROP hora_salida_programada, DROP hora_llegada_programada, DROP hora_salida_real, DROP hora_llegada_real');
    }
}
