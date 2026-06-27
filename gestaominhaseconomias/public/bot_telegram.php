<?php
/**
 * PROJETO: Gestão Minhas Economias - Versão Profissional Corrigida V2
 * CORREÇÃO: Variáveis padronizadas e lógica de consulta abrangente.
 */

// Desativar saída de erros para garantir a integridade da comunicação com o Telegram
ini_set('display_errors', 0);
error_reporting(E_ALL);

// --- CONFIGURAÇÕES DO TOKEN ---
define('TELEGRAM_TOKEN', '8501689130:AAHmwSQQr9M4gbiES64fYKj6BcyjNp0rZt0');

// --- CONEXÃO COM O BANCO DE DADOS ---
$servidor_banco_dados = "workspacebds.mysql.dbaas.com.br";
$usuario_banco_dados  = "workspacebds";
$senha_banco_dados    = "BDSoft@1020";
$nome_banco_dados     = "workspacebds";

$conexao_mysql = new mysqli($servidor_banco_dados, $usuario_banco_dados, $senha_banco_dados, $nome_banco_dados);

if ($conexao_mysql->connect_error) {
    exit; 
}

$conexao_mysql->set_charset("utf8mb4");

// --- RECEBIMENTO DOS DADOS DO TELEGRAM ---
$json_entrada = file_get_contents("php://input");
$atualizacao_recebida = json_decode($json_entrada, true);

if (!$atualizacao_recebida) {
    echo "<h1>✅ Servidor do Bot Financeiro Operacional</h1>";
    exit;
}

// Identificadores de Chat e Evento
$identificador_chat_telegram = $atualizacao_recebida["message"]["chat"]["id"] ?? $atualizacao_recebida["callback_query"]["message"]["chat"]["id"];
$identificador_evento_atual  = $atualizacao_recebida["message"]["message_id"] ?? $atualizacao_recebida["callback_query"]["id"];
$id_usuario_ativo_sistema    = 11;

// --- CONTROLE DE DUPLICIDADE ---
$query_verificar_trava = $conexao_mysql->query("SELECT ultima_mensagem_id FROM minhaseconomias_bot_estados WHERE chat_id = $identificador_chat_telegram");
$resultado_trava_mensagem = $query_verificar_trava->fetch_assoc();

if ($resultado_trava_mensagem && $resultado_trava_mensagem['ultima_mensagem_id'] == $identificador_evento_atual) {
    exit; 
}

$conexao_mysql->query("INSERT INTO minhaseconomias_bot_estados (chat_id, ultima_mensagem_id) VALUES ($identificador_chat_telegram, $identificador_evento_atual) ON DUPLICATE KEY UPDATE ultima_mensagem_id = $identificador_evento_atual");

$query_memoria_estado = $conexao_mysql->query("SELECT * FROM minhaseconomias_bot_estados WHERE chat_id = $identificador_chat_telegram");
$estado_usuario_atual = $query_memoria_estado->fetch_assoc();

