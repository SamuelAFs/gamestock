<?php
session_start();

// Só administradores logados podem remover categorias
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

// Só aceita envio pelo botão Remover (POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ver_categorias.php');
    exit;
}

require_once __DIR__ . '/../config/conexao.php';

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    header('Location: ver_categorias.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT nome FROM categorias WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $categoria = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$categoria) {
        header('Location: ver_categorias.php');
        exit;
    }

    // Categoria com jogos não pode ser removida
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM games WHERE categoria = :nome");
    $stmt->execute([':nome' => $categoria['nome']]);

    if ($stmt->fetchColumn() > 0) {
        header('Location: ver_categorias.php?msg=em_uso');
        exit;
    }

    $pdo->prepare("DELETE FROM categorias WHERE id = :id")->execute([':id' => $id]);

    header('Location: ver_categorias.php?msg=removido');
    exit;

} catch (PDOException $ex) {
    header('Location: ver_categorias.php?msg=erro');
    exit;
}
