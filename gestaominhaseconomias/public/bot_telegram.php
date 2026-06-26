<?php
/**
 * PROJETO: Gestão Minhas Economias - Versão Sistema Web Completo
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

define('TELEGRAM_TOKEN', '8501689130:AAHmwSQQr9M4gbiES64fYKj6BcyjNp0rZt0');

$conexao = new mysqli("workspacebds.mysql.dbaas.com.br", "workspacebds", "BDSoft@1020", "workspacebds");
$conexao->set_charset("utf8mb4");

$input = file_get_contents("php://input");
$atualizacao = json_decode($input, true);
if (!$atualizacao) exit;

$id_chat = $atualizacao["message"]["chat"]["id"] ?? $atualizacao["callback_query"]["message"]["chat"]["id"];
$id_msg_atual = $atualizacao["message"]["message_id"] ?? $atualizacao["callback_query"]["id"];
$id_usuario = 11;

// --- TRAVA ANTI-DUPLICIDADE ---
$res_trava = $conexao->query("SELECT ultima_mensagem_id FROM minhaseconomias_bot_estados WHERE chat_id = $id_chat");
$trava = $res_trava->fetch_assoc();
if ($trava && $trava['ultima_mensagem_id'] == $id_msg_atual) exit;
$conexao->query("INSERT INTO minhaseconomias_bot_estados (chat_id, ultima_mensagem_id) VALUES ($id_chat, $id_msg_atual) ON DUPLICATE KEY UPDATE ultima_mensagem_id = $id_msg_atual");

$estado = $conexao->query("SELECT * FROM minhaseconomias_bot_estados WHERE chat_id = $id_chat")->fetch_assoc();

// --- LÓGICA 1: CLIQUES (BOTÕES) ---
if (isset($atualizacao["callback_query"])) {
    $callback_id = $atualizacao["callback_query"]["id"];
    $dados = explode('|', $atualizacao["callback_query"]["data"]);
    $acao = $dados[0];

    removerTeclado($id_chat, $atualizacao["callback_query"]["message"]["message_id"]);

    // Passo 1: Escolha do Tipo (Receita ou Despesa)
    if ($acao == 'set_tipo') {
        $tipo = $dados[1];
        $conexao->query("UPDATE minhaseconomias_bot_estados SET tipo = '$tipo', etapa = 'aguarda_data' WHERE chat_id = $id_chat");
        
        $b = [
            [["text" => "📅 Hoje", "callback_data" => "set_data|".date('Y-m-d')], ["text" => "⬅️ Ontem", "callback_data" => "set_data|".date('Y-m-d', strtotime('-1 day'))]],
            [["text" => "⌨️ Outra Data (Digitar)", "callback_data" => "pedir_data_manual"]]
        ];
        enviarMensagem($id_chat, "📅 *Data do Lançamento:*", ["inline_keyboard" => $b]);
    }

    // Passo 2: Escolha da Data
    if ($acao == 'set_data' || $acao == 'pedir_data_manual') {
        if ($acao == 'set_data') {
            $data = $dados[1];
            $conexao->query("UPDATE minhaseconomias_bot_estados SET data_vencimento = '$data', etapa = 'aguarda_cat' WHERE chat_id = $id_chat");
            
            // CARREGAR TODAS AS CATEGORIAS DO USUÁRIO E DO TIPO SELECIONADO
            $tipo_atual = $conexao->query("SELECT tipo FROM minhaseconomias_bot_estados WHERE chat_id = $id_chat")->fetch_assoc()['tipo'];
            $res_cat = $conexao->query("SELECT id, nome FROM minhaseconomias_categorias WHERE usuario_id = $id_usuario AND (tipo = '$tipo_atual' OR tipo = 'AMBOS') ORDER BY nome ASC");
            
            $b_cat = [];
            while($c = $res_cat->fetch_assoc()) {
                $b_cat[] = [["text" => "📂 ".$c['nome'], "callback_data" => "set_cat|".$c['id']]];
            }
            enviarMensagem($id_chat, "📂 *Selecione a Categoria:*", ["inline_keyboard" => $b_cat]);
        } else {
            enviarMensagem($id_chat, "⌨️ Digite a data no formato: `DD/MM/AAAA` (Ex: `25/06/2024`) ");
        }
    }

    // Passo 3: Escolha da Categoria -> Pedir Conta
    if ($acao == 'set_cat') {
        $conexao->query("UPDATE minhaseconomias_bot_estados SET categoria_id = {$dados[1]} WHERE chat_id = $id_chat");
        $res_contas = $conexao->query("SELECT id, nome FROM minhaseconomias_contas WHERE usuario_id = $id_usuario AND status = 1");
        $b_contas = [];
        while($ct = $res_contas->fetch_assoc()) { $b_contas[] = [["text" => "💳 ".$ct['nome'], "callback_data" => "set_conta|".$ct['id']]]; }
        enviarMensagem($id_chat, "🏦 *Selecione a Conta:*", ["inline_keyboard" => $b_contas]);
    }

    // Passo 4: Escolha da Conta -> Pedir Status
    if ($acao == 'set_conta') {
        $conexao->query("UPDATE minhaseconomias_bot_estados SET conta_id = {$dados[1]} WHERE chat_id = $id_chat");
        $data_sel = $conexao->query("SELECT data_vencimento FROM minhaseconomias_bot_estados WHERE chat_id = $id_chat")->fetch_assoc()['data_vencimento'];
        
        $b_status = [ [["text" => "✅ Pago", "callback_data" => "set_status|Pago"]] ];
        if ($data_sel > date('Y-m-d')) {
            $b_status[] = [["text" => "⏳ Futuro", "callback_data" => "set_status|Futuro"]];
        } else if ($data_sel < date('Y-m-d')) {
            $b_status[] = [["text" => "⚠️ Atrasado", "callback_data" => "set_status|Atrasado"]];
        }

        enviarMensagem($id_chat, "⚙️ *Qual o Status do Lançamento?*", ["inline_keyboard" => $b_status]);
    }

    // Passo Final: Salvar
    if ($acao == 'set_status') {
        $status = $dados[1];
        $e = $conexao->query("SELECT * FROM minhaseconomias_bot_estados WHERE chat_id = $id_chat")->fetch_assoc();
        $data_pg = ($status == 'Pago') ? $e['data_vencimento'] : null;

        $sql = "INSERT INTO minhaseconomias_movimentacoes (usuario_id, conta_id, categoria_id, valor, descricao, data_vencimento, data_pagamento, status, tipo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $cmd = $conexao->prepare($sql);
        $cmd->bind_param("iiissssss", $id_usuario, $e['conta_id'], $e['categoria_id'], $e['valor_temporario'], $e['descricao_temporaria'], $e['data_vencimento'], $data_pg, $status, $e['tipo']);
        
        if($cmd->execute()){
            enviarMensagem($id_chat, "✅ *Lançamento Realizado!*\n💰 R$ ".number_format($e['valor_temporario'],2,',','.')."\n📝 {$e['descricao_temporaria']}\n🏷️ {$e['tipo']} - $status");
            $conexao->query("UPDATE minhaseconomias_bot_estados SET etapa = NULL, valor_temporario = NULL, descricao_temporaria = NULL WHERE chat_id = $id_chat");
            enviarMenuPrincipal($id_chat);
        }
    }
    exit;
}

// --- LÓGICA 2: TEXTO ---
if (isset($atualizacao["message"]["text"])) {
    $texto_orig = $atualizacao["message"]["text"];
    $texto = mb_strtolower($texto_orig, 'UTF-8');

    if ($texto == "/start" || $texto == "menu") {
        $conexao->query("DELETE FROM minhaseconomias_bot_estados WHERE chat_id = $id_chat");
        enviarMenuPrincipal($id_chat); exit;
    }

    // Se estiver aguardando digitar data manual
    if ($estado && $estado['etapa'] == 'aguarda_data') {
        $data_formatada = formatarData($texto);
        if ($data_formatada) {
            // Segue para categorias (Mesma lógica do callback set_data)
            $conexao->query("UPDATE minhaseconomias_bot_estados SET data_vencimento = '$data_formatada', etapa = 'aguarda_cat' WHERE chat_id = $id_chat");
            $tipo_atual = $conexao->query("SELECT tipo FROM minhaseconomias_bot_estados WHERE chat_id = $id_chat")->fetch_assoc()['tipo'];
            $res_cat = $conexao->query("SELECT id, nome FROM minhaseconomias_categorias WHERE usuario_id = $id_usuario AND (tipo = '$tipo_atual' OR tipo = 'AMBOS') ORDER BY nome ASC");
            $b_cat = [];
            while($c = $res_cat->fetch_assoc()) { $b_cat[] = [["text" => "📂 ".$c['nome'], "callback_data" => "set_cat|".$c['id']]]; }
            enviarMensagem($id_chat, "📂 *Data: $texto_orig*\nSelecione a Categoria:", ["inline_keyboard" => $b_cat]);
        } else {
            enviarMensagem($id_chat, "❌ Data inválida. Use DD/MM/AAAA");
        }
        exit;
    }

    // DETECÇÃO DE VALOR + DESCRIÇÃO (Início do Fluxo)
    if (preg_match('/(\d+[\.,]\d{2})|(\d+)/', $texto, $m)) {
        $val = str_replace(',', '.', $m[0]);
        $desc = trim(str_replace($m[0], '', $texto_orig));
        $desc = preg_replace('/^(lançar|registrar|novo|gastei|recebi)\s+/i', '', $desc);
        $desc_final = mb_substr($desc, 0, 250, 'UTF-8');

        $conexao->query("REPLACE INTO minhaseconomias_bot_estados (chat_id, etapa, valor_temporario, descricao_temporaria) VALUES ($id_chat, 'escolhe_tipo', $val, '$desc_final')");
        
        $b_tipo = [[["text" => "🔴 Despesa", "callback_data" => "set_tipo|DESPESA"], ["text" => "🟢 Receita", "callback_data" => "set_tipo|RECEITA"]]];
        enviarMensagem($id_chat, "💰 *Lançamento:* R$ ".number_format($val,2,',','.')."\n📝 $desc_final\n\nEste lançamento é:", ["inline_keyboard" => $b_tipo]);
        exit;
    }

    enviarMenuPrincipal($id_chat);
}

// --- FUNÇÕES ---
function enviarMenuPrincipal($id) {
    $m = "👋 *Gestão Minhas Economias*\n✍️ Digite `Descrição Valor` para começar.";
    enviarMensagem($id, $m);
}
function enviarMensagem($id, $txt, $kb = null) {
    $url = "https://api.telegram.org/bot".TELEGRAM_TOKEN."/sendMessage";
    $p = ['chat_id' => $id, 'text' => $txt, 'parse_mode' => 'Markdown'];
    if ($kb) $p['reply_markup'] = json_encode($kb, JSON_UNESCAPED_UNICODE);
    $ch = curl_init($url); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $p); curl_exec($ch); curl_close($ch);
}
function removerTeclado($chat, $msg) {
    $url = "https://api.telegram.org/bot".TELEGRAM_TOKEN."/editMessageReplyMarkup";
    $p = ['chat_id' => $chat, 'message_id' => $msg, 'reply_markup' => json_encode(['inline_keyboard' => []])];
    $ch = curl_init($url); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $p); curl_exec($ch); curl_close($ch);
}
function formatarData($d) {
    $p = explode('/', $d);
    return (count($p) == 3 && checkdate($p[1], $p[0], $p[2])) ? "{$p[2]}-{$p[1]}-{$p[0]}" : false;
}