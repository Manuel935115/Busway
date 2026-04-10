<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260410100631 extends AbstractMigration
{
    public function getDescription(): string{return 'Add performance indexes';}
    public function up(Schema $schema): void{$this->addSql('CREATE INDEX idx_estado_vuelo_fecha ON estado_vuelo(fecha_hora DESC)');$this->addSql('CREATE INDEX idx_estado_vuelo_vuelo_fecha ON estado_vuelo(vuelo_id, fecha_hora DESC)');$this->addSql('CREATE INDEX idx_vuelo_numero ON vuelo(numero)');}
    public function down(Schema $schema): void{$this->addSql('DROP INDEX idx_estado_vuelo_fecha ON estado_vuelo');$this->addSql('DROP INDEX idx_estado_vuelo_vuelo_fecha ON estado_vuelo');$this->addSql('DROP INDEX idx_vuelo_numero ON vuelo');}
}