// --- LÓGICA 1: CLIQUES EM BOTÕES (CALLBACK) ---
if (isset($atualizacao_recebida["callback_query"])) {
    $identificador_callback = $atualizacao_recebida["callback_query"]["id"];
    $dados_do_callback = explode('|', $atualizacao_recebida["callback_query"]["data"]);
    $comando_acao_bot = $dados_do_callback[0]; // Correção: variável padronizada
    $id_mensagem_com_teclado = $atualizacao_recebida["callback_query"]["message"]["message_id"];

    removerTecladoTelegram($identificador_chat_telegram, $id_mensagem_com_teclado);

    // --- FLUXO DE CONSULTA ---
    if ($comando_acao_bot == 'fluxo_consulta_iniciar') {
        $tipo_relatorio = $dados_do_callback[1];
        $conexao_mysql->query("UPDATE minhaseconomias_bot_estados SET tipo_consulta = '$tipo_relatorio', etapa = 'consulta_aguardando_conta' WHERE chat_id = $identificador_chat_telegram");
        
        $resultado_contas = $conexao_mysql->query("SELECT id, nome FROM minhaseconomias_contas WHERE usuario_id = $id_usuario_ativo_sistema AND status = 1");
        $teclado_contas = [];
        while ($conta = $resultado_contas->fetch_assoc()) {
            $teclado_contas[] = [["text" => "💳 " . $conta['nome'], "callback_data" => "consulta_selecionar_conta|" . $conta['id']]];
        }
        enviarMensagemTelegram($identificador_chat_telegram, "🔍 *Consulta: $tipo_relatorio*\nSelecione a conta bancária:", ["inline_keyboard" => $teclado_contas]);
    }

    if ($comando_acao_bot == 'consulta_selecionar_conta') {
        $id_conta_selecionada = $dados_do_callback[1];
        $conexao_mysql->query("UPDATE minhaseconomias_bot_estados SET conta_id = $id_conta_selecionada, etapa = 'consulta_aguardando_data_inicio' WHERE chat_id = $identificador_chat_telegram");
        enviarMensagemTelegram($identificador_chat_telegram, "📅 Informe a *Data de Início* (Ex: `01/06/2026`):");
    }

    // --- FLUXO DE LANÇAMENTO ---
    if ($comando_acao_bot == 'fluxo_lancamento_tipo') {
        $tipo_financeiro = $dados_do_callback[1];
        $conexao_mysql->query("UPDATE minhaseconomias_bot_estados SET tipo = '$tipo_financeiro', etapa = 'aguardando_data_transacao' WHERE chat_id = $identificador_chat_telegram");
        
        $teclado_datas = [
            [["text" => "📅 Hoje", "callback_data" => "fluxo_lancamento_data|" . date('Y-m-d')], ["text" => "⬅️ Ontem", "callback_data" => "fluxo_lancamento_data|" . date('Y-m-d', strtotime('-1 day'))]],
            [["text" => "⌨️ Digitar Data Manual", "callback_data" => "solicitar_entrada_data_manual"]]
        ];
        enviarMensagemTelegram($identificador_chat_telegram, "📅 *Data do Lançamento:*", ["inline_keyboard" => $teclado_datas]);
    }

    if ($comando_acao_bot == 'fluxo_lancamento_data') {
        $data_definida = $dados_do_callback[1];
        $conexao_mysql->query("UPDATE minhaseconomias_bot_estados SET data_vencimento = '$data_definida', etapa = 'aguardando_categoria_transacao' WHERE chat_id = $identificador_chat_telegram");
        
        $tipo_atual = $estado_usuario_atual['tipo'];
        $query_categorias = $conexao_mysql->query("SELECT id, nome FROM minhaseconomias_categorias WHERE usuario_id = $id_usuario_ativo_sistema AND (tipo = '$tipo_atual' OR tipo = 'AMBOS') ORDER BY nome ASC");
        
        $teclado_cats = [];
        while ($cat = $query_categorias->fetch_assoc()) {
            $teclado_cats[] = [["text" => "📂 " . $cat['nome'], "callback_data" => "fluxo_lancamento_categoria|" . $cat['id']]];
        }
        enviarMensagemTelegram($identificador_chat_telegram, "📂 *Selecione a Categoria:*", ["inline_keyboard" => $teclado_cats]);
    }

    if ($comando_acao_bot == 'fluxo_lancamento_categoria') {
        $conexao_mysql->query("UPDATE minhaseconomias_bot_estados SET categoria_id = {$dados_do_callback[1]} WHERE chat_id = $identificador_chat_telegram");
        $query_contas = $conexao_mysql->query("SELECT id, nome FROM minhaseconomias_contas WHERE usuario_id = $id_usuario_ativo_sistema AND status = 1");
        $teclado_ctas = [];
        while ($cta = $query_contas->fetch_assoc()) {
            $teclado_ctas[] = [["text" => "💳 " . $cta['nome'], "callback_data" => "fluxo_lancamento_conta|" . $cta['id']]];
        }
        enviarMensagemTelegram($identificador_chat_telegram, "🏦 *Selecione a Conta:*", ["inline_keyboard" => $teclado_ctas]);
    }

    if ($comando_acao_bot == 'fluxo_lancamento_conta') {
        $conexao_mysql->query("UPDATE minhaseconomias_bot_estados SET conta_id = {$dados_do_callback[1]} WHERE chat_id = $identificador_chat_telegram");
        $data_venc = $estado_usuario_atual['data_vencimento'];
        
        $teclado_st = [[["text" => "✅ Pago / Recebido", "callback_data" => "fluxo_lancamento_status|Pago"]]];
        if ($data_venc > date('Y-m-d')) {
            $teclado_st[] = [["text" => "⏳ Agendar (Futuro)", "callback_data" => "fluxo_lancamento_status|Futuro"]];
        } else if ($data_venc < date('Y-m-d')) {
            $teclado_st[] = [["text" => "⚠️ Atrasado", "callback_data" => "fluxo_lancamento_status|Atrasado"]];
        }
        enviarMensagemTelegram($identificador_chat_telegram, "⚙️ *Qual o Status do Lançamento?*", ["inline_keyboard" => $teclado_st]);
    }

    if ($comando_acao_bot == 'fluxo_lancamento_status') {
        $status_final = $dados_do_callback[1];
        $memoria = $conexao_mysql->query("SELECT * FROM minhaseconomias_bot_estados WHERE chat_id = $identificador_chat_telegram")->fetch_assoc();
        $data_pagto = ($status_final == 'Pago') ? $memoria['data_vencimento'] : null;

        $sql_ins = "INSERT INTO minhaseconomias_movimentacoes (usuario_id, conta_id, categoria_id, valor, descricao, data_vencimento, data_pagamento, status, tipo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conexao_mysql->prepare($sql_ins);
        $stmt->bind_param("iiissssss", $id_usuario_ativo_sistema, $memoria['conta_id'], $memoria['categoria_id'], $memoria['valor_temporario'], $memoria['descricao_temporaria'], $memoria['data_vencimento'], $data_pagto, $status_final, $memoria['tipo']);
        
        if ($stmt->execute()) {
            responderCliqueBotao($identificador_callback, "Concluído!");
            enviarMensagemTelegram($identificador_chat_telegram, "✅ *Lançamento Realizado!*\n💰 R$ " . number_format($memoria['valor_temporario'], 2, ',', '.') . "\n📝 " . $memoria['descricao_temporaria']);
            $conexao_mysql->query("UPDATE minhaseconomias_bot_estados SET etapa = NULL, valor_temporario = NULL, descricao_temporaria = NULL WHERE chat_id = $identificador_chat_telegram");
            enviarMenuPrincipalTelegram($identificador_chat_telegram);
        }
    }
    exit;
}

