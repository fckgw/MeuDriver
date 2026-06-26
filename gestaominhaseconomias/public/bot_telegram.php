<?php
/**
 * PROJETO: Gestão Minhas Economias - Versão Anti-Duplicidade e Alta Performance
 */

// Desativar saída de erros para evitar quebras no JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

define('TELEGRAM_TOKEN', '8501689130:AAHmwSQQr9M4gbiES64fYKj6BcyjNp0rZt0');

// CONEXÃO COM O BANCO
$servidor = "workspacebds.mysql.dbaas.com.br";
$usuario  = "workspacebds";
$senha    = "BDSoft@1020";
$banco    = "workspacebds";

$conexao = new mysqli($servidor, $usuario, $senha, $banco);
if ($conexao->connect_error) exit;

$conexao->set_charset("utf8mb4");

// RECEBIMENTO DOS DADOS
$input_telegram = file_get_contents("php://input");
$atualizacao = json_decode($input_telegram, true);

if (!$atualizacao) {
    echo "✅ Servidor Online.";
    exit;
}

// Identificadores
$id_chat = $atualizacao["message"]["chat"]["id"] ?? $atualizacao["callback_query"]["message"]["chat"]["id"];
$id_mensagem_atual = $atualizacao["message"]["message_id"] ?? $atualizacao["callback_query"]["id"];
$id_usuario_sistema = 11;

// --- TRAVA ANTI-DUPLICIDADE (DEDUPLICAÇÃO) ---
$query_trava = $conexao->query("SELECT ultima_mensagem_id FROM minhaseconomias_bot_estados WHERE chat_id = $id_chat");
$trava = $query_trava->fetch_assoc();

if ($trava && $trava['ultima_mensagem_id'] == $id_mensagem_atual) {
    // Se o ID da mensagem recebida for igual ao último processado, encerra aqui.
    exit;
}
// Atualiza imediatamente o ID da mensagem para travar reenvios do Telegram
$conexao->query("INSERT INTO minhaseconomias_bot_estados (chat_id, ultima_mensagem_id) VALUES ($id_chat, $id_mensagem_atual) ON DUPLICATE KEY UPDATE ultima_mensagem_id = $id_mensagem_atual");

// --- BUSCAR ESTADO COMPLETO ---
$query_estado = $conexao->query("SELECT * FROM minhaseconomias_bot_estados WHERE chat_id = $id_chat");
$estado_atual = $query_estado->fetch_assoc();

// --- LÓGICA 1: CLIQUES EM BOTÕES ---
if (isset($atualizacao["callback_query"])) {
    $id_callback = $atualizacao["callback_query"]["id"];
    $dados = explode('|', $atualizacao["callback_query"]["data"]);
    $acao = $dados[0];
    $id_msg_teclado = $atualizacao["callback_query"]["message"]["message_id"];

    // Remove botões para evitar cliques múltiplos
    removerTecladoTelegram($id_chat, $id_msg_teclado);

    if ($acao == 'sel_cat') {
        $id_cat = $dados[1];
        $conexao->query("UPDATE minhaseconomias_bot_estados SET categoria_id = $id_cat WHERE chat_id = $id_chat");
        
        $res_contas = $conexao->query("SELECT id, nome FROM minhaseconomias_contas WHERE usuario_id = $id_usuario_sistema AND status = 1");
        $botoes = [];
        while ($c = $res_contas->fetch_assoc()) {
            $botoes[] = [["text" => "💳 " . $c['nome'], "callback_data" => "save_final|" . $c['id']]];
        }
        responderClique($id_callback, "Categoria OK");
        enviarMensagem($id_chat, "🏦 *Lançamento:* De qual conta saiu o valor?", ["inline_keyboard" => $botoes]);
    }

    if ($acao == 'save_final') {
        $id_conta = $dados[1];
        $val = $estado_atual['valor_temporario'];
        $des = $estado_atual['descricao_temporaria'];
        $cat = $estado_atual['categoria_id'];
        $hoje = date('Y-m-d');

        $sql = "INSERT INTO minhaseconomias_movimentacoes (usuario_id, conta_id, categoria_id, valor, descricao, data_vencimento, data_pagamento, status, tipo) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pago', 'DESPESA')";
        $stmt = $conexao->prepare($sql);
        $stmt->bind_param("iiissss", $id_usuario_sistema, $id_conta, $cat, $val, $des, $hoje, $hoje);
        
        if ($stmt->execute()) {
            responderClique($id_callback, "Sucesso!");
            enviarMensagem($id_chat, "✅ *Gasto Registrado!*\n💰 R$ " . number_format($val, 2, ',', '.') . "\n📝 $des");
            $conexao->query("UPDATE minhaseconomias_bot_estados SET etapa = NULL, valor_temporario = NULL, descricao_temporaria = NULL, categoria_id = NULL WHERE chat_id = $id_chat");
            enviarMenuPrincipal($id_chat);
        }
    }
    
    if ($acao == 'ver_saldo') {
        $id_c = $dados[1];
        $q = $conexao->query("SELECT conta_nome, saldo_atual FROM vw_minhaseconomias_saldo_atual WHERE conta_id = $id_c");
        $d = $q->fetch_assoc();
        enviarMensagem($id_chat, "🏦 *Conta:* {$d['conta_nome']}\n💰 *Saldo:* R$ " . number_format($d['saldo_atual'], 2, ',', '.'));
        enviarMenuPrincipal($id_chat);
    }
    exit;
}

