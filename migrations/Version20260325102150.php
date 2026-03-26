<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260325102150 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE estado (id INT AUTO_INCREMENT NOT NULL, nombre VARCHAR(100) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE estado_vuelo (id INT AUTO_INCREMENT NOT NULL, fecha_hora DATETIME NOT NULL, hora_salida DATETIME DEFAULT NULL, hora_llegada DATETIME DEFAULT NULL, raw_data JSON DEFAULT NULL, vuelo_id INT NOT NULL, estado_id INT NOT NULL, INDEX IDX_BCF365874FF34720 (vuelo_id), INDEX IDX_BCF365879F5A440B (estado_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE vuelo (id INT AUTO_INCREMENT NOT NULL, numero VARCHAR(50) NOT NULL, hora_salida DATETIME DEFAULT NULL, hora_llegada DATETIME DEFAULT NULL, estado_actual_id INT DEFAULT NULL, INDEX IDX_B608E37522552C49 (estado_actual_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE estado_vuelo ADD CONSTRAINT FK_BCF365874FF34720 FOREIGN KEY (vuelo_id) REFERENCES vuelo (id)');
        $this->addSql('ALTER TABLE estado_vuelo ADD CONSTRAINT FK_BCF365879F5A440B FOREIGN KEY (estado_id) REFERENCES estado (id)');
        $this->addSql('ALTER TABLE vuelo ADD CONSTRAINT FK_B608E37522552C49 FOREIGN KEY (estado_actual_id) REFERENCES estado (id)');
        $this->addSql('DROP TABLE flight_record');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE flight_record (id INT AUTO_INCREMENT NOT NULL, flight_number VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, origin_iata VARCHAR(10) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, destination_iata VARCHAR(10) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, scheduled_out DATETIME DEFAULT NULL, status VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, raw_data JSON DEFAULT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = MyISAM COMMENT = \'\' ');
        $this->addSql('ALTER TABLE estado_vuelo DROP FOREIGN KEY FK_BCF365874FF34720');
        $this->addSql('ALTER TABLE estado_vuelo DROP FOREIGN KEY FK_BCF365879F5A440B');
        $this->addSql('ALTER TABLE vuelo DROP FOREIGN KEY FK_B608E37522552C49');
        $this->addSql('DROP TABLE estado');
        $this->addSql('DROP TABLE estado_vuelo');
        $this->addSql('DROP TABLE vuelo');
    }
}
