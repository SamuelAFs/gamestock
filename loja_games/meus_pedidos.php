<?php
session_start();
require_once 'admin/config/conexao.php';
require_once __DIR__ . '/includes/funcoes.php';

// Precisa estar logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$statusLista = ['pendente' => 'Pendente', 'pago' => 'Pago', 'enviado' => 'Enviado', 'cancelado' => 'Cancelado'];

$msg = $_GET['msg'] ?? '';
$pedidos = [];
$erroBanco = false;

try {
    $stmt = $pdo->prepare("SELECT p.id, p.game_id, p.quantidade, p.preco_unitario, p.status, p.created_at, g.titulo
                           FROM pedidos p
                           JOIN games g ON g.id = p.game_id
                           WHERE p.usuario_id = :u
                           ORDER BY p.id DESC");
    $stmt->execute([':u' => (int)$_SESSION['usuario_id']]);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $ex) {
    $erroBanco = true;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GameStock - Meus pedidos</title>
    <link rel="stylesheet" href="admin/public/css/style.css">
</head>
<body>

    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <section class="hero">
        <h1>Meus pedidos</h1>
        <p><?= count($pedidos); ?> pedido(s)</p>
    </section>

    <?php if ($erroBanco): ?>
        <div class="alerta aviso-admin">Não foi possível carregar os pedidos agora.</div>
    <?php endif; ?>

    <?php if ($msg === 'compra_ok'): ?>
        <div class="sucesso aviso-admin">Compra finalizada com sucesso! Seus pedidos estão pendentes.</div>
    <?php elseif ($msg === 'cancelado'): ?>
        <div class="sucesso aviso-admin">Pedido cancelado.</div>
    <?php elseif ($msg === 'nao_pode'): ?>
        <div class="alerta aviso-admin">Só é possível cancelar pedidos pendentes.</div>
    <?php elseif ($msg === 'erro'): ?>
        <div class="alerta aviso-admin">Não foi possível cancelar o pedido. Tente novamente.</div>
    <?php endif; ?>

    <table>

        <tr>
            <th>Pedido</th>
            <th>Jogo</th>
            <th>Qtd</th>
            <th>Total</th>
            <th>Status</th>
            <th>Data</th>
            <th>Ações</th>
        </tr>

        <?php if (count($pedidos) > 0): ?>

            <?php foreach ($pedidos as $p): ?>

                <tr>
                    <td>#<?= (int)$p['id']; ?></td>

                    <td><a href="jogo.php?id=<?= (int)$p['game_id']; ?>"><?= e($p['titulo']); ?></a></td>

                    <td><?= (int)$p['quantidade']; ?></td>

                    <td>R$ <?= number_format($p['quantidade'] * $p['preco_unitario'], 2, ',', '.'); ?></td>

                    <td><span class="tag tag-<?= e($p['status']); ?>"><?= e($statusLista[$p['status']] ?? $p['status']); ?></span></td>

                    <td><?= date('d/m/Y H:i', strtotime($p['created_at'])); ?></td>

                    <td class="acoes">
                        <?php if ($p['status'] === 'pendente'): ?>
                            <form method="POST" action="cancelar_pedido.php" class="form-inline"
                                  onsubmit="return confirm('Cancelar este pedido?');">
                                <input type="hidden" name="id" value="<?= (int)$p['id']; ?>">
                                <button type="submit" class="btn-remover">Cancelar</button>
                            </form>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>

            <?php endforeach; ?>

        <?php else: ?>

            <tr>
                <td colspan="7">Você ainda não fez nenhum pedido. <a href="index.php">Ver jogos</a></td>
            </tr>

        <?php endif; ?>

    </table>

    <?php include __DIR__ . '/includes/rodape.php'; ?>

</body>
</html>