// --- LÓGICA 2: MENSAGENS DE TEXTO ---
if (isset($atualizacao["message"]["text"])) {
    $texto_original = $atualizacao["message"]["text"];
    $texto_limpo = mb_strtolower($texto_original, 'UTF-8');

    if ($texto_limpo == "/start" || $texto_limpo == "menu") {
        enviarMenuPrincipal($id_chat);
        exit;
    }

    // Saldo
    if (strpos($texto_limpo, 'saldo') !== false) {
        $res = $conexao->query("SELECT conta_id, conta_nome FROM vw_minhaseconomias_saldo_atual WHERE usuario_id = $id_usuario_sistema");
        $b = [];
        while ($c = $res->fetch_assoc()) {
            $b[] = [["text" => "💳 " . $c['conta_nome'], "callback_data" => "ver_saldo|" . $c['conta_id']]];
        }
        enviarMensagem($id_chat, "🔍 Selecione a conta para o saldo:", ["inline_keyboard" => $b]);
        exit;
    }

    // LANÇAMENTO
    if (preg_match('/(\d+[\.,]\d{2})|(\d+)/', $texto_original, $matches)) {
        $valor = str_replace(',', '.', $matches[0]);
        $descricao = trim(str_replace($matches[0], '', $texto_original));
        $descricao = preg_replace('/^(lançar|registrar|gravar|novo|paguei|recebi|gastei)\s+/i', '', $descricao);
        if (empty($descricao)) $descricao = "Gasto via Bot";
        $descricao_final = mb_substr($descricao, 0, 250, 'UTF-8');

        // Salva estado COM a trava do ID da mensagem atual
        $stmt_e = $conexao->prepare("UPDATE minhaseconomias_bot_estados SET etapa = 'cat', valor_temporario = ?, descricao_temporaria = ?, ultima_mensagem_id = ? WHERE chat_id = ?");
        $stmt_e->bind_param("ssii", $valor, $descricao_final, $id_mensagem_atual, $id_chat);
        
        if ($stmt_e->execute()) {
            $res_cat = $conexao->query("SELECT id, nome FROM minhaseconomias_categorias WHERE usuario_id = $id_usuario_sistema ORDER BY nome ASC LIMIT 15");
            $botoes = [];
            while ($cat = $res_cat->fetch_assoc()) {
                $botoes[] = [["text" => "📂 " . $cat['nome'], "callback_data" => "sel_cat|" . $cat['id']]];
            }
            $confirmacao = "💰 *Lançamento:* R$ " . number_format($valor, 2, ',', '.') . "\n📝 $descricao_final\n\n*Selecione a Categoria:*";
            enviarMensagem($id_chat, $confirmacao, ["inline_keyboard" => $botoes]);
        }
        exit;
    }

    enviarMenuPrincipal($id_chat);
}

// --- FUNÇÕES ---

function enviarMenuPrincipal($id) {
    $m = "👋 *Gestão Minhas Economias*\n\n✍️ `Descrição Valor` (ex: `Jantar 137,54`)\n🏦 `Saldo` | ⚙️ `Menu` ";
    enviarMensagem($id, $m);
}

function enviarMensagem($id, $txt, $kb = null) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_TOKEN . "/sendMessage";
    $p = ['chat_id' => $id, 'text' => $txt, 'parse_mode' => 'Markdown'];
    if ($kb) $p['reply_markup'] = json_encode($kb, JSON_UNESCAPED_UNICODE);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Timeout para evitar que o script pendure
    curl_setopt($ch, CURLOPT_POSTFIELDS, $p);
    curl_exec($ch);
    curl_close($ch);
}

function removerTecladoTelegram($id_chat, $id_msg) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_TOKEN . "/editMessageReplyMarkup";
    $p = ['chat_id' => $id_chat, 'message_id' => $id_msg, 'reply_markup' => json_encode(['inline_keyboard' => []])];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $p);
    curl_exec($ch);
    curl_close($ch);
}

function responderClique($id, $txt) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_TOKEN . "/answerCallbackQuery";
    $p = ['callback_query_id' => $id, 'text' => $txt];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $p);
    curl_exec($ch);
    curl_close($ch);
}