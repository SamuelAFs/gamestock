<?php
session_start();

// Só administradores logados acessam esta página
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '/../config/conexao.php';

$statusLista = ['pendente' => 'Pendente', 'pago' => 'Pago', 'enviado' => 'Enviado', 'cancelado' => 'Cancelado'];

$erroBanco = false;

$totalVendas = 0.0;
$totalPedidos = 0;
$ticketMedio = 0.0;
$totalJogos = 0;
$totalEstoque = 0;
$totalClientes = 0;
$totalUsuarios = 0;
$porStatus = array_fill_keys(array_keys($statusLista), 0);
$topJogos = [];
$porCategoria = [];
$estoqueBaixo = [];
$ultimosPedidos = [];
$dias = [];

try {
    // Indicadores gerais (vendas não contam pedidos cancelados)
    $linha = $pdo->query("SELECT COUNT(*) AS pedidos, COALESCE(SUM(quantidade * preco_unitario), 0) AS total
                          FROM pedidos WHERE status <> 'cancelado'")->fetch(PDO::FETCH_ASSOC);
    $totalPedidos = (int)$linha['pedidos'];
    $totalVendas = (float)$linha['total'];
    $ticketMedio = $totalPedidos > 0 ? $totalVendas / $totalPedidos : 0.0;

    $linha = $pdo->query("SELECT COUNT(*) AS jogos, COALESCE(SUM(estoque), 0) AS unidades FROM games")->fetch(PDO::FETCH_ASSOC);
    $totalJogos = (int)$linha['jogos'];
    $totalEstoque = (int)$linha['unidades'];

    $totalUsuarios = (int)$pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();

    foreach ($pdo->query("SELECT status, COUNT(*) AS total FROM pedidos GROUP BY status")->fetchAll(PDO::FETCH_ASSOC) as $l) {
        $porStatus[$l['status']] = (int)$l['total'];
    }

    // Jogos mais vendidos
    $topJogos = $pdo->query("SELECT g.titulo, SUM(p.quantidade) AS unidades, SUM(p.quantidade * p.preco_unitario) AS total
                             FROM pedidos p JOIN games g ON g.id = p.game_id
                             WHERE p.status <> 'cancelado'
                             GROUP BY g.id, g.titulo ORDER BY unidades DESC, total DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

    // Vendas por categoria
    $porCategoria = $pdo->query("SELECT g.categoria, SUM(p.quantidade * p.preco_unitario) AS total
                                 FROM pedidos p JOIN games g ON g.id = p.game_id
                                 WHERE p.status <> 'cancelado'
                                 GROUP BY g.categoria ORDER BY total DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);

    // Jogos com estoque baixo
    $estoqueBaixo = $pdo->query("SELECT titulo, estoque FROM games WHERE estoque <= 5 ORDER BY estoque ASC, titulo LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);

    // Últimos pedidos
    $ultimosPedidos = $pdo->query("SELECT p.id, p.quantidade, p.preco_unitario, p.status, p.created_at, u.nome AS cliente, g.titulo
                                   FROM pedidos p JOIN usuarios u ON u.id = p.usuario_id JOIN games g ON g.id = p.game_id
                                   ORDER BY p.id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

    // Vendas dos últimos 7 dias
    $porDia = [];
    foreach ($pdo->query("SELECT DATE(created_at) AS dia, SUM(quantidade * preco_unitario) AS total
                          FROM pedidos
                          WHERE status <> 'cancelado' AND created_at >= (CURDATE() - INTERVAL 6 DAY)
                          GROUP BY DATE(created_at)")->fetchAll(PDO::FETCH_ASSOC) as $l) {
        $porDia[$l['dia']] = (float)$l['total'];
    }

    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i day"));
        $dias[] = ['rotulo' => date('d/m', strtotime($d)), 'total' => $porDia[$d] ?? 0.0];
    }
} catch (PDOException $ex) {
    $erroBanco = true;
}

// Clientes (tabela criada depois: se faltar, o resto do painel continua funcionando)
try {
    $totalClientes = (int)$pdo->query("SELECT COUNT(*) FROM clientes")->fetchColumn();
} catch (PDOException $ex) {
    $totalClientes = 0;
}

$maxDia = max(array_column($dias ?: [['total' => 0]], 'total'));
$maxTop = $topJogos ? max(array_map('floatval', array_column($topJogos, 'unidades'))) : 0;
$maxCat = $porCategoria ? max(array_map('floatval', array_column($porCategoria, 'total'))) : 0;

function reais($valor) {
    return 'R$ ' . number_format((float)$valor, 2, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>GameStock - Dashboard</title>

    <link rel="stylesheet" href="../public/css/style.css">
</head>

<body>

    <?php include __DIR__ . '/_navbar_admin.php'; ?>

    <div class="barra-admin">
        <h3>Dashboard</h3>
        <a href="ver_pedidos.php" class="btn-secondary">Ver todos os pedidos</a>
    </div>

    <?php if ($erroBanco): ?>
        <div class="alerta aviso-admin">Não foi possível ler os dados de vendas. Rode o script <strong>atualizar_pedidos.sql</strong> no phpMyAdmin.</div>
    <?php endif; ?>

    <section class="cards-resumo">
        <div class="stat"><span>Total em vendas</span><strong><?= reais($totalVendas); ?></strong></div>
        <div class="stat"><span>Pedidos (sem cancelados)</span><strong><?= $totalPedidos; ?></strong></div>
        <div class="stat"><span>Ticket médio</span><strong><?= reais($ticketMedio); ?></strong></div>
        <div class="stat"><span>Jogos no catálogo</span><strong><?= $totalJogos; ?></strong></div>
        <div class="stat"><span>Unidades em estoque</span><strong><?= $totalEstoque; ?></strong></div>
        <div class="stat"><span>Clientes</span><strong><?= $totalClientes; ?></strong></div>
        <div class="stat"><span>Usuários</span><strong><?= $totalUsuarios; ?></strong></div>
    </section>

    <section class="paineis">

        <div class="painel-box"<?= estilo_painel(); ?>>
            <h4>Vendas dos últimos 7 dias</h4>
            <div class="grafico-dias">
                <?php foreach ($dias as $d): ?>
                    <?php $altura = $maxDia > 0 ? max(2, round($d['total'] / $maxDia * 100)) : 2; ?>
                    <div class="coluna">
                        <small><?= $d['total'] > 0 ? e(number_format($d['total'], 0, ',', '.')) : ''; ?></small>
                        <div class="barra-v" style="height: <?= $altura; ?>%;"></div>
                        <small><?= e($d['rotulo']); ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="painel-box"<?= estilo_painel(); ?>>
            <h4>Pedidos por status</h4>
            <div class="lista-status">
                <?php foreach ($statusLista as $chave => $rotulo): ?>
                    <div class="linha-status">
                        <span class="tag tag-<?= $chave; ?>"><?= $rotulo; ?></span>
                        <strong><?= (int)$porStatus[$chave]; ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="painel-box"<?= estilo_painel(); ?>>
            <h4>Jogos mais vendidos</h4>
            <?php if ($topJogos): ?>
                <?php foreach ($topJogos as $j): ?>
                    <div class="rank">
                        <div class="rank-topo"><span><?= e($j['titulo']); ?></span><strong><?= (int)$j['unidades']; ?> un.</strong></div>
                        <div class="trilho"><div class="preench" style="width: <?= $maxTop > 0 ? round($j['unidades'] / $maxTop * 100) : 0; ?>%;"></div></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="vazio-box">Ainda não há vendas.</p>
            <?php endif; ?>
        </div>

        <div class="painel-box"<?= estilo_painel(); ?>>
            <h4>Vendas por categoria</h4>
            <?php if ($porCategoria): ?>
                <?php foreach ($porCategoria as $c): ?>
                    <div class="rank">
                        <div class="rank-topo"><span><?= e($c['categoria']); ?></span><strong><?= reais($c['total']); ?></strong></div>
                        <div class="trilho"><div class="preench" style="width: <?= $maxCat > 0 ? round($c['total'] / $maxCat * 100) : 0; ?>%;"></div></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="vazio-box">Ainda não há vendas.</p>
            <?php endif; ?>
        </div>

        <div class="painel-box"<?= estilo_painel(); ?>>
            <h4>Estoque baixo (5 ou menos)</h4>
            <?php if ($estoqueBaixo): ?>
                <?php foreach ($estoqueBaixo as $j): ?>
                    <div class="linha-status">
                        <span><?= e($j['titulo']); ?></span>
                        <span class="estoque <?= (int)$j['estoque'] === 0 ? 'esgotado' : 'baixo'; ?>">
                            <?= (int)$j['estoque'] === 0 ? 'Esgotado' : (int)$j['estoque'] . ' un.'; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="vazio-box">Nenhum jogo com estoque baixo.</p>
            <?php endif; ?>
        </div>

        <div class="painel-box"<?= estilo_painel(); ?>>
            <h4>Últimos pedidos</h4>
            <?php if ($ultimosPedidos): ?>
                <?php foreach ($ultimosPedidos as $p): ?>
                    <div class="linha-status">
                        <span>#<?= (int)$p['id']; ?> &middot; <?= e($p['cliente']); ?> &middot; <?= e($p['titulo']); ?></span>
                        <span class="tag tag-<?= e($p['status']); ?>"><?= e($statusLista[$p['status']] ?? $p['status']); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="vazio-box">Ainda não há pedidos.</p>
            <?php endif; ?>
        </div>

    </section>

</body>

</html>
