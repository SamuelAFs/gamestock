<?php
session_start();

// Só administradores logados podem remover usuários
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

// Só aceita envio pelo botão Remover (POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ver_usuarios.php');
    exit;
}

require_once __DIR__ . '/../config/conexao.php';

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    header('Location: ver_usuarios.php');
    exit;
}

// Não pode remover a própria conta
if ($id === (int)$_SESSION['usuario_id']) {
    header('Location: ver_usuarios.php?msg=proprio');
    exit;
}

// Quem já comprou não pode ser apagado (preserva o histórico de pedidos)
$temPedidos = false;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE usuario_id = :id");
    $stmt->execute([':id' => $id]);
    $temPedidos = $stmt->fetchColumn() > 0;
} catch (PDOException $ex) {
    // Tabela pedidos ainda não existe: não há histórico para proteger
}

if ($temPedidos) {
    header('Location: ver_usuarios.php?msg=com_pedidos');
    exit;
}

// Não deixa o sistema sem administradores
$stmt = $pdo->prepare("SELECT tipo FROM usuarios WHERE id = :id");
$stmt->execute([':id' => $id]);
$alvo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$alvo) {
    header('Location: ver_usuarios.php');
    exit;
}

if ($alvo['tipo'] === 'admin') {
    $admins = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE tipo = 'admin'")->fetchColumn();

    if ($admins <= 1) {
        header('Location: ver_usuarios.php?msg=ultimo_admin');
        exit;
    }
}

try {
    $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = :id");
    $stmt->execute([':id' => $id]);

    header('Location: ver_usuarios.php?msg=removido');
    exit;

} catch (PDOException $ex) {
    header('Location: ver_usuarios.php?msg=erro_remover');
    exit;
}
