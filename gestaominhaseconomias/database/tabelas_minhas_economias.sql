-- 1. Contas Financeiras (Bancos, Carteira, Cartões)
CREATE TABLE `minhaseconomias_contas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL, -- Chave estrangeira para sua tabela 'usuarios'
  `nome` varchar(100) NOT NULL,
  `tipo` enum('Carteira', 'Banco', 'Cartao', 'Empresa') NOT NULL,
  `saldo_inicial` decimal(10,2) DEFAULT '0.00',
  `cor` varchar(7) DEFAULT '#3498db',
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_me_usuario_contas` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Categorias de Fluxo
CREATE TABLE `minhaseconomias_categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `tipo` enum('Receita', 'Despesa') NOT NULL,
  `icone` varchar(50) DEFAULT 'fa-tag',
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_me_usuario_cat` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Movimentações Financeiras (Onde a mágica acontece)
CREATE TABLE `minhaseconomias_movimentacoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `conta_id` int(11) NOT NULL,
  `categoria_id` int(11) NOT NULL,
  `descricao` varchar(255) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `data_vencimento` date NOT NULL,
  `data_pagamento` date DEFAULT NULL,
  `status` enum('Pendente', 'Pago') DEFAULT 'Pendente',
  `tipo` enum('Receita', 'Despesa') NOT NULL,
  `parcela_atual` int(11) DEFAULT '1',
  `total_parcelas` int(11) DEFAULT '1',
  `origem_pj` tinyint(1) DEFAULT '0', -- 0 para Pessoal, 1 para Empresarial (Requisito 6)
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_me_conta` FOREIGN KEY (`conta_id`) REFERENCES `minhaseconomias_contas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_me_cat` FOREIGN KEY (`categoria_id`) REFERENCES `minhaseconomias_categorias` (`id`),
  CONSTRAINT `fk_me_usuario_mov` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;