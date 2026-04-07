<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260407105411 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE estado_tren (id INT AUTO_INCREMENT NOT NULL, fecha_hora DATETIME NOT NULL, retraso INT DEFAULT NULL, origen VARCHAR(255) DEFAULT NULL, destino VARCHAR(255) DEFAULT NULL, raw_data JSON DEFAULT NULL, tren_id INT NOT NULL, UNIQUE INDEX UNIQ_DBC452DAD08AE3A3 (tren_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE tren (id INT AUTO_INCREMENT NOT NULL, codigo_comercial VARCHAR(50) NOT NULL, tipo VARCHAR(100) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE estado_tren ADD CONSTRAINT FK_DBC452DAD08AE3A3 FOREIGN KEY (tren_id) REFERENCES tren (id)');
        $this->addSql('ALTER TABLE estado_vuelo ADD CONSTRAINT FK_BCF365874FF34720 FOREIGN KEY (vuelo_id) REFERENCES vuelo (id)');
        $this->addSql('ALTER TABLE estado_vuelo ADD CONSTRAINT FK_BCF365879F5A440B FOREIGN KEY (estado_id) REFERENCES estado (id)');
        $this->addSql('ALTER TABLE vuelo ADD CONSTRAINT FK_B608E37522552C49 FOREIGN KEY (estado_actual_id) REFERENCES estado (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE estado_tren DROP FOREIGN KEY FK_DBC452DAD08AE3A3');
        $this->addSql('DROP TABLE estado_tren');
        $this->addSql('DROP TABLE tren');
        $this->addSql('ALTER TABLE estado_vuelo DROP FOREIGN KEY FK_BCF365874FF34720');
        $this->addSql('ALTER TABLE estado_vuelo DROP FOREIGN KEY FK_BCF365879F5A440B');
        $this->addSql('ALTER TABLE vuelo DROP FOREIGN KEY FK_B608E37522552C49');
    }
}
