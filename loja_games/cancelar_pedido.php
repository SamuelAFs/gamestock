<?php
session_start();
require_once 'admin/config/conexao.php';

// Só aceita envio pelo botão Cancelar (POST) e exige login
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$usuarioId = (int)$_SESSION['usuario_id'];

try {
    $pdo->beginTransaction();

    // Só pedidos do próprio usuário
    $stmt = $pdo->prepare("SELECT status, quantidade, game_id FROM pedidos WHERE id = :id AND usuario_id = :u FOR UPDATE");
    $stmt->execute([':id' => $id, ':u' => $usuarioId]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        $pdo->rollBack();
        header('Location: meus_pedidos.php');
        exit;
    }

    if ($pedido['status'] !== 'pendente') {
        $pdo->rollBack();
        header('Location: meus_pedidos.php?msg=nao_pode');
        exit;
    }

    // Devolve as unidades ao estoque e marca como cancelado
    $pdo->prepare("UPDATE games SET estoque = estoque + :q WHERE id = :g")
        ->execute([':q' => $pedido['quantidade'], ':g' => $pedido['game_id']]);

    $pdo->prepare("UPDATE pedidos SET status = 'cancelado' WHERE id = :id")
        ->execute([':id' => $id]);

    $pdo->commit();

    header('Location: meus_pedidos.php?msg=cancelado');
    exit;

} catch (PDOException $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: meus_pedidos.php?msg=erro');
    exit;
}
