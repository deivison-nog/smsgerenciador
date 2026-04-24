-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 24/04/2026 às 03:02
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `cronograma_empresa`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `chamados`
--

CREATE TABLE `chamados` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `tipo` varchar(50) NOT NULL,
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
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `ocupacao` varchar(100) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `tipo` enum('admin','funcionario','estabelecimento') NOT NULL DEFAULT 'funcionario'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome`, `ocupacao`, `email`, `senha`, `tipo`) VALUES
(3, 'Jorge', NULL, 'jorge@teste.com', '0192023a7bbd73250516f069df18b500', 'admin'),
(5, 'Maria Silva', NULL, 'maria@empresa.com', 'f8461b554d59b3014e8ff5165dc62fac', 'funcionario'),
(6, 'Deivison Nogueira', 'Técnico em Informática', 'deivison@empresa.com', 'e10adc3949ba59abbe56e057f20f883e', 'funcionario'),
(8, 'ESF Jangolandia', 'ESF', 'esfjangolandia@gmail.com', '7a2a99fbe75980b6dcf8486037fd9e8e', 'estabelecimento'),
(9, 'ESF Orla', 'ESF', 'esforla@gmail.com', 'e10adc3949ba59abbe56e057f20f883e', 'estabelecimento'),
(10, 'Imar Junior', 'TI', 'imar@gmail.com', '28fee3d9c2500df9676982ca053a8f72', 'funcionario'),
(11, 'Admin', 'Admin', 'admin@empresa.com', '0192023a7bbd73250516f069df18b500', 'admin'),
(12, 'Bruce Barros', 'Medico', 'bruce@empresa.com', 'ff58ac7e8a159bfb312ee301d4880266', 'funcionario'),
(13, 'ESF Mocajatuba', 'ESF', 'mocajatuba@empresa.com', '7a2a99fbe75980b6dcf8486037fd9e8e', 'estabelecimento');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `chamados`
--
ALTER TABLE `chamados`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `estabelecimentos`
--
ALTER TABLE `estabelecimentos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_estab_usuario` (`usuario_id`);

--
-- Índices de tabela `eventos`
--
ALTER TABLE `eventos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `id_estabelecimento` (`id_estabelecimento`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `chamados`
--
ALTER TABLE `chamados`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `estabelecimentos`
--
ALTER TABLE `estabelecimentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `eventos`
--
ALTER TABLE `eventos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `estabelecimentos`
--
ALTER TABLE `estabelecimentos`
  ADD CONSTRAINT `fk_estab_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `eventos`
--
ALTER TABLE `eventos`
  ADD CONSTRAINT `eventos_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_eventos_estabelecimento` FOREIGN KEY (`id_estabelecimento`) REFERENCES `estabelecimentos` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
