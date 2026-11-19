<?php
session_start();

// Só administradores logados acessam esta página
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '/../config/conexao.php';

$statusLista = ['pendente' => 'Pendente', 'pago' => 'Pago', 'enviado' => 'Enviado'];

$erros = [];
$usuarioId = (int)($_POST['usuario_id'] ?? 0);
$gameId = (int)($_POST['game_id'] ?? 0);
$quantidade = (int)($_POST['quantidade'] ?? 1);
$status = $_POST['status'] ?? 'pendente';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($usuarioId <= 0) {
        $erros[] = 'Escolha o cliente.';
    }

    if ($gameId <= 0) {
        $erros[] = 'Escolha o jogo.';
    }

    if ($quantidade < 1) {
        $erros[] = 'A quantidade precisa ser de pelo menos 1.';
    }

    if (!isset($statusLista[$status])) {
        $erros[] = 'Status inválido.';
    }

    if (!$erros) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE id = :id");
            $stmt->execute([':id' => $usuarioId]);

            if (!$stmt->fetch()) {
                $pdo->rollBack();
                $erros[] = 'Cliente não encontrado.';
            } else {
                // Trava o jogo para conferir e baixar o estoque com segurança
                $stmt = $pdo->prepare("SELECT id, preco, estoque FROM games WHERE id = :id FOR UPDATE");
                $stmt->execute([':id' => $gameId]);
                $game = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$game) {
                    $pdo->rollBack();
                    $erros[] = 'Jogo não encontrado.';
                } elseif ($game['estoque'] < $quantidade) {
                    $pdo->rollBack();
                    $erros[] = 'Estoque insuficiente: restam ' . (int)$game['estoque'] . ' unidade(s).';
                } else {
                    $pdo->prepare("UPDATE games SET estoque = estoque - :q WHERE id = :id")
                        ->execute([':q' => $quantidade, ':id' => $gameId]);

                    $pdo->prepare("INSERT INTO pedidos (usuario_id, game_id, quantidade, preco_unitario, status)
                                   VALUES (:u, :g, :q, :p, :s)")
                        ->execute([
                            ':u' => $usuarioId, ':g' => $gameId, ':q' => $quantidade,
                            ':p' => $game['preco'], ':s' => $status,
                        ]);

                    $pdo->commit();

                    header('Location: ver_pedidos.php?msg=cadastrado');
                    exit;
                }
            }
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erros[] = 'Não foi possível cadastrar o pedido. Rode o script atualizar_pedidos.sql e tente novamente.';
        }
    }
}

$usuarios = $pdo->query("SELECT id, nome, email FROM usuarios ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$games = $pdo->query("SELECT id, titulo, preco, estoque FROM games WHERE estoque > 0 ORDER BY titulo")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>GameStock - Cadastrar Pedido</title>

    <link rel="stylesheet" href="../public/css/style.css">
</head>

<body>

    <?php include __DIR__ . '/_navbar_admin.php'; ?>

    <div class="form-container">

        <h2>Cadastrar pedido</h2>

        <?php foreach ($erros as $erro): ?>
            <div class="alerta"><?= e($erro); ?></div>
        <?php endforeach; ?>

        <form method="POST">

            <div class="form-group">
                <label for="usuario_id">Cliente</label>
                <select id="usuario_id" name="usuario_id" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($usuarios as $u): ?>
                        <option value="<?= (int)$u['id']; ?>" <?= $usuarioId === (int)$u['id'] ? 'selected' : ''; ?>>
                            <?= e($u['nome']); ?> (<?= e($u['email']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="game_id">Jogo</label>
                <select id="game_id" name="game_id" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($games as $g): ?>
                        <option value="<?= (int)$g['id']; ?>" <?= $gameId === (int)$g['id'] ? 'selected' : ''; ?>>
                            <?= e($g['titulo']); ?> - R$ <?= number_format($g['preco'], 2, ',', '.'); ?> (<?= (int)$g['estoque']; ?> em estoque)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="quantidade">Quantidade</label>
                    <input type="number" id="quantidade" name="quantidade" min="1" value="<?= max(1, $quantidade); ?>" required>
                </div>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <?php foreach ($statusLista as $valor => $rotulo): ?>
                            <option value="<?= $valor; ?>" <?= $status === $valor ? 'selected' : ''; ?>><?= $rotulo; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-buttons">
                <button type="submit" class="btn-primary">Cadastrar pedido</button>
                <a href="ver_pedidos.php" class="btn-secondary">Voltar</a>
            </div>

        </form>

    </div>

</body>

</html>
