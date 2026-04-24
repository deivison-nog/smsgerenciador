-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 24/04/2026 às 03:02
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.0.30
--
-- Última atualização: adicionados campos de profissional (cpf, data_nascimento, perfil,
--   registro_classe, lotacao_id, carga_horaria, regime_contratacao, situacao,
--   cadastrado_em, inativado_em) e tabela frequencia.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `smsgerenciador`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `chamados`
--

CREATE TABLE `chamados` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `tipo` varchar(100) NOT NULL,
  `subtipo` varchar(150) NOT NULL DEFAULT '',
  `titulo` varchar(150) NOT NULL,
  `descricao` text DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'Aberto',
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `estabelecimentos`
--

CREATE TABLE `estabelecimentos` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `telefone` varchar(30) DEFAULT NULL,
  `usuario_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `estabelecimentos`
--

INSERT INTO `estabelecimentos` (`id`, `nome`, `email`, `telefone`, `usuario_id`) VALUES
(1, 'ESF Jangolandia', 'esfjangolandia@gmail.com', NULL, 8),
(2, 'ESF Orla', 'esforla@gmail.com', NULL, 9),
(3, 'ESF Mocajatuba', 'mocajatuba@empresa.com', NULL, 13);

-- --------------------------------------------------------

--
-- Estrutura para tabela `eventos`
--

CREATE TABLE `eventos` (
  `id` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_estabelecimento` int(11) DEFAULT NULL,
  `titulo` varchar(255) NOT NULL,
  `data` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `eventos`
--

INSERT INTO `eventos` (`id`, `id_usuario`, `id_estabelecimento`, `titulo`, `data`) VALUES
(8, 5, 1, 'CONSULTA - ITAJURÁ', '2025-07-29'),
(9, 6, 1, 'visita tecnica', '2025-07-03'),
(11, 6, NULL, 'visita tecnica', '2025-07-11'),
(12, 6, 2, 'visita tecnica', '2025-07-09'),
(13, 12, 1, 'DEMANDA LIVRE - STO ANTONIO', '2025-07-01'),
(14, 5, 2, 'consulta', '2026-03-03'),
(15, 6, 2, 'visita tecnica', '2026-03-03'),
(16, 6, 2, 'REUNIÃO COM OS ACS', '2026-03-27');

-- --------------------------------------------------------

--
-- Estrutura para tabela `frequencia`
--

CREATE TABLE `frequencia` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `estabelecimento_id` int(11) NOT NULL,
  `ano` smallint(6) NOT NULL,
  `mes` tinyint(4) NOT NULL,
  `dia` tinyint(4) NOT NULL,
  `status` enum('P','F','FJ','FO') NOT NULL,
  `observacao` varchar(200) DEFAULT NULL,
  `registrado_por` int(11) NOT NULL,
  `registrado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--
-- Perfis: admin | administrativo | coordenador | profissional
-- Perfil 'admin' → tipo='admin'; demais → tipo='funcionario'
-- 'administrativo': pode cadastrar/inativar funcionários, gerencia frequência e cronograma
-- 'coordenador'   : gerencia frequência e cronograma
-- 'profissional'  : acesso apenas ao cronograma
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `cpf` varchar(14) DEFAULT NULL,
  `data_nascimento` date DEFAULT NULL,
  `ocupacao` varchar(100) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `tipo` enum('admin','funcionario','estabelecimento') NOT NULL DEFAULT 'funcionario',
  `perfil` varchar(30) NOT NULL DEFAULT 'profissional',
  `registro_classe` varchar(50) DEFAULT NULL,
  `lotacao_id` int(11) DEFAULT NULL,
  `carga_horaria` varchar(30) DEFAULT NULL,
  `regime_contratacao` varchar(20) DEFAULT NULL,
  `situacao` varchar(10) NOT NULL DEFAULT 'ativo',
  `cadastrado_em` datetime DEFAULT current_timestamp(),
  `inativado_em` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome`, `cpf`, `data_nascimento`, `ocupacao`, `email`, `senha`, `tipo`, `perfil`, `registro_classe`, `lotacao_id`, `carga_horaria`, `regime_contratacao`, `situacao`, `cadastrado_em`, `inativado_em`) VALUES
