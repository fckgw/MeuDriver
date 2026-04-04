<?php
/**
 * BDSoft Workspace - Minhas Economias
 * Footer DEFINITIVO - Centralizador de Inteligência e Modais Globais
 */
?>
    </div> <!-- Fim do container-fluid aberto no header.php -->

    <!-- ========================================================
         MODAIS GLOBAIS (Acessíveis de qualquer página)
         ======================================================== -->

    <!-- 1. MODAL CATEGORIA (NOVO/EDITAR) -->
    <div class="modal fade" id="modalCategoria" tabindex="-1" aria-hidden="true" style="z-index: 1095;">
        <div class="modal-dialog modal-dialog-centered">
            <form action="index.php" method="POST" class="modal-content border-0 shadow-lg rounded-4">
                <input type="hidden" name="id_categoria" id="id_categoria">
                <input type="hidden" name="origem_requisicao" id="origem_requisicao" value="categorias">
                <div class="modal-header border-0 pt-4 px-4"><h5 class="modal-title fw-bold text-dark" id="labelModalCat">Gerenciar Categoria</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body p-4">
                    <div class="mb-3"><label class="form-label small fw-bold text-muted">NOME DA CATEGORIA</label><input type="text" name="nome" id="nome_cat" class="form-control bg-light border-0 py-2" required></div>
                    <div class="row g-3">
                        <div class="col-6"><label class="small fw-bold text-muted">TIPO</label><select name="tipo" id="tipo_cat" class="form-select bg-light border-0"><option value="AMBOS">Ambos</option><option value="DESPESA">Despesa</option><option value="RECEITA">Receita</option></select></div>
                        <div class="col-6">
                            <label class="small fw-bold text-muted">PAI (OPCIONAL)</label>
                            <select name="pai_id" id="pai_id_cat" class="form-select bg-light border-0">
                                <option value="">Principal</option>
                                <?php 
                                    $stP = $pdo->prepare("SELECT id, nome FROM minhaseconomias_categorias WHERE usuario_id = ? AND parent_id IS NULL AND id != 999 ORDER BY nome ASC");
                                    $stP->execute([$_SESSION['usuario_id']]);
                                    while($p = $stP->fetch(PDO::FETCH_ASSOC)) echo "<option value='{$p['id']}'>{$p['nome']}</option>";
                                ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0"><button type="submit" name="btn_salvar_categoria" class="btn btn-primary w-100 rounded-pill py-3 fw-bold shadow">SALVAR CATEGORIA</button></div>
            </form>
        </div>
    </div>

    <!-- 2. MODAL SUCESSO -->
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

    <!-- 3. MODAL EXCLUIR CATEGORIA -->
    <div class="modal fade" id="modalExcluirCat" tabindex="-1" aria-hidden="true" style="z-index: 1096;">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow-lg text-center rounded-4">
                <div class="modal-body p-5">
                    <i class="fas fa-trash text-danger fa-4x mb-4 opacity-25"></i>
                    <h5 class="fw-bold">Remover?</h5>
                    <p class="text-muted small" id="txtNomeExcluirCat"></p>
                    <form action="index.php" method="POST">
                        <input type="hidden" name="id_categoria_excluir" id="id_cat_excluir">
                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" name="btn_excluir_categoria" class="btn btn-danger rounded-pill fw-bold">SIM, EXCLUIR</button>
                            <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">NÃO</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. MODAL EXCLUIR TRANSAÇÃO -->
    <div class="modal fade" id="modalExcluirTransacao" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content border-0 shadow-lg text-center rounded-4"><div class="modal-body p-5"><i class="fas fa-trash-alt text-danger fa-4x mb-4 opacity-25"></i><h5 class="fw-bold">Remover?</h5><p class="text-muted small" id="txtNomeTExcluir"></p><form action="index.php" method="POST"><input type="hidden" name="id_transacao_excluir" id="id_t_excluir"><div class="d-grid gap-2 mt-4"><button type="submit" name="btn_excluir_transacao" class="btn btn-danger rounded-pill fw-bold shadow">SIM, EXCLUIR</button><button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Não</button></div></form></div></div></div></div>

    <!-- SCRIPTS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
    // --- INSTÂNCIAS GLOBAIS ---
    window.mC = null; window.mEC = null; window.mT = null; window.mET = null; 
    window.mS = null; window.mCat = null; window.mECat = null; window.mP = null;

    document.addEventListener("DOMContentLoaded", function() {
        const initM = (id) => { const el = document.getElementById(id); return el ? new bootstrap.Modal(el) : null; };
        
        window.mC = initM('modalConta');
        window.mEC = initM('modalExcluir');
        window.mT = initM('modalTransacao');
        window.mET = initM('modalExcluirTransacao');
        window.mS = initM('modalSucesso');
        window.mCat = initM('modalCategoria');
        window.mECat = initM('modalExcluirCat');
        window.mP = initM('modalPendenciasHoje');

        // Máscara Mobile Nativa
        document.querySelectorAll('#input_valor_t, #valor, #valorTransacao').forEach(i => {
            i.setAttribute('inputmode', 'numeric');
            i.addEventListener('input', (e) => {
                let v = e.target.value.replace(/\D/g, "");
                if (v === "") return e.target.value = "";
                v = (parseInt(v) / 100).toFixed(2);
                let p = v.split("."); p[0] = p[0].replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                e.target.value = p.join(",");
            });
        });

        if (typeof window.dadosReal !== 'undefined') initCharts();
        if (window.pendenciasHoje > 0 && window.mP) window.mP.show();

        // Feedbacks via URL
        const params = new URLSearchParams(window.location.search);
        const s = params.get('success');
        if (s && window.mS) {
            let msg = "Operação realizada.";
            if(s==='transacao_excluida') msg = "Lançamento removido do fluxo.";
            if(s==='transacao_ok') msg = "Transação salva com sucesso!";
            if(s==='cat_ok') msg = "Categoria salva com sucesso!";
            if(s==='cat_excluida') msg = "Categoria removida do sistema.";
            if(s==='conta_ok') msg = "Dados da conta salvos.";
            document.getElementById('msgDescSucesso').innerText = msg;
            window.mS.show();
            window.history.replaceState({}, document.title, window.location.pathname + "?p=" + (params.get('p') || 'dashboard'));
        }
    });

    // --- FUNÇÕES DE CATEGORIAS ---
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
        document.getElementById('labelModalCat').innerText = "Nova Categoria Rápida";
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

    // --- FUNÇÕES DE TRANSAÇÕES ---
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

    // --- FUNÇÕES DE CONTAS ---
    window.abrirModalNovaConta = function() { if(window.mC) window.mC.show(); };
    window.prepararEdicao = function(id, nome, valor, status, tipo) {
        if(!window.mC) return;
        document.getElementById('id_conta').value = id;
        document.getElementById('nome').value = nome;
        document.getElementById('valor').value = valor.toString().replace('.', ',');
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

    function initCharts() {
        const ctxR = document.getElementById('chartRealizado'); const ctxF = document.getElementById('chartFuturo'); const ctxC = document.getElementById('chartCategorias');
        if(!ctxR || !ctxF) return;
        const opt = (sF) => ({ responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, onClick: (e, el) => { if (el.length > 0) { const t = el[0].index === 0 ? 'Receita' : 'Despesa'; window.location.href = `index.php?p=transacoes&f_tipo=${t}&f_status=${sF}&mes=${window.filtroAtual.mes}&ano=${window.filtroAtual.ano}`; } } });
        new Chart(ctxR.getContext('2d'), { type: 'bar', data: { labels: ['Ganhos', 'Gastos'], datasets: [{ data: [window.dadosReal.receita, window.dadosReal.despesa], backgroundColor: ['#2ecc71', '#e74c3c'], borderRadius: 10 }] }, options: opt('Pago') });
        new Chart(ctxF.getContext('2d'), { type: 'bar', data: { labels: ['A Receber', 'A Pagar'], datasets: [{ data: [window.dadosFuturo.receita, window.dadosFuturo.despesa], backgroundColor: ['#3498db', '#f1c40f'], borderRadius: 10 }] }, options: opt('Futuro') });
        if(ctxC && window.dadosCategorias.labels.length > 0) {
            new Chart(ctxC.getContext('2d'), { type: 'doughnut', data: { labels: window.dadosCategorias.labels, datasets: [{ data: window.dadosCategorias.values, backgroundColor: ['#1a73e8','#f1c40f','#e74c3c','#2ecc71','#9b59b6','#34495e'] }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } } } } });
        }
    }
    </script>
    <footer class="text-center py-4 mt-5 border-top bg-white no-print"><div class="container"><p class="mb-0 text-muted small"><strong>Minhas Economias</strong> - Tecnologia Cloud desenvolvido por <strong>BDSoftech</strong> &copy; 2026</p></div></footer>
</body>
</html>