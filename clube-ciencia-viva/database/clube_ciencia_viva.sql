-- ============================================================================
-- FICHEIRO: database/clube_ciencia_viva.sql
-- PROJETO:  Clube de Ciência Viva
-- PARA QUE SERVE: cria a base de dados "clube_ciencia_viva" com as 17 tabelas
--                 e as 22 chaves estrangeiras (FK) usadas pela aplicação.
-- COMO USAR: WampServer ligado -> http://localhost/phpmyadmin -> separador
--            "Importar" (escolher este ficheiro) -> Executar.
--            Também podes colar o conteúdo no separador "SQL".
--
-- AVISO IMPORTANTE:
--   Este ficheiro NÃO apaga dados. Se a base de dados já tiver tabelas, a
--   importação falha em vez de as substituir. Para uma instalação limpa,
--   cria uma base de dados nova (ou apaga a antiga no phpMyAdmin antes de
--   importar, depois de teres a certeza de que não precisas dos dados).
--
-- DEPOIS DE IMPORTAR:
--   Abre o site e vai a instalar.php para criar o primeiro administrador.
--   O SQL não contém passwords: essa página só funciona enquanto a tabela
--   `utilizador` estiver vazia.
-- ============================================================================

CREATE DATABASE IF NOT EXISTS clube_ciencia_viva
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE clube_ciencia_viva;

SET NAMES utf8mb4;


-- ----------------------------- GESTÃO DO CLUBE ------------------------------

