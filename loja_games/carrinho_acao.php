<?php
session_start();
require_once 'admin/config/conexao.php';

// Só aceita envio pelos botões do carrinho (POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: carrinho.php');
    exit;
}

$acao = $_POST['acao'] ?? '';
$id = (int)($_POST['id'] ?? 0);
$qtd = (int)($_POST['qtd'] ?? 1);

if (!isset($_SESSION['carrinho']) || !is_array($_SESSION['carrinho'])) {
    $_SESSION['carrinho'] = [];
}

// Estoque atual do jogo (null se o jogo não existir)
function estoque_do_jogo($pdo, $id) {
    $stmt = $pdo->prepare("SELECT estoque FROM games WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $estoque = $stmt->fetchColumn();

    return $estoque === false ? null : (int)$estoque;
}

if ($acao === 'adicionar') {

    $estoque = estoque_do_jogo($pdo, $id);

    if ($estoque === null || $estoque <= 0) {
        header('Location: jogo.php?id=' . $id . '&msg=esgotado');
        exit;
    }

    $qtd = max(1, $qtd);
    $atual = $_SESSION['carrinho'][$id] ?? 0;
    $novo = min($atual + $qtd, $estoque);

    $_SESSION['carrinho'][$id] = $novo;

    $msg = ($novo < $atual + $qtd) ? 'carrinho_limite' : 'carrinho';
    header('Location: jogo.php?id=' . $id . '&msg=' . $msg);
    exit;
}

if ($acao === 'atualizar' && isset($_SESSION['carrinho'][$id])) {

    $estoque = estoque_do_jogo($pdo, $id);

    if ($estoque === null || $qtd <= 0) {
        unset($_SESSION['carrinho'][$id]);
        header('Location: carrinho.php');
        exit;
    }

    if ($qtd > $estoque) {
        $_SESSION['carrinho'][$id] = $estoque;
        header('Location: carrinho.php?msg=limite');
        exit;
    }

    $_SESSION['carrinho'][$id] = $qtd;
    header('Location: carrinho.php');
    exit;
}

if ($acao === 'remover') {
    unset($_SESSION['carrinho'][$id]);
}

if ($acao === 'limpar') {
    $_SESSION['carrinho'] = [];
}

header('Location: carrinho.php');
exit;
