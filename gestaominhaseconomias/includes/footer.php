<?php
/**
 * BDSoft Workspace - Minhas Economias
 * Arquivo: gestaominhaseconomias/includes/footer.php
 * Descrição: Rodapé do sistema, carregamento de scripts e lógica JavaScript global.
 */
?>
    </div> <!-- Fechamento do container-fluid aberto no header.php -->

    <!-- ========================================================
         SCRIPTS E DEPENDÊNCIAS
         ======================================================== -->
    
    <!-- 1. jQuery (Necessário para o MaskMoney) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- 2. Bootstrap 5 Bundle (JS + Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- 3. Chart.js (Gráficos) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- 4. jQuery MaskMoney (Máscaras financeiras R$) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-maskmoney/3.0.2/jquery.maskMoney.min.js"></script>

    <script>
    /**
     * INICIALIZAÇÃO GLOBAL DE MODAIS DO BOOTSTRAP
     */
    let modalConta, modalExcluirConta, modalTransacaoExcluir, modalSucesso, modalCategoria, modalExcluirCat;

    document.addEventListener("DOMContentLoaded", function() {
        // Inicializar instâncias dos modais se os elementos existirem no DOM
        const elConta = document.getElementById('modalConta');
        const elExcluirConta = document.getElementById('modalExcluir');
        const elTransacaoExcluir = document.getElementById('modalExcluirTransacao');
        const elSucesso = document.getElementById('modalSucesso');
        const elCategoria = document.getElementById('modalCategoria');
        const elExcluirCat = document.getElementById('modalExcluirCat');

        if (elConta) modalConta = new bootstrap.Modal(elConta);
        if (elExcluirConta) modalExcluirConta = new bootstrap.Modal(elExcluirConta);
        if (elTransacaoExcluir) modalTransacaoExcluir = new bootstrap.Modal(elTransacaoExcluir);
        if (elSucesso) modalSucesso = new bootstrap.Modal(elSucesso);
        if (elCategoria) modalCategoria = new bootstrap.Modal(elCategoria);
        if (elExcluirCat) modalExcluirCat = new bootstrap.Modal(elExcluirCat);

        // Ativar máscaras de dinheiro
        configurarMascaras();

        // Verificar mensagens de sucesso na URL
        verificarMensagensSucesso();

        // Renderizar o Gráfico de Fluxo Mensal
        renderizarGraficoFluxo();
    });

    /**
     * CONFIGURAÇÃO DE MÁSCARAS FINANCEIRAS
     */
    function configurarMascaras() {
        const opcoesMask = {
            prefix: '', 
            allowNegative: false, 
            thousands: '.', 
            decimal: ',', 
            affixesStay: false
        };

        // Aplica na Transação (Grid)
        if ($('#valorTransacao').length) {
            $('#valorTransacao').maskMoney(opcoesMask);
        }
        // Aplica no Modal de Contas
        if ($('#valor').length) {
            $('#valor').maskMoney(opcoesMask);
        }
    }

    /**
     * FUNÇÕES PARA GESTÃO DE CONTAS (DASHBOARD)
     */
    function abrirModalNovaConta() {
        document.getElementById('id_conta').value = "";
        document.getElementById('nome').value = "";
        document.getElementById('valor').value = "";
        document.getElementById('tipo').value = "Carteira";
        document.getElementById('status').checked = true;
        document.getElementById('labelModal').innerText = "Cadastrar Nova Conta";
        modalConta.show();
    }

    function prepararEdicao(id, nome, valor, status, tipo) {
        document.getElementById('id_conta').value = id;
        document.getElementById('nome').value = nome;
        document.getElementById('valor').value = valor.replace('.', ',');
        document.getElementById('tipo').value = tipo;
        document.getElementById('status').checked = (status == 1);
        document.getElementById('labelModal').innerText = "Editar Conta Financeira";
        modalConta.show();
    }

    function confirmarExclusao(id, nome) {
        document.getElementById('id_conta_excluir').value = id;
        document.getElementById('txtNomeExcluir').innerText = nome;
        modalExcluirConta.show();
    }

    /**
     * FUNÇÕES PARA GESTÃO DE TRANSAÇÕES (GRID)
     */
    function mostrarLinhaAdicionar() {
        document.getElementById('id_transacao').value = "";
        document.getElementById('linhaAdd').style.display = 'table-row';
        document.getElementById('data_t').focus();
    }

    function ocultarLinhaAdicionar() {
        document.getElementById('linhaAdd').style.display = 'none';
    }

    function editarTransacao(id, data, desc, cat, conta, valor, tipo) {
        document.getElementById('id_transacao').value = id;
        document.getElementById('data_t').value = data;
        document.getElementById('desc_t').value = desc;
        document.getElementById('cat_t').value = cat;
        document.getElementById('conta_t').value = conta;
        document.getElementById('valorTransacao').value = valor.toString().replace('.', ',');
        document.getElementById('tipo_t').value = tipo;
        document.getElementById('linhaAdd').style.display = 'table-row';
        document.getElementById('linhaAdd').scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function confirmarExcluirTransacao(id, desc) {
        document.getElementById('id_t_excluir').value = id;
        document.getElementById('txtNomeTExcluir').innerText = desc;
        modalTransacaoExcluir.show();
    }

    /**
     * FUNÇÕES PARA GESTÃO DE CATEGORIAS
     */
    function abrirModalNovaCategoria() {
        document.getElementById('id_categoria').value = "";
        document.getElementById('nome_cat').value = "";
        document.getElementById('tipo_cat').value = "Despesa";
        document.getElementById('pai_id_cat').value = "";
        document.getElementById('labelModalCat').innerText = "Nova Categoria";
        modalCategoria.show();
    }

    function prepararEdicaoCategoria(id, nome, tipo, pai_id) {
        document.getElementById('id_categoria').value = id;
        document.getElementById('nome_cat').value = nome;
        document.getElementById('tipo_cat').value = tipo;
        document.getElementById('pai_id_cat').value = pai_id;
        document.getElementById('labelModalCat').innerText = "Editar Categoria";
        modalCategoria.show();
    }

    function confirmarExclusaoCategoria(id, nome) {
        document.getElementById('id_categoria_excluir').value = id;
        document.getElementById('nome_cat_excluir').innerText = nome;
        modalExcluirCat.show();
    }

    /**
     * LÓGICA DE FEEDBACK (ALERTAS DE SUCESSO)
     */
    function verificarMensagensSucesso() {
        const urlParams = new URLSearchParams(window.location.search);
        const success = urlParams.get('success');
        const pagina = urlParams.get('p') || 'dashboard';

        if (success && modalSucesso) {
            let msg = "Operação realizada com sucesso!";
            
            if (success === 'conta_criada') msg = "Sua nova conta foi registrada.";
            if (success === 'conta_atualizada') msg = "Os dados da conta foram salvos.";
            if (success === 'conta_excluida') msg = "A conta foi removida permanentemente.";
            if (success === 'transacao_ok') msg = "Lançamento financeiro concluído!";
            if (success === 'transacao_atualizada') msg = "Lançamento editado com sucesso.";
            if (success === 'transacao_excluida') msg = "Lançamento removido do fluxo.";
            if (success === 'cat_criada') msg = "Nova categoria adicionada.";
            if (success === 'cat_atualizada') msg = "Categoria editada com sucesso.";
            if (success === 'cat_excluida') msg = "Categoria removida.";

            document.getElementById('msgDesc').innerText = msg;
            modalSucesso.show();

            // Limpa a URL para não repetir o modal ao atualizar (F5)
            const cleanUrl = window.location.pathname + "?p=" + pagina;
            window.history.replaceState({}, document.title, cleanUrl);
        }
    }

    /**
     * CONFIGURAÇÃO DO GRÁFICO (CHART.JS)
     */
    function renderizarGraficoFluxo() {
        const canvas = document.getElementById('financeChart');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        
        // Dados injetados pelo PHP nas views correspondentes
        const valReceitas = window.dadosReceitas || 0;
        const valDespesas = window.dadosDespesas || 0;

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Ganhos / Receitas', 'Gastos / Despesas'],
                datasets: [{
                    data: [valReceitas, valDespesas],
                    backgroundColor: [
                        'rgba(46, 204, 113, 0.85)', // Verde
                        'rgba(231, 76, 60, 0.85)'   // Vermelho
                    ],
                    borderColor: ['#27ae60', '#e74c3c'],
                    borderWidth: 1,
                    borderRadius: 12,
                    barPercentage: 0.5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return ' R$ ' + context.parsed.y.toLocaleString('pt-BR', {minimumFractionDigits: 2});
                            }
                        }
                    }
                },
                onClick: (evt, element) => {
                    // Lógica de clique solicitada: Filtrar ao clicar na barra
                    if (element.length > 0) {
                        const index = element[0].index;
                        const tipoFiltro = (index === 0) ? 'Receita' : 'Despesa';
                        
                        // Captura mês e ano atuais da URL ou do sistema
                        const urlP = new URLSearchParams(window.location.search);
                        const m = urlP.get('mes') || '<?= date("m") ?>';
                        const a = urlP.get('ano') || '<?= date("Y") ?>';
                        
                        // Redireciona para a aba de transações com o filtro ativo
                        window.location.href = `index.php?p=transacoes&f_tipo=${tipoFiltro}&mes=${m}&ano=${a}`;
                    }
                },
                scales: {
                    y: { 
                        beginAtZero: true,
                        grid: { color: '#f8f9fa' },
                        ticks: {
                            callback: function(value) {
                                return 'R$ ' + value.toLocaleString('pt-BR');
                            }
                        }
                    },
                    x: { grid: { display: false } }
                }
            }
        });
    }
    </script>
</body>
</html>