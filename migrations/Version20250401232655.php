<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250401232655 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE employee_situation (id SERIAL NOT NULL, employee_id INT NOT NULL, start_date DATE NOT NULL, end_date DATE DEFAULT NULL, nature_changement VARCHAR(255) NOT NULL, grade VARCHAR(255) NOT NULL, affectation VARCHAR(255) NOT NULL, categorie VARCHAR(255) NOT NULL, repere VARCHAR(255) NOT NULL, sit_familiale VARCHAR(255) NOT NULL, enf INT NOT NULL, enf_charge INT NOT NULL, taux_horaire DOUBLE PRECISION DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_E9BDF2888C03F15C ON employee_situation (employee_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE employee_situation ADD CONSTRAINT FK_E9BDF2888C03F15C FOREIGN KEY (employee_id) REFERENCES employee (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE employee ADD adresse VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE employee DROP sit_familiale
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE employee_situation DROP CONSTRAINT FK_E9BDF2888C03F15C
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE employee_situation
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE employee ADD sit_familiale VARCHAR(255) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE employee DROP adresse
        SQL);
    }
}
