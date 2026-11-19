<?php
session_start();

// Só administradores logados acessam esta página
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '/../models/games.php';

$gameModel = new Game($pdo);
$games = $gameModel->listar();

$busca = trim($_GET['busca'] ?? '');
$filtroCategoria = $_GET['categoria'] ?? '';
$filtroEstoque = $_GET['estoque'] ?? '';
$ordem = $_GET['ordem'] ?? 'recentes';

$ordens = [
    'recentes'      => 'id DESC',
    'titulo'        => 'titulo ASC',
    'menor_preco'   => 'preco ASC, titulo ASC',
    'maior_preco'   => 'preco DESC, titulo ASC',
    'menor_estoque' => 'estoque ASC, titulo ASC',
];
$rotulosOrdem = [
    'recentes'      => 'Mais recentes',
    'titulo'        => 'Título (A-Z)',
    'menor_preco'   => 'Menor preço',
    'maior_preco'   => 'Maior preço',
    'menor_estoque' => 'Menor estoque',
];
$rotulosEstoque = [
    'disponivel' => 'Disponível (mais de 5)',
    'baixo'      => 'Estoque baixo (1 a 5)',
    'esgotado'   => 'Esgotado',
];

if (!isset($ordens[$ordem])) {
    $ordem = 'recentes';
}

$sql = "SELECT * FROM games WHERE 1=1";
$params = [];

if ($busca !== '') {
    $sql .= " AND titulo LIKE :busca";
    $params[':busca'] = '%' . $busca . '%';
}

if ($filtroCategoria !== '') {
    $sql .= " AND categoria = :categoria";
    $params[':categoria'] = $filtroCategoria;
}

if ($filtroEstoque === 'esgotado') {
    $sql .= " AND estoque <= 0";
} elseif ($filtroEstoque === 'baixo') {
    $sql .= " AND estoque BETWEEN 1 AND 5";
} elseif ($filtroEstoque === 'disponivel') {
    $sql .= " AND estoque > 5";
}

$sql .= " ORDER BY " . $ordens[$ordem];

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$games = $stmt->fetchAll(PDO::FETCH_ASSOC);

$categoriasFiltro = $pdo->query("SELECT DISTINCT categoria FROM games WHERE categoria IS NOT NULL AND categoria <> '' ORDER BY categoria")
                        ->fetchAll(PDO::FETCH_COLUMN);

$msg = $_GET['msg'] ?? '';

function e($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>GameStock - Painel Admin</title>

    <link rel="stylesheet" href="../public/css/style.css">
</head>

<body>

    <?php include __DIR__ . '/_navbar_admin.php'; ?>

    <div class="barra-admin">
        <h3>Gerenciar jogos</h3>

        <a href="cadastrar_game.php" class="btn-primary">+ Cadastrar novo jogo</a>
    </div>

    <?php if ($msg === 'cadastrado'): ?>
        <div class="sucesso aviso-admin">Jogo cadastrado com sucesso!</div>
    <?php elseif ($msg === 'editado'): ?>
        <div class="sucesso aviso-admin">Jogo atualizado com sucesso!</div>
    <?php elseif ($msg === 'removido'): ?>
        <div class="sucesso aviso-admin">Jogo removido do catálogo.</div>
    <?php elseif ($msg === 'com_pedidos'): ?>
        <div class="alerta aviso-admin">Este jogo já foi vendido e não pode ser removido. Edite-o e zere o estoque para tirá-lo de venda.</div>
    <?php elseif ($msg === 'erro_remover'): ?>
        <div class="alerta aviso-admin">Não foi possível remover o jogo. Tente novamente.</div>
    <?php endif; ?>

    <form method="GET" class="filtros">
        <input type="text" name="busca" placeholder="Buscar por título..." value="<?= e($busca); ?>">

        <select name="categoria" onchange="this.form.submit()">
            <option value="">Todas as categorias</option>
            <?php foreach ($categoriasFiltro as $cat): ?>
                <option value="<?= e($cat); ?>" <?= $filtroCategoria === $cat ? 'selected' : ''; ?>><?= e($cat); ?></option>
            <?php endforeach; ?>
        </select>

        <select name="estoque" onchange="this.form.submit()">
                <option value="">Todo o estoque</option>
                <?php foreach ($rotulosEstoque as $valor => $rotulo): ?>
                    <option value="<?= e($valor); ?>" <?= $filtroEstoque === (string)$valor ? 'selected' : ''; ?>><?= e($rotulo); ?></option>
                <?php endforeach; ?>
            </select>

        <select name="ordem" onchange="this.form.submit()">
                                <?php foreach ($rotulosOrdem as $valor => $rotulo): ?>
                    <option value="<?= e($valor); ?>" <?= $ordem === (string)$valor ? 'selected' : ''; ?>><?= e($rotulo); ?></option>
                <?php endforeach; ?>
            </select>

        <button type="submit" class="btn-primary">Filtrar</button>
        <a href="ver_games.php" class="btn-secondary">Limpar</a>
    </form>

    <p class="resumo"><?= count($games); ?> jogo(s) encontrado(s)</p>

    <table>

        <tr>
            <th>ID</th>
            <th>Título</th>
            <th>Descrição</th>
            <th>Preço</th>
            <th>Estoque</th>
            <th>Categoria</th>
            <th>Imagem</th>
            <th>Ações</th>
        </tr>

        <?php if (count($games) > 0): ?>

            <?php foreach ($games as $game): ?>

                <tr>

                    <td><?= (int)$game['id']; ?></td>

                    <td><?= e($game['titulo']); ?></td>

                    <td><?= e(mb_strimwidth((string)$game['descricao'], 0, 70, '...')); ?></td>

                    <td>R$ <?= number_format($game['preco'], 2, ',', '.'); ?></td>

                    <td><?= (int)$game['estoque']; ?></td>

                    <td><?= e($game['categoria']); ?></td>

                    <td>
                        <?php if ($game['imagem']): ?>
                            <img class="mini" src="../public/imagens/<?= e($game['imagem']); ?>" alt="" onerror="this.replaceWith(document.createTextNode('-'))">
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>

                    <td class="acoes">
                        <a href="../../jogo.php?id=<?= (int)$game['id']; ?>" class="btn-secondary">Ver</a>
                        <a href="editar_game.php?id=<?= (int)$game['id']; ?>" class="btn-primary">Editar</a>

                        <form method="POST" action="remover_game.php" class="form-inline"
                              onsubmit="return confirm(<?= e(json_encode('Remover "' . $game['titulo'] . '" do catálogo? Esta ação não pode ser desfeita.')); ?>);">
                            <input type="hidden" name="id" value="<?= (int)$game['id']; ?>">
                            <button type="submit" class="btn-remover">Remover</button>
                        </form>
                    </td>

                </tr>

            <?php endforeach; ?>

        <?php else: ?>

            <tr>
                <td colspan="8">
                    Nenhum jogo cadastrado.
                </td>
            </tr>

        <?php endif; ?>

    </table>

</body>

</html>
