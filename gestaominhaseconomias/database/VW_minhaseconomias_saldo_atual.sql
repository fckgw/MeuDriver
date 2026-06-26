CREATE OR REPLACE VIEW vw_minhaseconomias_saldo_atual AS
SELECT 
    c.id AS conta_id,
    c.usuario_id,
    c.nome AS conta_nome,
    c.saldo_inicial,
    COALESCE((SELECT SUM(valor) FROM minhaseconomias_movimentacoes WHERE conta_id = c.id AND tipo = 'RECEITA' AND status = 'Pago'), 0) AS total_receitas,
    COALESCE((SELECT SUM(valor) FROM minhaseconomias_movimentacoes WHERE conta_id = c.id AND tipo = 'DESPESA' AND status = 'Pago'), 0) AS total_despesas,
    (c.saldo_inicial + 
     COALESCE((SELECT SUM(valor) FROM minhaseconomias_movimentacoes WHERE conta_id = c.id AND tipo = 'RECEITA' AND status = 'Pago'), 0) - 
     COALESCE((SELECT SUM(valor) FROM minhaseconomias_movimentacoes WHERE conta_id = c.id AND tipo = 'DESPESA' AND status = 'Pago'), 0)
    ) AS saldo_atual
FROM minhaseconomias_contas c;