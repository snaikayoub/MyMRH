<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251228212408 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE voyage_deplacement (id INT AUTO_INCREMENT NOT NULL, employee_id INT NOT NULL, periode_paie_id INT NOT NULL, type_voyage VARCHAR(255) DEFAULT NULL, motif LONGTEXT DEFAULT NULL, mode_transport VARCHAR(255) NOT NULL, date_heure_depart DATETIME NOT NULL, date_heure_retour DATETIME NOT NULL, distance_km DOUBLE PRECISION NOT NULL, status VARCHAR(255) NOT NULL, commentaire LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_218262608C03F15C (employee_id), INDEX IDX_21826260196A46D7 (periode_paie_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE voyage_deplacement ADD CONSTRAINT FK_218262608C03F15C FOREIGN KEY (employee_id) REFERENCES employee (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE voyage_deplacement ADD CONSTRAINT FK_21826260196A46D7 FOREIGN KEY (periode_paie_id) REFERENCES periode_paie (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE voyage_deplacement DROP FOREIGN KEY FK_218262608C03F15C
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE voyage_deplacement DROP FOREIGN KEY FK_21826260196A46D7
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE voyage_deplacement
        SQL);
    }
}
