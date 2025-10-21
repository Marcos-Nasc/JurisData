-- --------------------------------------------------------
-- Servidor:                     127.0.0.1
-- Versão do servidor:           10.4.32-MariaDB - mariadb.org binary distribution
-- OS do Servidor:               Win64
-- HeidiSQL Versão:              12.11.0.7065
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Copiando estrutura do banco de dados para juridico
CREATE DATABASE IF NOT EXISTS `juridico` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;
USE `juridico`;

-- Copiando estrutura para tabela juridico.processos_judiciais
CREATE TABLE IF NOT EXISTS `processos_judiciais` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numero_processo` varchar(30) NOT NULL,
  `autor` varchar(150) NOT NULL,
  `data_recebimento` date NOT NULL,
  `ano` year(4) DEFAULT NULL COMMENT 'Ano de referência definido na importação',
  `mes` tinyint(4) DEFAULT NULL COMMENT 'Mês de referência definido na importação',
  `materia` text DEFAULT NULL,
  `central_custo` varchar(100) DEFAULT NULL,
  `valor_causa` decimal(15,2) DEFAULT 0.00,
  `sentenca_1_instancia` text DEFAULT NULL,
  `recurso` text DEFAULT NULL,
  `despesas_processuais_1` decimal(15,2) DEFAULT 0.00,
  `despesas_processuais_2` decimal(15,2) DEFAULT 0.00,
  `valor_pago` decimal(15,2) DEFAULT 0.00,
  `economia` decimal(15,2) DEFAULT 0.00,
  `status` enum('em andamento','finalizado','arquivado') DEFAULT 'em andamento',
  `usuario_id` int(11) DEFAULT NULL,
  `data_cadastro` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero_processo` (`numero_processo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Copiando dados para a tabela juridico.processos_judiciais: ~0 rows (aproximadamente)
DELETE FROM `processos_judiciais`;

-- Copiando estrutura para tabela juridico.relatorio_mensal_processos
CREATE TABLE IF NOT EXISTS `relatorio_mensal_processos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo_vara` varchar(100) NOT NULL,
  `ano` smallint(6) NOT NULL,
  `mes` smallint(6) NOT NULL,
  `quantidade` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `data_atualizacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_relatorio_unico` (`ano`,`mes`,`tipo_vara`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `relatorio_mensal_processos_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Copiando dados para a tabela juridico.relatorio_mensal_processos: ~0 rows (aproximadamente)
DELETE FROM `relatorio_mensal_processos`;

-- Copiando estrutura para tabela juridico.usuarios
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `nivel_acesso` enum('Admin','Advogado','Estagiario') NOT NULL DEFAULT 'Advogado',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `ultimo_login` timestamp NULL DEFAULT NULL,
  `token_recuperacao` varchar(255) DEFAULT NULL,
  `token_expira` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Copiando dados para a tabela juridico.usuarios: ~0 rows (aproximadamente)
DELETE FROM `usuarios`;


-- Copiando estrutura para tabela juridico.configuracoes
CREATE TABLE IF NOT EXISTS `configuracoes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `chave` VARCHAR(255) NOT NULL UNIQUE,
  `valor` TEXT,
  `data_atualizacao` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Copiando dados para a tabela juridico.configuracoes: ~0 rows (aproximadamente)
DELETE FROM `configuracoes`;

/*!40103 SET TIME_ZONE=IFNULL( @OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL( @OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL( @OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT= @OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL( @OLD_SQL_NOTES, 1) */;
