<?php
/**
 * BDSoft Workspace - AGRO CAMPO (MOTOR DE PROCESSAMENTO CENTRAL)
 * Localização: public_html/agrocampo/acoes.php
 */

// 1. Configurações de Depuração para ambiente de produção Locaweb
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. Aumentar limites para processamento de arquivos XML e execução
set_time_limit(1200); 
ini_set('memory_limit', '512M');

session_start();

// 3. Importar conexão com o banco de dados (Sobe um nível para a raiz)
if (!file_exists('../config.php')) {
    die("Erro Crítico: Arquivo de configuração não encontrado na raiz do sistema.");
}
require_once '../config.php';

// 4. Verificação de Segurança de Sessão
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    exit("Erro: Sua sessão expirou. Por favor, realize o login novamente.");
}

$usuario_id_logado = $_SESSION['usuario_id'];
$nivel_usuario_logado = $_SESSION['usuario_nivel'];

try {

    /**
     * -------------------------------------------------------------------------
     * --- SEÇÃO: GESTÃO DE USUÁRIOS E PERMISSÕES (ADMINISTRATIVO) ---
     * -------------------------------------------------------------------------
     */

    // Ação: Criar Novo Usuário diretamente pelo módulo Agro
    if (isset($_POST['acao']) && $_POST['acao'] === 'novo_usuario_agro') {
        if ($nivel_usuario_logado !== 'admin') { die("Acesso Negado."); }

        $nome_novo    = trim($_POST['nome']);
        $cpf_novo     = trim($_POST['cpf']);
        $usuario_novo = trim($_POST['usuario']);
        $senha_nova   = password_hash($_POST['senha'], PASSWORD_DEFAULT);
        $submodulos   = $_POST['submodulos'] ?? []; // Array de IDs de submódulos

        $pdo->beginTransaction();

        try {
            // 1. Inserir o usuário na tabela global de usuários
            $sql_user = "INSERT INTO usuarios (nome, cpf, usuario, senha, data_criacao, nivel, status, quota_limite) 
                         VALUES (?, ?, ?, ?, NOW(), 'usuario', 'ativo', 1073741824)";
            $stmt_user = $pdo->prepare($sql_user);
            $stmt_user->execute([$nome_novo, $cpf_novo, $usuario_novo, $senha_nova]);
            $novo_usuario_id = $pdo->lastInsertId();

            // 2. Vincular o usuário ao módulo principal AgroCampo na tabela global de módulos
            $stmt_mod_global = $pdo->prepare("INSERT INTO usuarios_modulos (usuario_id, modulo_id) 
                                              SELECT ?, id FROM modulos WHERE nome = 'AgroCampo' LIMIT 1");
            $stmt_mod_global->execute([$novo_usuario_id]);

            // 3. Vincular o usuário aos submódulos específicos do Agro (Financeiro, Ordenha, etc)
            if (!empty($submodulos)) {
                $sql_perm = "INSERT INTO usuarios_agro_permissões (usuario_id, submodulo_id) VALUES (?, ?)";
                $stmt_perm = $pdo->prepare($sql_perm);
                foreach ($submodulos as $id_sub) {
                    $stmt_perm->execute([$novo_usuario_id, $id_sub]);
                }
            }

            $pdo->commit();
            registrarLog($pdo, $usuario_id_logado, "Admin", "Cadastrou novo usuário Agro: $usuario_novo");
            header("Location: admin_permissoes.php?sucesso=1");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            die("Erro ao cadastrar usuário: " . $e->getMessage());
        }
    }

    // Ação: Salvar Permissões de Submódulos para Usuário Existente
    if (isset($_POST['acao']) && $_POST['acao'] === 'salvar_permissoes_agro') {
        if ($nivel_usuario_logado !== 'admin') { die("Acesso Negado."); }

        $id_usuario_alvo = (int)$_POST['usuario_id'];
        $submodulos_escolhidos = $_POST['submodulos'] ?? [];

        $pdo->beginTransaction();
        
        // Limpar permissões antigas deste usuário no módulo Agro
        $pdo->prepare("DELETE FROM usuarios_agro_permissões WHERE usuario_id = ?")->execute([$id_usuario_alvo]);

        // Inserir novas permissões marcadas
        if (!empty($submodulos_escolhidos)) {
            $stmt_ins_perm = $pdo->prepare("INSERT INTO usuarios_agro_permissões (usuario_id, submodulo_id) VALUES (?, ?)");
            foreach ($submodulos_escolhidos as $id_submodulo) {
                $stmt_ins_perm->execute([$id_usuario_alvo, $id_submodulo]);
            }
        }

        $pdo->commit();
        registrarLog($pdo, $usuario_id_logado, "Admin", "Alterou permissões Agro do usuário ID: $id_usuario_alvo");
        header("Location: admin_permissoes.php?uid=$id_usuario_alvo&sucesso=1");
        exit;
    }


    /**
     * -------------------------------------------------------------------------
     * --- SEÇÃO: GESTÃO FINANCEIRA (CONTAS PAGAR / RECEBER / XML) ---
     * -------------------------------------------------------------------------
     */

    // Ação: Novo Lançamento Financeiro Manual
    if (isset($_POST['acao']) && $_POST['acao'] === 'novo_fin') {
        $tipo        = $_POST['tipo']; 
        $valor       = (float)$_POST['valor'];
        $fornecedor  = trim($_POST['fornecedor']);
        $descricao   = trim($_POST['descricao']);
        $vencimento  = $_POST['data_vencimento'];
        $metodo      = $_POST['metodo_pagamento'];
        $status      = $_POST['status'];

        $data_pagamento_real = ($status === 'Pago') ? date('Y-m-d') : null;

        $sql_financeiro = "INSERT INTO agro_financeiro (tipo, descricao, fornecedor, valor, data_vencimento, data_pagamento, status, metodo_pagamento, usuario_id) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt_fin = $pdo->prepare($sql_financeiro);
        $stmt_fin->execute([$tipo, $descricao, $fornecedor, $valor, $vencimento, $data_pagamento_real, $status, $metodo, $usuario_id_logado]);
        
        header("Location: financeiro.php");
        exit;
    }

    // Ação: Confirmar Pagamento (Baixa com data real)
    if (isset($_GET['acao']) && $_GET['acao'] === 'confirmar_pagamento') {
        $id_conta = (int)$_GET['id'];
        $data_recebida = $_GET['data']; // Espera DD/MM/AAAA do prompt JS

        $partes = explode('/', $data_recebida);
        if (count($partes) === 3) {
            $data_sql = $partes[2] . '-' . $partes[1] . '-' . $partes[0];

            $stmt_baixa = $pdo->prepare("UPDATE agro_financeiro SET status = 'Pago', data_pagamento = ? WHERE id = ? AND usuario_id = ?");
            $stmt_baixa->execute([$data_sql, $id_conta, $usuario_id_logado]);
        }

        header("Location: financeiro.php");
        exit;
    }

    // Ação: Estornar Pagamento (Voltar para Pendente)
    if (isset($_GET['acao']) && $_GET['acao'] === 'estornar_pagamento') {
        $id_estorno = (int)$_GET['id'];
        $pdo->prepare("UPDATE agro_financeiro SET status = 'Pendente', data_pagamento = NULL WHERE id = ? AND usuario_id = ?")
            ->execute([$id_estorno, $usuario_id_logado]);

        header("Location: financeiro.php");
        exit;
    }

    // Ação: Ler XML da Comevap (AJAX)
    if (isset($_POST['acao']) && $_POST['acao'] === 'ler_xml_agro') {
        if (!isset($_FILES['xml_file'])) {
            echo json_encode(['status' => 'error', 'message' => 'Nenhum arquivo enviado.']);
            exit;
        }

        $xml_texto = file_get_contents($_FILES['xml_file']['tmp_name']);
        $xml_texto = str_replace(['xmlns=', 'xmlns:'], ['ns=', 'ns:'], $xml_texto); // Limpeza de namespaces para SAT/CFe
        $xml_objeto = simplexml_load_string($xml_texto);

        if ($xml_objeto && isset($xml_objeto->infCFe)) {
            $info_cfe = $xml_objeto->infCFe;
            echo json_encode([
                'status' => 'success',
                'dados'  => [
                    'emitente' => (string)$info_cfe->emit->xNome,
                    'valor' => number_format((float)$info_cfe->total->ICMSTot->vCFe, 2, ',', '.'),
                    'valor_limpo' => (float)$info_cfe->total->ICMSTot->vCFe,
                    'produto' => (string)$info_cfe->det[0]->prod->xProd
                ]
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'XML inválido ou formato incompatível.']);
        }
        exit;
    }

    // Ação: Efetivar Lançamento XML Confirmado
    if (isset($_POST['acao']) && $_POST['acao'] === 'confirmar_xml_agro') {
        $valor_xml = $_POST['valor'];
        $desc_xml  = $_POST['descricao'];
        $venc_xml  = $_POST['vencimento'];

        $sql_xml_ins = "INSERT INTO agro_financeiro (tipo, descricao, fornecedor, valor, categoria, data_vencimento, status, metodo_pagamento, usuario_id) 
                        VALUES ('Saida', ?, 'COMEVAP - COOPERATIVA', ?, 'Consignado', ?, 'Pendente', 'Consignado', ?)";
        $pdo->prepare($sql_xml_ins)->execute([$desc_xml, $valor_xml, $venc_xml, $usuario_id_logado]);
        
        echo "Sucesso";
        exit;
    }

    // Ação: Excluir Lançamento Financeiro
    if (isset($_GET['del_fin'])) {
        $pdo->prepare("DELETE FROM agro_financeiro WHERE id = ? AND usuario_id = ?")
            ->execute([(int)$_GET['del_fin'], $usuario_id_logado]);
        header("Location: financeiro.php");
        exit;
    }


    /**
     * -------------------------------------------------------------------------
     * --- SEÇÃO: MONITORAMENTO DE CAMPO (TALHÕES) ---
     * -------------------------------------------------------------------------
     */

    if (isset($_POST['acao']) && $_POST['acao'] === 'novo_talhao') {
        $stmt_t = $pdo->prepare("INSERT INTO agro_talhoes (nome, area_ha, cultura_atual, usuario_id) VALUES (?, ?, ?, ?)");
        $stmt_t->execute([trim($_POST['nome']), $_POST['area_ha'], trim($_POST['cultura']), $usuario_id_logado]);
        header("Location: index.php");
        exit;
    }

    if (isset($_GET['del_talhao'])) {
        $pdo->prepare("DELETE FROM agro_talhoes WHERE id = ? AND usuario_id = ?")
            ->execute([(int)$_GET['del_talhao'], $usuario_id_logado]);
        header("Location: index.php");
        exit;
    }


    /**
     * -------------------------------------------------------------------------
     * --- SEÇÃO: ORDENHA PRÁTICA (PECUÁRIA) ---
     * -------------------------------------------------------------------------
     */

    // Ação: Cadastrar Nova Vaca (Animal)
    if (isset($_POST['acao']) && $_POST['acao'] === 'novo_animal') {
        $pdo->prepare("INSERT INTO agro_animais (brinco, nome, usuario_id, status) VALUES (?, ?, ?, 'Lactação')")
            ->execute([trim($_POST['brinco']), trim($_POST['nome']), $usuario_id_logado]);
        header("Location: ordenha.php");
        exit;
    }

    // Ação: Lançar Litragem de Leite
    if (isset($_POST['acao']) && $_POST['acao'] === 'lancar_ordenha') {
        $sql_leite = "INSERT INTO agro_ordenhas (animal_id, litros, periodo, data_registro, usuario_id) VALUES (?, ?, ?, ?, ?)";
        $pdo->prepare($sql_leite)->execute([
            (int)$_POST['animal_id'], (float)$_POST['litros'], $_POST['periodo'], $_POST['data_registro'], $usuario_id_logado
        ]);
        header("Location: ordenha.php");
        exit;
    }

    // Ação: Excluir Animal
    if (isset($_GET['del_animal'])) {
        $pdo->prepare("DELETE FROM agro_animais WHERE id = ? AND usuario_id = ?")
            ->execute([(int)$_GET['del_animal'], $usuario_id_logado]);
        header("Location: ordenha.php");
        exit;
    }

} catch (Exception $erro_fatal) {
    // Tratamento de Erro para evitar tela branca na Locaweb
    http_response_code(500);
    echo "<div style='padding:30px; background:#fff0f0; border:2px solid red; font-family:sans-serif;'>";
    echo "<h3 style='color:red;'>❌ Erro no Motor de Ações Agro</h3>";
    echo "<p><strong>Mensagem:</strong> " . $erro_fatal->getMessage() . "</p>";
    echo "<p><strong>Linha:</strong> " . $erro_fatal->getLine() . "</p>";
    echo "<br><a href='index.php'>Voltar para o Painel</a>";
    echo "</div>";
}