// --- LÓGICA 2: MENSAGENS DE TEXTO ---
if (isset($atualizacao_recebida["message"]["text"])) {
    $texto_usuario = mb_strtolower($atualizacao_recebida["message"]["text"], 'UTF-8');

    if ($texto_usuario == "/start" || $texto_usuario == "menu") {
        $conexao_mysql->query("DELETE FROM minhaseconomias_bot_estados WHERE chat_id = $identificador_chat_telegram");
        enviarMenuPrincipalTelegram($identificador_chat_telegram);
        exit;
    }

    if (strpos($texto_usuario, 'consulta') !== false || strpos($texto_usuario, 'ver') !== false) {
        $teclado_cons = [
            [["text" => "⚠️ Contas Atrasadas", "callback_data" => "fluxo_consulta_iniciar|Atrasadas"]],
            [["text" => "✅ Contas Pagas", "callback_data" => "fluxo_consulta_iniciar|Pagas"]],
            [["text" => "⏳ Receita Futura", "callback_data" => "fluxo_consulta_iniciar|Receita Futura"]],
            [["text" => "💰 Saldo Bancário", "callback_data" => "fluxo_consulta_iniciar|Saldo Real"]]
        ];
        enviarMensagemTelegram($identificador_chat_telegram, "📋 *O que você deseja consultar?*", ["inline_keyboard" => $teclado_cons]);
        exit;
    }

    if ($estado_usuario_atual) {
        if ($estado_usuario_atual['etapa'] == 'consulta_aguardando_data_inicio') {
            $data_inicio = formatarDataParaBanco($texto_usuario);
            if ($data_inicio) {
                $conexao_mysql->query("UPDATE minhaseconomias_bot_estados SET data_inicio = '$data_inicio', etapa = 'consulta_aguardando_data_fim' WHERE chat_id = $identificador_chat_telegram");
                enviarMensagemTelegram($identificador_chat_telegram, "🏁 Informe a *Data de Término* (Ex: `30/06/2026`):");
            } else { enviarMensagemTelegram($identificador_chat_telegram, "❌ Formato inválido. Use DD/MM/AAAA"); }
            exit;
        }

        if ($estado_usuario_atual['etapa'] == 'consulta_aguardando_data_fim') {
            $data_fim = formatarDataParaBanco($texto_usuario);
            if ($data_fim) {
                $tipo_rel = $estado_usuario_atual['tipo_consulta'];
                $id_conta = $estado_usuario_atual['conta_id'];
                $dt_ini = $estado_usuario_atual['data_inicio'];

                if ($tipo_rel == 'Saldo Real') {
                    $q_saldo = $conexao_mysql->query("SELECT conta_nome, saldo_atual FROM vw_minhaseconomias_saldo_atual WHERE conta_id = $id_conta");
                    $d_saldo = $q_saldo->fetch_assoc();
                    $relatorio = "🏦 *Saldo Real em " . $d_saldo['conta_nome'] . "*\n💰 R$ " . number_format($d_saldo['saldo_atual'], 2, ',', '.');
                } else {
                    if ($tipo_rel == 'Receita Futura') {
                        $f_status = "status IN ('Futuro', 'Atrasado')";
                        $f_tipo   = "tipo = 'RECEITA'";
                    } else if ($tipo_rel == 'Atrasadas') {
                        $f_status = "status = 'Atrasado'"; $f_tipo = "tipo = 'DESPESA'";
                    } else { 
                        $f_status = "status = 'Pago'"; $f_tipo = "tipo = 'DESPESA'";
                    }
                    
                    $sql_rel = "SELECT descricao, valor, data_vencimento FROM minhaseconomias_movimentacoes 
                                WHERE usuario_id = $id_usuario_ativo_sistema AND conta_id = $id_conta 
                                AND $f_status AND $f_tipo AND data_vencimento BETWEEN '$dt_ini' AND '$data_fim' ORDER BY data_vencimento ASC";
                    
                    $res_lista = $conexao_mysql->query($sql_rel);
                    $relatorio = "📊 *Relatório de $tipo_rel*\nPeríodo: " . date('d/m/y', strtotime($dt_ini)) . " a " . date('d/m/y', strtotime($data_fim)) . "\n\n";
                    
                    if ($res_lista->num_rows > 0) {
                        $soma = 0;
                        while ($mov = $res_lista->fetch_assoc()) {
                            $relatorio .= "• " . date('d/m', strtotime($mov['data_vencimento'])) . " - " . $mov['descricao'] . " (*R$ " . number_format($mov['valor'], 2, ',', '.') . "*)\n";
                            $soma += $mov['valor'];
                        }
                        $relatorio .= "\n🧮 *Total: R$ " . number_format($soma, 2, ',', '.') . "*";
                    } else { $relatorio .= "Nenhum registro encontrado. ✅"; }
                }

                enviarMensagemTelegram($identificador_chat_telegram, $relatorio);
                $conexao_mysql->query("DELETE FROM minhaseconomias_bot_estados WHERE chat_id = $identificador_chat_telegram");
                enviarMenuPrincipalTelegram($identificador_chat_telegram);
            }
            exit;
        }

        if ($estado_usuario_atual['etapa'] == 'aguardando_data_transacao') {
            $data_br = formatarDataParaBanco($texto_usuario);
            if ($data_br) {
                $conexao_mysql->query("UPDATE minhaseconomias_bot_estados SET data_vencimento = '$data_br', etapa = 'aguardando_categoria_transacao' WHERE chat_id = $identificador_chat_telegram");
                $t_at = $estado_usuario_atual['tipo'];
                $q_c = $conexao_mysql->query("SELECT id, nome FROM minhaseconomias_categorias WHERE usuario_id = $id_usuario_ativo_sistema AND (tipo = '$t_at' OR tipo = 'AMBOS') ORDER BY nome ASC");
                $tk_c = [];
                while ($c = $q_c->fetch_assoc()) { $tk_c[] = [["text" => "📂 " . $c['nome'], "callback_data" => "fluxo_lancamento_categoria|" . $c['id']]]; }
                enviarMensagemTelegram($identificador_chat_telegram, "📂 Selecione a Categoria:", ["inline_keyboard" => $tk_c]);
            } else { enviarMensagemTelegram($identificador_chat_telegram, "❌ Data inválida."); }
            exit;
        }
    }

    if (preg_match('/(\d+[\.,]\d{2})|(\d+)/', $texto_usuario, $correspondencias)) {
        $valor = str_replace(',', '.', $correspondencias[0]);
        $desc = trim(str_replace($correspondencias[0], '', $atualizacao_recebida["message"]["text"]));
        $desc = preg_replace('/^(lançar|registrar|novo|gastei|recebi)\s+/i', '', $desc);
        $desc_final = mb_substr($desc, 0, 250, 'UTF-8');

        $conexao_mysql->query("REPLACE INTO minhaseconomias_bot_estados (chat_id, etapa, valor_temporario, descricao_temporaria) VALUES ($identificador_chat_telegram, 'escolhendo_tipo', $valor, '$desc_final')");
        
        $tk_tipos = [[["text" => "🔴 Despesa", "callback_data" => "fluxo_lancamento_tipo|DESPESA"], ["text" => "🟢 Receita", "callback_data" => "fluxo_lancamento_tipo|RECEITA"]]];
        enviarMensagemTelegram($identificador_chat_telegram, "💰 *Lançamento:* R$ " . number_format($valor, 2, ',', '.') . "\n📝 $desc_final\n\nEste item é:", ["inline_keyboard" => $tk_tipos]);
        exit;
    }

    enviarMenuPrincipalTelegram($identificador_chat_telegram);
}

