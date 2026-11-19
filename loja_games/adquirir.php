<?php
session_start();
require_once 'admin/config/conexao.php';

// Só aceita envio pelo formulário
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Precisa estar logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$gameId = (int)($_POST['id'] ?? 0);
$usuarioId = (int)$_SESSION['usuario_id'];

try {
    $pdo->beginTransaction();

    // Trava a linha do jogo para evitar duas compras ao mesmo tempo
    $stmt = $pdo->prepare("SELECT id, preco, estoque FROM games WHERE id = :id FOR UPDATE");
    $stmt->execute([':id' => $gameId]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$game) {
        $pdo->rollBack();
        header('Location: index.php');
        exit;
    }

    if ($game['estoque'] <= 0) {
        $pdo->rollBack();
        header('Location: jogo.php?id=' . $gameId . '&msg=esgotado');
        exit;
    }

    // Baixa uma unidade do estoque
    $pdo->prepare("UPDATE games SET estoque = estoque - 1 WHERE id = :id")
        ->execute([':id' => $gameId]);

    // Registra o pedido
    $pdo->prepare("INSERT INTO pedidos (usuario_id, game_id, quantidade, preco_unitario) VALUES (:u, :g, 1, :p)")
        ->execute([':u' => $usuarioId, ':g' => $gameId, ':p' => $game['preco']]);

    $pdo->commit();

    header('Location: jogo.php?id=' . $gameId . '&msg=ok');
    exit;

} catch (Exception $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: jogo.php?id=' . $gameId . '&msg=erro');
    exit;
}
