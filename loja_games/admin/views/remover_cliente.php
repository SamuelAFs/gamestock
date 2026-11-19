<?php
session_start();

// Só administradores logados podem remover clientes
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

// Só aceita envio pelo botão Remover (POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ver_clientes.php');
    exit;
}

require_once __DIR__ . '/../config/conexao.php';

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    header('Location: ver_clientes.php');
    exit;
}

try {
    // Remove só o cadastro do cliente; a conta de login (se houver) continua existindo
    $pdo->prepare("DELETE FROM clientes WHERE id = :id")->execute([':id' => $id]);

    header('Location: ver_clientes.php?msg=removido');
    exit;

} catch (PDOException $ex) {
    header('Location: ver_clientes.php?msg=erro');
    exit;
}
