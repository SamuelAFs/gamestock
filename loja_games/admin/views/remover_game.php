<?php
session_start();

// Só administradores logados podem remover jogos
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

// Só aceita envio pelo botão Remover (POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ver_games.php');
    exit;
}

require_once __DIR__ . '/../config/conexao.php';

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    header('Location: ver_games.php');
    exit;
}

// Jogos que já foram vendidos não podem ser apagados (preserva o histórico de pedidos)
$temPedidos = false;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE game_id = :id");
    $stmt->execute([':id' => $id]);
    $temPedidos = $stmt->fetchColumn() > 0;
} catch (PDOException $ex) {
    // Tabela pedidos ainda não existe: não há vendas para proteger
}

if ($temPedidos) {
    header('Location: ver_games.php?msg=com_pedidos');
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM games WHERE id = :id");
    $stmt->execute([':id' => $id]);

    header('Location: ver_games.php?msg=removido');
    exit;

} catch (PDOException $ex) {
    header('Location: ver_games.php?msg=erro_remover');
    exit;
}
