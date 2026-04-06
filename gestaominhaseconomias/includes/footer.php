<?php
/**
 * BDSoft Workspace - Minhas Economias
 * Footer UNIVERSAL - Proteção total contra erros de referência
 */
?>
    </div> <!-- Fim do container aberto no header.php -->

    <!-- ========================================================
         MODAIS GLOBAIS (Presentes em todas as páginas)
         ======================================================== -->

    <!-- 1. MODAL CATEGORIA (NOVO/EDITAR/ATALHO) -->
    <div class="modal fade" id="modalCategoria" tabindex="-1" aria-hidden="true" style="z-index: 1095;">
        <div class="modal-dialog modal-dialog-centered">
            <form action="index.php" method="POST" class="modal-content border-0 shadow-lg rounded-4">
                <input type="hidden" name="id_categoria" id="id_categoria">
                <input type="hidden" name="origem_requisicao" id="origem_requisicao" value="categorias">
                
                <div class="modal-header border-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold text-dark" id="labelModalCat">Gerenciar Categoria</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Nome da Categoria</label>
                        <input type="text" name="nome" id="nome_cat" class="form-control bg-light border-0 py-2" placeholder="Ex: Alimentação, Lazer..." required>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Tipo</label>
                            <select name="tipo" id="tipo_cat" class="form-select bg-light border-0">
                                <option value="AMBOS">Ambos (Flexível)</option>
                                <option value="DESPESA">Apenas Despesa</option>
                                <option value="RECEITA">Apenas Receita</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Categoria Pai</label>
                            <select name="pai_id" id="pai_id_cat" class="form-select bg-light border-0">
                                <option value="">Nenhuma (Principal)</option>
                                <?php 
                                    // Busca categorias pai dinamicamente para o seletor em qualquer página
                                    $stP = $pdo->prepare("SELECT id, nome FROM minhaseconomias_categorias WHERE usuario_id = ? AND parent_id IS NULL AND id != 999 ORDER BY nome ASC");
                                    $stP->execute([$_SESSION['usuario_id']]);
                                    while($p = $stP->fetch(PDO::FETCH_ASSOC)) {
                                        echo "<option value='{$p['id']}'>{$p['nome']}</option>";
                                    }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="submit" name="btn_salvar_categoria" class="btn btn-primary w-100 rounded-pill py-3 fw-bold shadow">
                        <i class="fas fa-check-circle me-1"></i> SALVAR CATEGORIA
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 2. MODAL SUCESSO GLOBAL -->
    <div class="modal fade" id="modalSucesso" tabindex="-1" aria-hidden="true" style="z-index: 1100;">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow-lg text-center" style="border-radius: 20px;">
                <div class="modal-body p-4 text-center">
                    <i class="fas fa-check-circle text-success fa-4x mb-3"></i>
                    <h5 class="fw-bold text-dark">Pronto!</h5>
                    <p class="text-muted small mb-0" id="msgDescSucesso">Ação concluída com sucesso.</p>
                    <button class="btn btn-dark btn-sm rounded-pill px-5 mt-4 fw-bold shadow-sm" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. MODAL FEEDBACK / ERRO -->
    <div class="modal fade" id="modalFeedback" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow-lg text-center" style="border-radius: 20px;">
                <div class="modal-body p-4">
                    <div id="feedbackIcon" class="mb-3"></div>
                    <h5 class="fw-bold mb-1" id="feedbackTitle"></h5>
                    <p class="text-muted small mb-0" id="feedbackMsg"></p>
                    <button type="button" class="btn btn-dark btn-sm rounded-pill px-4 mt-3 fw-bold" data-bs-dismiss="modal">Ok</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================
         SCRIPTS E LÓGICA BLINDADA
         ======================================================== -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
    /**
     * INICIALIZAÇÃO DE VARIÁVEIS TIPO 'WINDOW'
     * Isso garante que as funções sejam acessíveis de qualquer lugar do sistema.
     */
    window.mC = null; window.mEC = null; window.mT = null; window.mET = null; 
    window.mS = null; window.mCat = null; window.mECat = null; window.mP = null;

    document.addEventListener("DOMContentLoaded", function() {
        // Função interna para instanciar modais apenas se o elemento existir no HTML
        const safeInit = (id) => {
            const el = document.getElementById(id);
            return el ? new bootstrap.Modal(el) : null;
        };
        
        window.mC = safeInit('modalConta');
        window.mEC = safeInit('modalExcluir');
        window.mT = safeInit('modalTransacao');
        window.mET = safeInit('modalExcluirTransacao');
        window.mS = safeInit('modalSucesso');
        window.mCat = safeInit('modalCategoria');
        window.mECat = safeInit('modalExcluirCat');
        window.mP = safeInit('modalPendenciasHoje');

        // Ativação da máscara de dinheiro (Otimizada para não travar o campo do Saldo)
        inicializarMascarasNativas();

        // Renderização dos gráficos se houver dados
        if (typeof window.dadosReal !== 'undefined') initCharts();

        // Alerta automático de pendências hoje
        if (window.pendenciasHoje > 0 && window.mP) window.mP.show();

        // Processamento de mensagens de sucesso vindo da URL (?success=...)
        verificarRetornoServidor();
    });

    /**
     * MÁSCARA MONETÁRIA NATIVA (MOBILE FRIENDLY)
     * Não trava o campo e permite digitação fluída da esquerda para a direita.
     */
    function inicializarMascarasNativas() {
        // Selecionamos apenas os campos de transação. O campo de saldo de conta é tipo 'number' nativo.
        const inputs = document.querySelectorAll('#input_valor_t, #valorTransacao');
        inputs.forEach(i => {
            i.setAttribute('inputmode', 'numeric');
            i.addEventListener('input', (e) => {
                let v = e.target.value.replace(/\D/g, "");
                if (v === "") return e.target.value = "";
                v = (parseInt(v) / 100).toFixed(2);
                let p = v.split("."); 
                p[0] = p[0].replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                e.target.value = p.join(",");
            });
        });
    }

    /**
     * FUNÇÕES GLOBAIS DE CATEGORIAS (BLINDADAS)
     */
    window.abrirModalNovaCategoria = function() {
        if(!window.mCat) return;
        document.getElementById('id_categoria').value = "";
        document.getElementById('nome_cat').value = "";
        document.getElementById('tipo_cat').value = "AMBOS";
        document.getElementById('pai_id_cat').value = "";
        document.getElementById('origem_requisicao').value = "categorias";
        document.getElementById('labelModalCat').innerText = "Nova Categoria";
        window.mCat.show();
    };

    window.abrirAtalhoCategoria = function() {
        if(!window.mCat) return;
        document.getElementById('id_categoria').value = "";
        document.getElementById('nome_cat').value = "";
        document.getElementById('origem_requisicao').value = "transacoes";
        document.getElementById('labelModalCat').innerText = "Adicionar Categoria Rápida";
        window.mCat.show();
    };

    window.prepararEdicaoCategoria = function(id, nome, tipo, pai) {
        if(!window.mCat) return;
        document.getElementById('id_categoria').value = id;
        document.getElementById('nome_cat').value = nome;
        document.getElementById('tipo_cat').value = tipo;
        document.getElementById('pai_id_cat').value = pai;
        document.getElementById('origem_requisicao').value = "categorias";
        document.getElementById('labelModalCat').innerText = "Editar Categoria";
        window.mCat.show();
    };

    window.confirmarExclusaoCategoria = function(id, nome) {
        if(!window.mECat) return;
        document.getElementById('id_cat_excluir').value = id;
        document.getElementById('txtNomeExcluirCat').innerText = nome;
        window.mECat.show();
    };

    /**
     * FUNÇÕES GLOBAIS DE TRANSAÇÕES
     */
    window.abrirModalNovaTransacao = function() { if(window.mT) window.mT.show(); };
    window.prepararEdicaoTransacao = function(id, data, desc, cat, conta, valor, tipo, status) {
        if(!window.mT) return;
        document.getElementById('id_t').value = id;
        document.getElementById('input_data_t').value = data;
        document.getElementById('input_desc_t').value = desc;
        document.getElementById('input_cat_t').value = cat;
        document.getElementById('input_conta_t').value = conta;
        document.getElementById('input_valor_t').value = valor.toString().replace('.', ',');
        document.getElementById('input_tipo_t').value = tipo;
        document.getElementById('input_status_t').value = status;
        window.mT.show();
    };
    window.confirmarExcluirTransacao = function(id, desc) {
        if(!window.mET) return;
        document.getElementById('id_t_excluir').value = id;
        document.getElementById('txtNomeTExcluir').innerText = desc;
        window.mET.show();
    };

    /**
     * FUNÇÕES GLOBAIS DE CONTAS (DASHBOARD)
     */
    window.abrirModalNovaConta = function() { if(window.mC) window.mC.show(); };
    window.prepararEdicao = function(id, nome, valor, status, tipo) {
        if(!window.mC) return;
        document.getElementById('id_conta').value = id;
        document.getElementById('nome').value = nome;
        document.getElementById('valor').value = valor; // Campo number nativo
        document.getElementById('tipo').value = tipo;
        document.getElementById('status').checked = (status == 1);
        window.mC.show();
    };
    window.confirmarExclusao = function(id, nome) {
        if(!window.mEC) return;
        document.getElementById('id_conta_excluir').value = id;
        document.getElementById('txtNomeExcluir').innerText = nome;
        window.mEC.show();
    };

    /**
     * MOTOR DE GRÁFICOS (CHART.JS)
     */
    let chartReal, chartFuturo, chartCategorias;
    function initCharts() {
        const ctxR = document.getElementById('chartRealizado'); 
        const ctxF = document.getElementById('chartFuturo');
        const ctxC = document.getElementById('chartCategorias');
        if(!ctxR || !ctxF) return;

        const opt = (sF) => ({ responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, onClick: (e, el) => { if (el.length > 0) { const t = el[0].index === 0 ? 'Receita' : 'Despesa'; window.location.href = `index.php?p=transacoes&f_tipo=${t}&f_status=${sF}&mes=${window.filtroAtual.mes}&ano=${window.filtroAtual.ano}`; } } });

        new Chart(ctxR.getContext('2d'), { type: 'bar', data: { labels: ['Ganhos', 'Gastos'], datasets: [{ data: [window.dadosReal.receita, window.dadosReal.despesa], backgroundColor: ['#2ecc71', '#e74c3c'], borderRadius: 10 }] }, options: opt('Pago') });
        new Chart(ctxF.getContext('2d'), { type: 'bar', data: { labels: ['A Receber', 'A Pagar'], datasets: [{ data: [window.dadosFuturo.receita, window.dadosFuturo.despesa], backgroundColor: ['#3498db', '#f1c40f'], borderRadius: 10 }] }, options: opt('Futuro') });
        
        if(ctxC && window.dadosCategorias.labels.length > 0) {
            new Chart(ctxC.getContext('2d'), { type: 'doughnut', data: { labels: window.dadosCategorias.labels, datasets: [{ data: window.dadosCategorias.values, backgroundColor: ['#1a73e8','#f1c40f','#e74c3c','#2ecc71','#9b59b6','#34495e'] }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } } } } });
        }
    }

    /**
     * VERIFICAÇÃO DE FEEDBACKS DA URL
     */
    function verificarRetornoServidor() {
        const p = new URLSearchParams(window.location.search);
        const s = p.get('success');
        if (s && window.mS) {
            let msg = "A operação foi realizada com sucesso.";
            if(s==='transacao_excluida') msg = "Lançamento removido do seu histórico.";
            if(s==='transacao_ok') msg = "Transação salva no seu fluxo financeiro.";
            if(s==='cat_ok') msg = "Categoria salva com sucesso!";
            if(s==='cat_excluida') msg = "Categoria removida permanentemente.";
            if(s==='conta_ok') msg = "Os dados da sua conta foram atualizados.";
            
            document.getElementById('msgDescSucesso').innerText = msg;
            window.mS.show();
            // Limpa a URL sem atualizar para não repetir o alerta
            window.history.replaceState({}, document.title, window.location.pathname + "?p=" + (p.get('p') || 'dashboard') + (p.get('mes') ? "&mes="+p.get('mes') : "") + (p.get('ano') ? "&ano="+p.get('ano') : ""));
        }
    }

    function alterarTipoGrafico(v) { location.reload(); }
    </script>

    <footer class="text-center py-4 mt-5 border-top bg-white no-print">
        <div class="container">
            <p class="mb-0 text-muted small">
                <strong>Minhas Economias</strong> - Tecnologia Cloud desenvolvido por <strong>BDSoftech</strong> &copy; 2026
            </p>
        </div>
    </footer>
</body>
</html>