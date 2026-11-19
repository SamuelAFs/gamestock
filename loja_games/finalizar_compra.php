<?php
session_start();
require_once 'admin/config/conexao.php';

// Só aceita envio pelo botão Finalizar compra (POST) e exige login
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: carrinho.php');
    exit;
}

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$carrinho = $_SESSION['carrinho'] ?? [];

if (!is_array($carrinho) || count($carrinho) === 0) {
    header('Location: carrinho.php');
    exit;
}

$usuarioId = (int)$_SESSION['usuario_id'];

// Ordem fixa dos ids evita travamentos entre compras simultâneas
$ids = array_map('intval', array_keys($carrinho));
sort($ids);

try {
    $pdo->beginTransaction();

    foreach ($ids as $id) {
        $qtd = (int)$carrinho[$id];

        // Trava o jogo para conferir e baixar o estoque com segurança
        $stmt = $pdo->prepare("SELECT id, titulo, preco, estoque FROM games WHERE id = :id FOR UPDATE");
        $stmt->execute([':id' => $id]);
        $game = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$game) {
            $pdo->rollBack();
            unset($_SESSION['carrinho'][$id]);
            $_SESSION['carrinho_erro'] = 'Um dos jogos não está mais disponível e foi retirado do carrinho. Confira e tente de novo.';
            header('Location: carrinho.php');
            exit;
        }

        if ($qtd < 1 || $game['estoque'] < $qtd) {
            $pdo->rollBack();
            $_SESSION['carrinho_erro'] = 'Estoque insuficiente de "' . $game['titulo'] . '": restam ' . (int)$game['estoque'] . ' unidade(s). Ajuste a quantidade.';
            header('Location: carrinho.php');
            exit;
        }

        $pdo->prepare("UPDATE games SET estoque = estoque - :q WHERE id = :id")
            ->execute([':q' => $qtd, ':id' => $id]);

        $pdo->prepare("INSERT INTO pedidos (usuario_id, game_id, quantidade, preco_unitario)
                       VALUES (:u, :g, :q, :p)")
            ->execute([':u' => $usuarioId, ':g' => $id, ':q' => $qtd, ':p' => $game['preco']]);
    }

    $pdo->commit();

    $_SESSION['carrinho'] = [];

    header('Location: meus_pedidos.php?msg=compra_ok');
    exit;

} catch (PDOException $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['carrinho_erro'] = 'Não foi possível finalizar a compra. Tente novamente.';
    header('Location: carrinho.php');
    exit;
}
