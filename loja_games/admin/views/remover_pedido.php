<?php
session_start();

// Só administradores logados podem remover pedidos
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

// Só aceita envio pelo botão Remover (POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ver_pedidos.php');
    exit;
}

require_once __DIR__ . '/../config/conexao.php';

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    header('Location: ver_pedidos.php');
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT status, quantidade, game_id FROM pedidos WHERE id = :id FOR UPDATE");
    $stmt->execute([':id' => $id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        $pdo->rollBack();
        header('Location: ver_pedidos.php');
        exit;
    }

    // Pedido que ainda estava valendo devolve as unidades ao estoque
    if ($pedido['status'] !== 'cancelado') {
        $pdo->prepare("UPDATE games SET estoque = estoque + :q WHERE id = :g")
            ->execute([':q' => $pedido['quantidade'], ':g' => $pedido['game_id']]);
    }

    $pdo->prepare("DELETE FROM pedidos WHERE id = :id")->execute([':id' => $id]);

    $pdo->commit();

    header('Location: ver_pedidos.php?msg=removido');
    exit;

} catch (PDOException $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: ver_pedidos.php?msg=erro');
    exit;
}
