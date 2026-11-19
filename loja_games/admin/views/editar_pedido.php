<?php
session_start();

// Só administradores logados acessam esta página
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '/../config/conexao.php';

$statusLista = ['pendente' => 'Pendente', 'pago' => 'Pago', 'enviado' => 'Enviado', 'cancelado' => 'Cancelado'];

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: ver_pedidos.php');
    exit;
}

function buscarPedido($pdo, $id) {
    $stmt = $pdo->prepare("SELECT p.id, p.game_id, p.quantidade, p.preco_unitario, p.status, p.created_at,
                                  u.nome AS cliente, u.email, g.titulo
                           FROM pedidos p
                           JOIN usuarios u ON u.id = p.usuario_id
                           JOIN games g ON g.id = p.game_id
                           WHERE p.id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

try {
    $pedido = buscarPedido($pdo, $id);
} catch (PDOException $ex) {
    header('Location: ver_pedidos.php?msg=erro');
    exit;
}

if (!$pedido) {
    header('Location: ver_pedidos.php');
    exit;
}

$erros = [];
$status = $pedido['status'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $status = $_POST['status'] ?? $pedido['status'];

    if (!isset($statusLista[$status])) {
        $erros[] = 'Status inválido.';
    }

    if (!$erros) {
        try {
            $pdo->beginTransaction();

            // Trava o pedido e relê o status atual
            $stmt = $pdo->prepare("SELECT status, quantidade, game_id FROM pedidos WHERE id = :id FOR UPDATE");
            $stmt->execute([':id' => $id]);
            $atual = $stmt->fetch(PDO::FETCH_ASSOC);

            $ok = true;

            if ($atual['status'] !== 'cancelado' && $status === 'cancelado') {
                // Cancelou: devolve as unidades ao estoque
                $pdo->prepare("UPDATE games SET estoque = estoque + :q WHERE id = :g")
                    ->execute([':q' => $atual['quantidade'], ':g' => $atual['game_id']]);

            } elseif ($atual['status'] === 'cancelado' && $status !== 'cancelado') {
                // Reativou um pedido cancelado: precisa ter estoque de novo
                $stmt = $pdo->prepare("SELECT estoque FROM games WHERE id = :g FOR UPDATE");
                $stmt->execute([':g' => $atual['game_id']]);
                $estoque = (int)$stmt->fetchColumn();

                if ($estoque < $atual['quantidade']) {
                    $ok = false;
                    $erros[] = 'Estoque insuficiente para reativar este pedido: restam ' . $estoque . ' unidade(s).';
                } else {
                    $pdo->prepare("UPDATE games SET estoque = estoque - :q WHERE id = :g")
                        ->execute([':q' => $atual['quantidade'], ':g' => $atual['game_id']]);
                }
            }

            if ($ok) {
                $pdo->prepare("UPDATE pedidos SET status = :s WHERE id = :id")
                    ->execute([':s' => $status, ':id' => $id]);

                $pdo->commit();

                header('Location: ver_pedidos.php?msg=editado');
                exit;
            }

            $pdo->rollBack();

        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erros[] = 'Não foi possível salvar as alterações. Tente novamente.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>GameStock - Editar Pedido</title>

    <link rel="stylesheet" href="../public/css/style.css">
</head>

<body>

    <?php include __DIR__ . '/_navbar_admin.php'; ?>

    <div class="form-container">

        <h2>Editar pedido #<?= (int)$pedido['id']; ?></h2>

        <?php foreach ($erros as $erro): ?>
            <div class="alerta"><?= e($erro); ?></div>
        <?php endforeach; ?>

        <table class="ficha">
            <tr><th>Cliente</th><td><?= e($pedido['cliente']); ?> <small class="voce">(<?= e($pedido['email']); ?>)</small></td></tr>
            <tr><th>Jogo</th><td><?= e($pedido['titulo']); ?></td></tr>
            <tr><th>Quantidade</th><td><?= (int)$pedido['quantidade']; ?></td></tr>
            <tr><th>Valor unitário</th><td>R$ <?= number_format($pedido['preco_unitario'], 2, ',', '.'); ?></td></tr>
            <tr><th>Total</th><td>R$ <?= number_format($pedido['quantidade'] * $pedido['preco_unitario'], 2, ',', '.'); ?></td></tr>
            <tr><th>Data</th><td><?= date('d/m/Y H:i', strtotime($pedido['created_at'])); ?></td></tr>
        </table>

        <form method="POST">

            <div class="form-group">
                <label for="status">Status do pedido</label>
                <select id="status" name="status">
                    <?php foreach ($statusLista as $valor => $rotulo): ?>
                        <option value="<?= $valor; ?>" <?= $status === $valor ? 'selected' : ''; ?>><?= $rotulo; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <p class="login-box">Ao cancelar, as unidades voltam ao estoque. Ao reativar um pedido cancelado, elas são descontadas de novo.</p>

            <div class="form-buttons">
                <button type="submit" class="btn-primary">Salvar alterações</button>
                <a href="ver_pedidos.php" class="btn-secondary">Cancelar</a>
            </div>

        </form>

    </div>

</body>

</html>
