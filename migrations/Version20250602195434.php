<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250602195434 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE category (id INT AUTO_INCREMENT NOT NULL, category_name VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE category_tm (id INT AUTO_INCREMENT NOT NULL, category_id INT NOT NULL, grp_perf_id INT NOT NULL, tm DOUBLE PRECISION NOT NULL, INDEX IDX_94F6495A12469DE2 (category_id), INDEX IDX_94F6495A2C7F0CDD (grp_perf_id), UNIQUE INDEX UNIQ_94F6495A12469DE22C7F0CDD (category_id, grp_perf_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE conge (id INT AUTO_INCREMENT NOT NULL, employee_id INT NOT NULL, traite_par_id INT DEFAULT NULL, type_conge VARCHAR(50) NOT NULL, date_debut DATE NOT NULL, date_fin DATE NOT NULL, nombre_jours NUMERIC(5, 2) NOT NULL, statut VARCHAR(20) DEFAULT 'en_attente' NOT NULL, motif LONGTEXT DEFAULT NULL, commentaire_gestionnaire LONGTEXT DEFAULT NULL, date_creation DATETIME NOT NULL, date_traitement DATETIME DEFAULT NULL, demi_journee TINYINT(1) DEFAULT 0 NOT NULL, periode_demi_journee VARCHAR(10) DEFAULT NULL, INDEX IDX_2ED893488C03F15C (employee_id), INDEX IDX_2ED89348167FABE8 (traite_par_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE division (id INT AUTO_INCREMENT NOT NULL, validateur_division_id INT NOT NULL, nom VARCHAR(255) NOT NULL, INDEX IDX_1017471436B74497 (validateur_division_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE employee (id INT AUTO_INCREMENT NOT NULL, grp_perf_id INT DEFAULT NULL, matricule VARCHAR(255) NOT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) NOT NULL, date_naissance DATE NOT NULL, lieu_naissance VARCHAR(255) DEFAULT NULL, code_sexe VARCHAR(255) NOT NULL, cin VARCHAR(255) NOT NULL, date_embauche DATE NOT NULL, adresse VARCHAR(255) DEFAULT NULL, INDEX IDX_5D9F75A12C7F0CDD (grp_perf_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE employee_situation (id INT AUTO_INCREMENT NOT NULL, employee_id INT NOT NULL, service_id INT NOT NULL, category_id INT NOT NULL, start_date DATE NOT NULL, end_date DATE DEFAULT NULL, nature_changement VARCHAR(255) NOT NULL, grade VARCHAR(255) NOT NULL, sit_familiale VARCHAR(255) NOT NULL, enf INT NOT NULL, enf_charge INT NOT NULL, taux_horaire DOUBLE PRECISION DEFAULT NULL, type_paie VARCHAR(255) NOT NULL, INDEX IDX_E9BDF2888C03F15C (employee_id), INDEX IDX_E9BDF288ED5CA9E6 (service_id), INDEX IDX_E9BDF28812469DE2 (category_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE grp_perf (id INT AUTO_INCREMENT NOT NULL, name_grp VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE periode_paie (id INT AUTO_INCREMENT NOT NULL, type_paie VARCHAR(255) NOT NULL, mois INT NOT NULL, annee INT NOT NULL, quinzaine INT DEFAULT NULL, statut VARCHAR(255) NOT NULL, score_equipe NUMERIC(5, 2) DEFAULT NULL, score_collectif NUMERIC(5, 2) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE prime_performance (id INT AUTO_INCREMENT NOT NULL, employee_id INT NOT NULL, periode_paie_id INT NOT NULL, taux_monetaire DOUBLE PRECISION NOT NULL, score_equipe DOUBLE PRECISION NOT NULL, score_collectif DOUBLE PRECISION NOT NULL, montant_perf DOUBLE PRECISION DEFAULT NULL, jours_perf DOUBLE PRECISION NOT NULL, note_hierarchique DOUBLE PRECISION NOT NULL, status VARCHAR(255) NOT NULL, calculated_at DATETIME DEFAULT NULL, INDEX IDX_4E958F178C03F15C (employee_id), INDEX IDX_4E958F17196A46D7 (periode_paie_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE services (id INT AUTO_INCREMENT NOT NULL, validateur_service_id INT DEFAULT NULL, division_id INT NOT NULL, nom VARCHAR(255) NOT NULL, INDEX IDX_7332E169D8BA21E7 (validateur_service_id), INDEX IDX_7332E16941859289 (division_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE service_gestionnaire (service_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_2BEA6EC5ED5CA9E6 (service_id), INDEX IDX_2BEA6EC5A76ED395 (user_id), PRIMARY KEY(service_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, full_name VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', available_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', delivered_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE category_tm ADD CONSTRAINT FK_94F6495A12469DE2 FOREIGN KEY (category_id) REFERENCES category (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE category_tm ADD CONSTRAINT FK_94F6495A2C7F0CDD FOREIGN KEY (grp_perf_id) REFERENCES grp_perf (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE conge ADD CONSTRAINT FK_2ED893488C03F15C FOREIGN KEY (employee_id) REFERENCES employee (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE conge ADD CONSTRAINT FK_2ED89348167FABE8 FOREIGN KEY (traite_par_id) REFERENCES employee (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE division ADD CONSTRAINT FK_1017471436B74497 FOREIGN KEY (validateur_division_id) REFERENCES `user` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE employee ADD CONSTRAINT FK_5D9F75A12C7F0CDD FOREIGN KEY (grp_perf_id) REFERENCES grp_perf (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE employee_situation ADD CONSTRAINT FK_E9BDF2888C03F15C FOREIGN KEY (employee_id) REFERENCES employee (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE employee_situation ADD CONSTRAINT FK_E9BDF288ED5CA9E6 FOREIGN KEY (service_id) REFERENCES services (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE employee_situation ADD CONSTRAINT FK_E9BDF28812469DE2 FOREIGN KEY (category_id) REFERENCES category (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE prime_performance ADD CONSTRAINT FK_4E958F178C03F15C FOREIGN KEY (employee_id) REFERENCES employee (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE prime_performance ADD CONSTRAINT FK_4E958F17196A46D7 FOREIGN KEY (periode_paie_id) REFERENCES periode_paie (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE services ADD CONSTRAINT FK_7332E169D8BA21E7 FOREIGN KEY (validateur_service_id) REFERENCES `user` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE services ADD CONSTRAINT FK_7332E16941859289 FOREIGN KEY (division_id) REFERENCES division (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE service_gestionnaire ADD CONSTRAINT FK_2BEA6EC5ED5CA9E6 FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE service_gestionnaire ADD CONSTRAINT FK_2BEA6EC5A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE category_tm DROP FOREIGN KEY FK_94F6495A12469DE2
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE category_tm DROP FOREIGN KEY FK_94F6495A2C7F0CDD
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE conge DROP FOREIGN KEY FK_2ED893488C03F15C
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE conge DROP FOREIGN KEY FK_2ED89348167FABE8
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE division DROP FOREIGN KEY FK_1017471436B74497
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE employee DROP FOREIGN KEY FK_5D9F75A12C7F0CDD
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE employee_situation DROP FOREIGN KEY FK_E9BDF2888C03F15C
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE employee_situation DROP FOREIGN KEY FK_E9BDF288ED5CA9E6
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE employee_situation DROP FOREIGN KEY FK_E9BDF28812469DE2
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE prime_performance DROP FOREIGN KEY FK_4E958F178C03F15C
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE prime_performance DROP FOREIGN KEY FK_4E958F17196A46D7
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE services DROP FOREIGN KEY FK_7332E169D8BA21E7
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE services DROP FOREIGN KEY FK_7332E16941859289
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE service_gestionnaire DROP FOREIGN KEY FK_2BEA6EC5ED5CA9E6
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE service_gestionnaire DROP FOREIGN KEY FK_2BEA6EC5A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE category
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE category_tm
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE conge
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE division
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE employee
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE employee_situation
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE grp_perf
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE periode_paie
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE prime_performance
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE services
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE service_gestionnaire
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE `user`
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE messenger_messages
        SQL);
    }
}
