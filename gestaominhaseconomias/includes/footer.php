<?php
/**
 * BDSoft Workspace - Minhas Economias
 * Footer Final Padronizado
 */
?>
    </div> <!-- Fim container-fluid aberto no header.php -->

    <!-- MODAIS GLOBAIS DE FEEDBACK -->
    <div class="modal fade" id="modalFeedback" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow-lg text-center" style="border-radius: 20px;">
                <div class="modal-body p-4">
                    <div id="feedbackIcon" class="mb-3"></div>
                    <h5 class="fw-bold mb-1" id="feedbackTitle"></h5>
                    <p class="text-muted small mb-0" id="feedbackMsg"></p>
                    <button type="button" class="btn btn-dark btn-sm rounded-pill px-4 mt-3" data-bs-dismiss="modal">Ok</button>
                </div>
            </div>
        </div>
    </div>

    <!-- SCRIPTS DE DEPENDÊNCIA -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-maskmoney/3.0.2/jquery.maskMoney.min.js"></script>
    
    <script>
    // --- INICIALIZAÇÃO DE MODAIS ---
    const getModalInstance = (id) => { 
        const el = document.getElementById(id); 
        return el ? new bootstrap.Modal(el) : null; 
    };

    const modalFormC = getModalInstance('modalConta');
    const modalDelC  = getModalInstance('modalExcluir');
    const modalDelT  = getModalInstance('modalExcluirTransacao');
    const modalFeedback = getModalInstance('modalFeedback');
    const modalPendencias = getModalInstance('modalPendenciasHoje');

    // --- FUNÇÕES DE CONTA (DASHBOARD) ---
    function abrirModalNovaConta() {
        if(!modalFormC) return;
        document.getElementById('id_conta').value = "";
        document.getElementById('nome').value = "";
        document.getElementById('valor').value = "";
        document.getElementById('labelModal').innerText = "Nova Conta";
        modalFormC.show();
    }
    function prepararEdicao(id, nome, valor, status, tipo) {
        if(!modalFormC) return;
        document.getElementById('id_conta').value = id;
        document.getElementById('nome').value = nome;
        document.getElementById('valor').value = valor.toString().replace('.', ',');
        document.getElementById('tipo').value = tipo;
        document.getElementById('status').checked = (status == 1);
        document.getElementById('labelModal').innerText = "Editar Conta";
        modalFormC.show();
    }
    function confirmarExclusao(id, nome) {
        if(!modalDelC) return;
        document.getElementById('id_conta_excluir').value = id;
        document.getElementById('txtNomeExcluir').innerText = nome;
        modalDelC.show();
    }

    // --- FUNÇÕES DE TRANSAÇÃO (GRID) ---
    function mostrarLinhaAdicionar() {
        const row = document.getElementById('linhaAdd');
        if(row) { 
            document.getElementById('id_transacao').value = ""; 
            row.style.display = 'table-row'; 
            document.getElementById('data_t').focus();
        }
    }
    function ocultarLinhaAdicionar() {
        const row = document.getElementById('linhaAdd');
        if(row) row.style.display = 'none';
    }
    function editarTransacao(id, data, desc, cat, conta, valor, tipo, status) {
        const row = document.getElementById('linhaAdd');
        if(!row) return;
        const setV = (i, v) => { const el = document.getElementById(i); if(el) el.value = v; };
        setV('id_transacao', id); 
        setV('data_t', data); 
        setV('desc_t', desc); 
        setV('cat_t', cat); 
        setV('conta_t', conta);
        setV('valorTransacao', valor.toString().replace('.', ',')); 
        setV('tipo_t', tipo); 
        setV('status_t', status);
        row.style.display = 'table-row';
        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    function confirmarExcluirTransacao(id, desc) {
        if(!modalDelT) return;
        document.getElementById('id_t_excluir').value = id;
        document.getElementById('txtNomeTExcluir').innerText = desc;
        modalDelT.show();
    }

    // --- GRÁFICOS (CHART.JS) ---
    let chartReal, chartFuturo;
    function initCharts(type) {
        const ctxR = document.getElementById('chartRealizado');
        const ctxF = document.getElementById('chartFuturo');
        if(!ctxR || !ctxF) return;
        if(chartReal) chartReal.destroy(); 
        if(chartFuturo) chartFuturo.destroy();

        const opt = (sF) => ({
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: type === 'doughnut', position: 'bottom' } },
            onClick: (e, el) => {
                if (el.length > 0) {
                    const t = el[0].index === 0 ? 'Receita' : 'Despesa';
                    window.location.href = `index.php?p=transacoes&f_tipo=${t}&f_status=${sF}&mes=${window.filtroAtual.mes}&ano=${window.filtroAtual.ano}`;
                }
            }
        });

        chartReal = new Chart(ctxR, { type: type, data: { labels: ['Ganhos', 'Gastos'], datasets: [{ data: [window.dadosReal.receita, window.dadosReal.despesa], backgroundColor: ['#2ecc71', '#e74c3c'], borderRadius: 10 }] }, options: opt('Pago') });
        chartFuturo = new Chart(ctxF, { type: type, data: { labels: ['A Receber', 'A Pagar'], datasets: [{ data: [window.dadosFuturo.receita, window.dadosFuturo.despesa], backgroundColor: ['#3498db', '#f1c40f'], borderRadius: 10 }] }, options: opt('Futuro') });
    }
    function alterarTipoGrafico(v) { initCharts(v); }

    // --- INICIALIZAÇÃO GERAL ---
    $(function() {
        initCharts('bar');
        // Máscara de Moeda
        if ($('#valorTransacao').length) {
            $('#valorTransacao').maskMoney({prefix: '', allowNegative: false, thousands: '.', decimal: ',', affixesStay: false});
        }
        
        // Auto-show de Pendências (Dashboard)
        if (window.pendenciasHoje > 0 && modalPendencias) {
            modalPendencias.show();
        }

        // Pop-up de Sucessos e Erros via URL
        const urlParams = new URLSearchParams(window.location.search);
        const success = urlParams.get('success');
        const error = urlParams.get('error');

        if ((success || error) && modalFeedback) {
            const iconDiv = document.getElementById('feedbackIcon');
            const titleH5 = document.getElementById('feedbackTitle');
            const msgP    = document.getElementById('feedbackMsg');

            if (success) {
                iconDiv.innerHTML = '<i class="fas fa-check-circle text-success fa-4x"></i>';
                titleH5.innerText = "Tudo certo!";
                titleH5.className = "fw-bold mb-1 text-success";
                msgP.innerText = "Sua transação foi processada com sucesso.";
            } else {
                iconDiv.innerHTML = '<i class="fas fa-times-circle text-danger fa-4x"></i>';
                titleH5.innerText = "Falha no sistema";
                titleH5.className = "fw-bold mb-1 text-danger";
                msgP.innerText = decodeURIComponent(error);
            }
            modalFeedback.show();
            window.history.replaceState({}, document.title, window.location.pathname + "?p=" + (urlParams.get('p') || 'dashboard'));
        }
    });
    </script>

    <!-- RODAPÉ PADRÃO BRANDING -->
    <footer class="text-center py-4 mt-5 border-top bg-white no-print">
        <div class="container">
            <p class="mb-0 text-muted small">
                Minhas Economias - Tecnologia Cloud desenvolvido por <strong>BDSoftech</strong> &copy; <?= date('Y') ?>
            </p>
        </div>
    </footer>
</body>
</html>