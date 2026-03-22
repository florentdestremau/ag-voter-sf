<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260322143626 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE choice (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, text VARCHAR(255) NOT NULL, allow_free_text BOOLEAN NOT NULL, order_index INTEGER NOT NULL, question_id INTEGER NOT NULL, CONSTRAINT FK_C1AB5A921E27F6BF FOREIGN KEY (question_id) REFERENCES question (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_C1AB5A921E27F6BF ON choice (question_id)');
        $this->addSql('CREATE TABLE participant (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(100) NOT NULL, token VARCHAR(64) NOT NULL, joined_at DATETIME NOT NULL, session_id INTEGER NOT NULL, CONSTRAINT FK_D79F6B11613FECDF FOREIGN KEY (session_id) REFERENCES voting_session (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D79F6B115F37A13B ON participant (token)');
        $this->addSql('CREATE INDEX IDX_D79F6B11613FECDF ON participant (session_id)');
        $this->addSql('CREATE TABLE question (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, text VARCHAR(500) NOT NULL, status VARCHAR(20) NOT NULL, order_index INTEGER NOT NULL, session_id INTEGER NOT NULL, CONSTRAINT FK_B6F7494E613FECDF FOREIGN KEY (session_id) REFERENCES voting_session (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_B6F7494E613FECDF ON question (session_id)');
        $this->addSql('CREATE TABLE vote (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, free_text VARCHAR(500) DEFAULT NULL, voted_at DATETIME NOT NULL, participant_id INTEGER NOT NULL, question_id INTEGER NOT NULL, choice_id INTEGER NOT NULL, CONSTRAINT FK_5A1085649D1C3019 FOREIGN KEY (participant_id) REFERENCES participant (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_5A1085641E27F6BF FOREIGN KEY (question_id) REFERENCES question (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_5A108564998666D1 FOREIGN KEY (choice_id) REFERENCES choice (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_5A1085649D1C3019 ON vote (participant_id)');
        $this->addSql('CREATE INDEX IDX_5A1085641E27F6BF ON vote (question_id)');
        $this->addSql('CREATE INDEX IDX_5A108564998666D1 ON vote (choice_id)');
        $this->addSql('CREATE UNIQUE INDEX unique_participant_question_vote ON vote (participant_id, question_id)');
        $this->addSql('CREATE TABLE voting_session (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, token VARCHAR(64) NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D042AF075F37A13B ON voting_session (token)');
        $this->addSql('CREATE TABLE messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE choice');
        $this->addSql('DROP TABLE participant');
        $this->addSql('DROP TABLE question');
        $this->addSql('DROP TABLE vote');
        $this->addSql('DROP TABLE voting_session');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