(3,  'Jorge',           NULL, NULL, NULL,                    'jorge@teste.com',          '0192023a7bbd73250516f069df18b500', 'admin',         'admin',         NULL, NULL, NULL, NULL, 'ativo', NOW(), NULL),
(5,  'Maria Silva',     NULL, NULL, NULL,                    'maria@empresa.com',        'f8461b554d59b3014e8ff5165dc62fac', 'funcionario',   'profissional',  NULL, NULL, NULL, NULL, 'ativo', NOW(), NULL),
(6,  'Deivison Nogueira', NULL, NULL, 'Técnico em Informática', 'deivison@empresa.com', 'e10adc3949ba59abbe56e057f20f883e', 'funcionario', 'profissional',    NULL, NULL, NULL, NULL, 'ativo', NOW(), NULL),
(8,  'ESF Jangolandia', NULL, NULL, 'ESF',                   'esfjangolandia@gmail.com', '7a2a99fbe75980b6dcf8486037fd9e8e', 'estabelecimento','profissional', NULL, NULL, NULL, NULL, 'ativo', NOW(), NULL),
(9,  'ESF Orla',        NULL, NULL, 'ESF',                   'esforla@gmail.com',        'e10adc3949ba59abbe56e057f20f883e', 'estabelecimento','profissional', NULL, NULL, NULL, NULL, 'ativo', NOW(), NULL),
(10, 'Imar Junior',     NULL, NULL, 'TI',                    'imar@gmail.com',           '28fee3d9c2500df9676982ca053a8f72', 'funcionario',   'profissional',  NULL, NULL, NULL, NULL, 'ativo', NOW(), NULL),
(11, 'Admin',           NULL, NULL, 'Admin',                 'admin@empresa.com',        '0192023a7bbd73250516f069df18b500', 'admin',         'admin',         NULL, NULL, NULL, NULL, 'ativo', NOW(), NULL),
(12, 'Bruce Barros',    NULL, NULL, 'Medico',                'bruce@empresa.com',        'ff58ac7e8a159bfb312ee301d4880266', 'funcionario',   'profissional',  NULL, NULL, NULL, NULL, 'ativo', NOW(), NULL),
(13, 'ESF Mocajatuba',  NULL, NULL, 'ESF',                   'mocajatuba@empresa.com',   '7a2a99fbe75980b6dcf8486037fd9e8e', 'estabelecimento','profissional', NULL, NULL, NULL, NULL, 'ativo', NOW(), NULL);

--
-- Índices para tabelas despejadas
--

ALTER TABLE `chamados`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `estabelecimentos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_estab_usuario` (`usuario_id`);

ALTER TABLE `eventos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `id_estabelecimento` (`id_estabelecimento`);

ALTER TABLE `frequencia`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_freq` (`usuario_id`,`estabelecimento_id`,`ano`,`mes`,`dia`);

ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_usuario_lotacao` (`lotacao_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

ALTER TABLE `chamados`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `estabelecimentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `eventos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

ALTER TABLE `frequencia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Restrições para tabelas despejadas
--

ALTER TABLE `estabelecimentos`
  ADD CONSTRAINT `fk_estab_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

ALTER TABLE `eventos`
  ADD CONSTRAINT `eventos_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_eventos_estabelecimento` FOREIGN KEY (`id_estabelecimento`) REFERENCES `estabelecimentos` (`id`) ON DELETE SET NULL;

ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuario_lotacao` FOREIGN KEY (`lotacao_id`) REFERENCES `estabelecimentos` (`id`) ON DELETE SET NULL;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