// --- FUNÇÕES ---

function enviarMenuPrincipalTelegram($id_chat) {
    $txt = "👋 *Assistente Financeiro*\n\n✍️ *Lançar:* `Descrição Valor`\n🔍 *Consultar:* Digite `Consulta` ";
    enviarMensagemTelegram($id_chat, $txt);
}

function enviarMensagemTelegram($id_chat, $texto, $teclado = null) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_TOKEN . "/sendMessage";
    $post = ['chat_id' => $id_chat, 'text' => $texto, 'parse_mode' => 'Markdown'];
    if ($teclado) $post['reply_markup'] = json_encode($teclado, JSON_UNESCAPED_UNICODE);
    $ch = curl_init($url); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post); curl_exec($ch); curl_close($ch);
}

function removerTecladoTelegram($id_chat, $id_msg) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_TOKEN . "/editMessageReplyMarkup";
    $post = ['chat_id' => $id_chat, 'message_id' => $id_msg, 'reply_markup' => json_encode(['inline_keyboard' => []])];
    $ch = curl_init($url); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post); curl_exec($ch); curl_close($ch);
}

function responderCliqueBotao($id, $txt) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_TOKEN . "/answerCallbackQuery";
    $post = ['callback_query_id' => $id, 'text' => $txt];
    $ch = curl_init($url); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post); curl_exec($ch); curl_close($ch);
}

function formatarDataParaBanco($dt) {
    $s = explode('/', $dt);
    return (count($s) == 3 && checkdate((int)$s[1], (int)$s[0], (int)$s[2])) ? "{$s[2]}-{$s[1]}-{$s[0]}" : false;
}