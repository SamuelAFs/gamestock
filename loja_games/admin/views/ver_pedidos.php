<?php
session_start();

// Só administradores logados acessam esta página
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '/../config/conexao.php';

$statusLista = ['pendente' => 'Pendente', 'pago' => 'Pago', 'enviado' => 'Enviado', 'cancelado' => 'Cancelado'];

$msg = $_GET['msg'] ?? '';
$filtroStatus = $_GET['status'] ?? '';
$busca = trim($_GET['busca'] ?? '');
$de = $_GET['de'] ?? '';
$ate = $_GET['ate'] ?? '';
$ordem = $_GET['ordem'] ?? 'recentes';

// Datas só valem no formato AAAA-MM-DD
$de = preg_match('/^\d{4}-\d{2}-\d{2}$/', $de) ? $de : '';
$ate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate) ? $ate : '';

$ordens = [
    'recentes'    => 'p.id DESC',
    'antigos'     => 'p.id ASC',
    'maior_valor' => '(p.quantidade * p.preco_unitario) DESC, p.id DESC',
    'menor_valor' => '(p.quantidade * p.preco_unitario) ASC, p.id DESC',
];
$rotulosOrdem = [
    'recentes'    => 'Mais recentes',
    'antigos'     => 'Mais antigos',
    'maior_valor' => 'Maior valor',
    'menor_valor' => 'Menor valor',
];

if (!isset($ordens[$ordem])) {
    $ordem = 'recentes';
}

$pedidos = [];
$erroBanco = false;
$total = 0.0;

try {
    $sql = "SELECT p.id, p.quantidade, p.preco_unitario, p.status, p.created_at,
                   u.nome AS cliente, u.email, g.titulo
            FROM pedidos p
            JOIN usuarios u ON u.id = p.usuario_id
            JOIN games g ON g.id = p.game_id
            WHERE 1=1";
    $params = [];

    if (isset($statusLista[$filtroStatus])) {
        $sql .= " AND p.status = :status";
        $params[':status'] = $filtroStatus;
    }

    if ($busca !== '') {
        $sql .= " AND (u.nome LIKE :b1 OR u.email LIKE :b2 OR g.titulo LIKE :b3)";
        $params[':b1'] = '%' . $busca . '%';
        $params[':b2'] = '%' . $busca . '%';
        $params[':b3'] = '%' . $busca . '%';
    }

    if ($de !== '') {
        $sql .= " AND p.created_at >= :de";
        $params[':de'] = $de . ' 00:00:00';
    }

    if ($ate !== '') {
        $sql .= " AND p.created_at < :ate";
        $params[':ate'] = date('Y-m-d', strtotime($ate . ' +1 day')) . ' 00:00:00';
    }

    $sql .= " ORDER BY " . $ordens[$ordem];

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($pedidos as $p) {
        if ($p['status'] !== 'cancelado') {
            $total += $p['quantidade'] * $p['preco_unitario'];
        }
    }
} catch (PDOException $ex) {
    $erroBanco = true;
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>GameStock - Pedidos</title>

    <link rel="stylesheet" href="../public/css/style.css">
</head>

<body>

    <?php include __DIR__ . '/_navbar_admin.php'; ?>

    <div class="barra-admin">
        <h3>Gerenciar pedidos</h3>

        <a href="cadastrar_pedido.php" class="btn-primary">+ Cadastrar novo pedido</a>
    </div>

    <?php if ($erroBanco): ?>
        <div class="alerta aviso-admin">Não foi possível ler os pedidos. Rode o script <strong>atualizar_pedidos.sql</strong> no phpMyAdmin.</div>
    <?php endif; ?>

    <?php if ($msg === 'cadastrado'): ?>
        <div class="sucesso aviso-admin">Pedido cadastrado com sucesso!</div>
    <?php elseif ($msg === 'editado'): ?>
        <div class="sucesso aviso-admin">Pedido atualizado com sucesso!</div>
    <?php elseif ($msg === 'removido'): ?>
        <div class="sucesso aviso-admin">Pedido removido.</div>
    <?php elseif ($msg === 'erro'): ?>
        <div class="alerta aviso-admin">Não foi possível concluir a operação. Tente novamente.</div>
    <?php endif; ?>

    <form method="GET" class="filtros">
        <input type="text" name="busca" placeholder="Buscar por cliente, e-mail ou jogo..." value="<?= e($busca); ?>">

        <select name="status" onchange="this.form.submit()">
            <option value="">Todos os status</option>
            <?php foreach ($statusLista as $valor => $rotulo): ?>
                <option value="<?= $valor; ?>" <?= $filtroStatus === $valor ? 'selected' : ''; ?>><?= $rotulo; ?></option>
            <?php endforeach; ?>
        </select>

        <label class="campo">De <input type="date" name="de" value="<?= e($de); ?>"></label>
        <label class="campo">Até <input type="date" name="ate" value="<?= e($ate); ?>"></label>

        <select name="ordem" onchange="this.form.submit()">
                                <?php foreach ($rotulosOrdem as $valor => $rotulo): ?>
                    <option value="<?= e($valor); ?>" <?= $ordem === (string)$valor ? 'selected' : ''; ?>><?= e($rotulo); ?></option>
                <?php endforeach; ?>
            </select>

        <button type="submit" class="btn-primary">Filtrar</button>
        <a href="ver_pedidos.php" class="btn-secondary">Limpar</a>
    </form>

    <p class="resumo">
        <?= count($pedidos); ?> pedido(s) na lista &middot;
        Total em vendas (sem cancelados): <strong>R$ <?= number_format($total, 2, ',', '.'); ?></strong>
    </p>

    <table>

        <tr>
            <th>ID</th>
            <th>Cliente</th>
            <th>Jogo</th>
            <th>Qtd</th>
            <th>Valor unit.</th>
            <th>Total</th>
            <th>Status</th>
            <th>Data</th>
            <th>Ações</th>
        </tr>

        <?php if (count($pedidos) > 0): ?>

            <?php foreach ($pedidos as $p): ?>

                <tr>

                    <td>#<?= (int)$p['id']; ?></td>

                    <td>
                        <?= e($p['cliente']); ?><br>
                        <small class="voce"><?= e($p['email']); ?></small>
                    </td>

                    <td><?= e($p['titulo']); ?></td>

                    <td><?= (int)$p['quantidade']; ?></td>

                    <td>R$ <?= number_format($p['preco_unitario'], 2, ',', '.'); ?></td>

                    <td>R$ <?= number_format($p['quantidade'] * $p['preco_unitario'], 2, ',', '.'); ?></td>

                    <td><span class="tag tag-<?= e($p['status']); ?>"><?= e($statusLista[$p['status']] ?? $p['status']); ?></span></td>

                    <td><?= date('d/m/Y H:i', strtotime($p['created_at'])); ?></td>

                    <td class="acoes">
                        <a href="editar_pedido.php?id=<?= (int)$p['id']; ?>" class="btn-primary">Editar</a>

                        <form method="POST" action="remover_pedido.php" class="form-inline"
                              onsubmit="return confirm(<?= e(json_encode('Remover o pedido #' . $p['id'] . '? Esta ação não pode ser desfeita.')); ?>);">
                            <input type="hidden" name="id" value="<?= (int)$p['id']; ?>">
                            <button type="submit" class="btn-remover">Remover</button>
                        </form>
                    </td>

                </tr>

            <?php endforeach; ?>

        <?php else: ?>

            <tr>
                <td colspan="9">Nenhum pedido encontrado.</td>
            </tr>

        <?php endif; ?>

    </table>

</body>

</html>
