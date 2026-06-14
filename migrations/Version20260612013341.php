<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260612013341 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE appointment (id UUID NOT NULL, scheduled_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(20) NOT NULL, notes TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, doctor_id UUID NOT NULL, patient_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_FE38F84487F4FB17 ON appointment (doctor_id)');
        $this->addSql('CREATE INDEX IDX_FE38F8446B899279 ON appointment (patient_id)');
        $this->addSql('CREATE TABLE doctor (id UUID NOT NULL, name VARCHAR(255) NOT NULL, crm VARCHAR(20) NOT NULL, max_appointments_per_day INT NOT NULL, is_active BOOLEAN NOT NULL, specialty_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1FC0F36A9BDAAAFA ON doctor (crm)');
        $this->addSql('CREATE INDEX IDX_1FC0F36A9A353316 ON doctor (specialty_id)');
        $this->addSql('CREATE TABLE patient (id UUID NOT NULL, name VARCHAR(255) NOT NULL, cpf VARCHAR(11) NOT NULL, birth_date DATE NOT NULL, phone VARCHAR(20) DEFAULT NULL, is_active BOOLEAN NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1ADAD7EB3E3E11F0 ON patient (cpf)');
        $this->addSql('CREATE TABLE specialty (id UUID NOT NULL, name VARCHAR(255) NOT NULL, description VARCHAR(255) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE appointment ADD CONSTRAINT FK_FE38F84487F4FB17 FOREIGN KEY (doctor_id) REFERENCES doctor (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE appointment ADD CONSTRAINT FK_FE38F8446B899279 FOREIGN KEY (patient_id) REFERENCES patient (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE doctor ADD CONSTRAINT FK_1FC0F36A9A353316 FOREIGN KEY (specialty_id) REFERENCES specialty (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE appointment DROP CONSTRAINT FK_FE38F84487F4FB17');
        $this->addSql('ALTER TABLE appointment DROP CONSTRAINT FK_FE38F8446B899279');
        $this->addSql('ALTER TABLE doctor DROP CONSTRAINT FK_1FC0F36A9A353316');
        $this->addSql('DROP TABLE appointment');
        $this->addSql('DROP TABLE doctor');
        $this->addSql('DROP TABLE patient');
        $this->addSql('DROP TABLE specialty');
    }
}