CREATE TABLE utilizador (
  id_utilizador  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome           VARCHAR(100) NOT NULL,
  email          VARCHAR(150) NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,
  perfil         ENUM('administrador','professor','aluno') NOT NULL DEFAULT 'aluno',
  ativo          TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id_utilizador),
  UNIQUE KEY uq_utilizador_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE projeto (
  id_projeto      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  titulo          VARCHAR(150) NOT NULL,
  descricao       TEXT NULL,
  data_inicio     DATE NOT NULL,
  data_fim        DATE NULL,
  estado          ENUM('planeado','em_curso','concluido','cancelado') NOT NULL DEFAULT 'planeado',
  id_responsavel  INT UNSIGNED NOT NULL,
  PRIMARY KEY (id_projeto),
  KEY fk_projeto_responsavel (id_responsavel),
  CONSTRAINT fk_projeto_responsavel FOREIGN KEY (id_responsavel)
    REFERENCES utilizador (id_utilizador) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE atividade (
  id_atividade    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  titulo          VARCHAR(150) NOT NULL,
  descricao       TEXT NULL,
  data_hora       DATETIME NOT NULL,
  local           VARCHAR(120) NULL,
  estado          ENUM('agendada','realizada','cancelada') NOT NULL DEFAULT 'agendada',
  id_responsavel  INT UNSIGNED NOT NULL,
  PRIMARY KEY (id_atividade),
  KEY fk_atividade_responsavel (id_responsavel),
  CONSTRAINT fk_atividade_responsavel FOREIGN KEY (id_responsavel)
    REFERENCES utilizador (id_utilizador) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reserva (
  id_reserva     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  data_pedido    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  data_inicio    DATETIME NOT NULL,
  data_fim       DATETIME NOT NULL,
  estado         ENUM('pendente','aprovada','recusada','cancelada','concluida') NOT NULL DEFAULT 'pendente',
  id_utilizador  INT UNSIGNED NOT NULL,
  PRIMARY KEY (id_reserva),
  KEY ix_reserva_periodo (data_inicio, data_fim),
  KEY fk_reserva_utilizador (id_utilizador),
  CONSTRAINT fk_reserva_utilizador FOREIGN KEY (id_utilizador)
    REFERENCES utilizador (id_utilizador) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pergunta_ia (
  id_pergunta    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pergunta       TEXT NOT NULL,
  resposta       TEXT NULL,
  data_hora      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  categoria      VARCHAR(60) NULL,
  id_utilizador  INT UNSIGNED NULL,
  PRIMARY KEY (id_pergunta),
  KEY fk_pergunta_ia_utilizador (id_utilizador),
  CONSTRAINT fk_pergunta_ia_utilizador FOREIGN KEY (id_utilizador)
    REFERENCES utilizador (id_utilizador) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tentativas_login (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email      VARCHAR(150) NOT NULL,
  ip         VARCHAR(45) NOT NULL,
  sucesso    TINYINT(1) NOT NULL DEFAULT 0,
  criado_em  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_tentativas_email (email, criado_em),
  KEY ix_tentativas_ip (ip, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -------------------------- EQUIPAMENTOS E RESERVAS -------------------------

CREATE TABLE categoria_equipamento (
  id_categoria  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome          VARCHAR(80) NOT NULL,
  descricao     VARCHAR(255) NULL,
  PRIMARY KEY (id_categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE equipamento (
  id_equipamento    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome              VARCHAR(120) NOT NULL,
  descricao         TEXT NULL,
  quantidade_total  INT UNSIGNED NOT NULL DEFAULT 1,
  estado            ENUM('disponivel','em_manutencao','avariado','abatido') NOT NULL DEFAULT 'disponivel',
  localizacao       VARCHAR(120) NULL,
  id_categoria      INT UNSIGNED NOT NULL,
  PRIMARY KEY (id_equipamento),
  KEY fk_equipamento_categoria (id_categoria),
  CONSTRAINT fk_equipamento_categoria FOREIGN KEY (id_categoria)
    REFERENCES categoria_equipamento (id_categoria) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE projeto_equipamento (
  id_projeto      INT UNSIGNED NOT NULL,
  id_equipamento  INT UNSIGNED NOT NULL,
  quantidade      INT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id_projeto, id_equipamento),
  KEY fk_projeto_equipamento_equipamento (id_equipamento),
  CONSTRAINT fk_projeto_equipamento_projeto FOREIGN KEY (id_projeto)
    REFERENCES projeto (id_projeto) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_projeto_equipamento_equipamento FOREIGN KEY (id_equipamento)
    REFERENCES equipamento (id_equipamento) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE atividade_equipamento (
  id_atividade    INT UNSIGNED NOT NULL,
  id_equipamento  INT UNSIGNED NOT NULL,
  quantidade      INT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id_atividade, id_equipamento),
  KEY fk_atividade_equipamento_equipamento (id_equipamento),
  CONSTRAINT fk_atividade_equipamento_atividade FOREIGN KEY (id_atividade)
    REFERENCES atividade (id_atividade) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_atividade_equipamento_equipamento FOREIGN KEY (id_equipamento)
    REFERENCES equipamento (id_equipamento) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------- FICHEIROS --------------------------------

CREATE TABLE ficheiro (
  id_ficheiro    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome           VARCHAR(150) NOT NULL,
  caminho        VARCHAR(255) NOT NULL,
  tipo           VARCHAR(100) NULL,
  tamanho        INT UNSIGNED NULL,
  data_upload    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  id_utilizador  INT UNSIGNED NOT NULL,
  PRIMARY KEY (id_ficheiro),
  KEY fk_ficheiro_utilizador (id_utilizador),
  CONSTRAINT fk_ficheiro_utilizador FOREIGN KEY (id_utilizador)
    REFERENCES utilizador (id_utilizador) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE projeto_ficheiro (
  id_projeto   INT UNSIGNED NOT NULL,
  id_ficheiro  INT UNSIGNED NOT NULL,
  PRIMARY KEY (id_projeto, id_ficheiro),
  KEY fk_projeto_ficheiro_ficheiro (id_ficheiro),
  CONSTRAINT fk_projeto_ficheiro_projeto FOREIGN KEY (id_projeto)
    REFERENCES projeto (id_projeto) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_projeto_ficheiro_ficheiro FOREIGN KEY (id_ficheiro)
    REFERENCES ficheiro (id_ficheiro) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* atividade_ficheiro guarda também a ligação opcional ao projeto/equipamento
   de onde o ficheiro veio. As duas colunas extra são anuláveis porque nem
   todos os ficheiros de uma atividade pertencem a um projeto. */
CREATE TABLE atividade_ficheiro (
  id_atividade           INT UNSIGNED NOT NULL,
  id_ficheiro            INT UNSIGNED NOT NULL,
  id_projeto             INT UNSIGNED NULL,
  id_equipamento_projeto INT UNSIGNED NULL,
  PRIMARY KEY (id_atividade, id_ficheiro),
  KEY fk_atividade_ficheiro_ficheiro (id_ficheiro),
  KEY fk_atividade_ficheiro_projeto_equipamento (id_projeto, id_equipamento_projeto),
  CONSTRAINT fk_atividade_ficheiro_atividade FOREIGN KEY (id_atividade)
    REFERENCES atividade (id_atividade) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_atividade_ficheiro_ficheiro FOREIGN KEY (id_ficheiro)
    REFERENCES ficheiro (id_ficheiro) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_atividade_ficheiro_projeto_equipamento FOREIGN KEY (id_projeto, id_equipamento_projeto)
    REFERENCES projeto_equipamento (id_projeto, id_equipamento) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* participacao_atividade pode estar ligada a um ficheiro de projeto
   (ex.: autorização ou trabalho entregue). A FK composta é opcional. */
CREATE TABLE participacao_atividade (
  id_atividade        INT UNSIGNED NOT NULL,
  id_utilizador       INT UNSIGNED NOT NULL,
  estado_inscricao    ENUM('inscrito','confirmado','cancelado','presente','faltou') NOT NULL DEFAULT 'inscrito',
  papel               ENUM('participante','organizador','orador') NOT NULL DEFAULT 'participante',
  id_projeto          INT UNSIGNED NULL,
  id_ficheiro_projeto INT UNSIGNED NULL,
  PRIMARY KEY (id_atividade, id_utilizador),
  KEY fk_participacao_atividade_utilizador (id_utilizador),
  KEY fk_participacao_projeto_ficheiro (id_projeto, id_ficheiro_projeto),
  CONSTRAINT fk_participacao_atividade_atividade FOREIGN KEY (id_atividade)
    REFERENCES atividade (id_atividade) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_participacao_atividade_utilizador FOREIGN KEY (id_utilizador)
    REFERENCES utilizador (id_utilizador) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_participacao_projeto_ficheiro FOREIGN KEY (id_projeto, id_ficheiro_projeto)
    REFERENCES projeto_ficheiro (id_projeto, id_ficheiro) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------- CIÊNCIA E IA -------------------------------
-- (Estas tabelas ficam no modelo, mas o site ainda não tem páginas para as
--  experiências porque elas não aparecem nos objetivos do projeto.)

CREATE TABLE categoria_cientifica (
  id_categoria  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome          VARCHAR(80) NOT NULL,
  descricao     VARCHAR(255) NULL,
  PRIMARY KEY (id_categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE experiencia (
  id_experiencia  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  titulo          VARCHAR(150) NOT NULL,
  objetivo        TEXT NULL,
  materiais       TEXT NULL,
  procedimento    TEXT NULL,
  explicacao      TEXT NULL,
  seguranca       TEXT NULL,
  nivel           ENUM('basico','intermedio','avancado') NOT NULL DEFAULT 'basico',
  id_categoria    INT UNSIGNED NOT NULL,
  PRIMARY KEY (id_experiencia),
  KEY fk_experiencia_categoria (id_categoria),
  CONSTRAINT fk_experiencia_categoria FOREIGN KEY (id_categoria)
    REFERENCES categoria_cientifica (id_categoria) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* reserva_equipamento fica aqui porque a coluna opcional id_experiencia
   depende da tabela experiencia. */
CREATE TABLE reserva_equipamento (
  id_reserva      INT UNSIGNED NOT NULL,
  id_equipamento  INT UNSIGNED NOT NULL,
  id_experiencia  INT UNSIGNED NULL,
  quantidade      INT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id_reserva, id_equipamento),
  KEY fk_reserva_equipamento_equipamento (id_equipamento),
  KEY fk_reserva_equipamento_experiencia (id_experiencia),
  CONSTRAINT fk_reserva_equipamento_reserva FOREIGN KEY (id_reserva)
    REFERENCES reserva (id_reserva) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_reserva_equipamento_equipamento FOREIGN KEY (id_equipamento)
    REFERENCES equipamento (id_equipamento) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_reserva_equipamento_experiencia FOREIGN KEY (id_experiencia)
    REFERENCES experiencia (id_experiencia) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------ DADOS INICIAIS ------------------------------
-- Só duas categorias de equipamento para o formulário não ficar vazio.
-- Podes apagar/alterar na página "Categorias" do site.
INSERT INTO categoria_equipamento (nome, descricao) VALUES
  ('Microscopia', 'Microscópios, lupas e acessórios'),
  ('Eletrónica e robótica', 'Placas, sensores e kits de robótica');
