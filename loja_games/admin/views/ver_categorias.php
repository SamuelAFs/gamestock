<?php
session_start();

// Só administradores logados acessam esta página
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '/../config/conexao.php';

$msg = $_GET['msg'] ?? '';
$busca = trim($_GET['busca'] ?? '');
$filtroUso = $_GET['uso'] ?? '';
$ordem = $_GET['ordem'] ?? 'nome';

$ordens = [
    'nome'        => 'c.nome ASC',
    'mais_jogos'  => 'total_jogos DESC, c.nome ASC',
    'menos_jogos' => 'total_jogos ASC, c.nome ASC',
];
$rotulosOrdem = [
    'nome'        => 'Nome (A-Z)',
    'mais_jogos'  => 'Mais jogos',
    'menos_jogos' => 'Menos jogos',
];
$rotulosUso = ['com' => 'Com jogos', 'sem' => 'Sem jogos'];

if (!isset($ordens[$ordem])) {
    $ordem = 'nome';
}
$categorias = [];
$erroBanco = false;

try {
    $sql = "SELECT c.id, c.nome, c.descricao, COUNT(g.id) AS total_jogos
            FROM categorias c
            LEFT JOIN games g ON g.categoria = c.nome
            WHERE 1=1";
    $params = [];

    if ($busca !== '') {
        $sql .= " AND c.nome LIKE :busca";
        $params[':busca'] = '%' . $busca . '%';
    }

    $sql .= " GROUP BY c.id, c.nome, c.descricao";

    if ($filtroUso === 'com') {
        $sql .= " HAVING COUNT(g.id) > 0";
    } elseif ($filtroUso === 'sem') {
        $sql .= " HAVING COUNT(g.id) = 0";
    }

    $sql .= " ORDER BY " . $ordens[$ordem];

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $ex) {
    $erroBanco = true;
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>GameStock - Categorias</title>

    <link rel="stylesheet" href="../public/css/style.css">
</head>

<body>

    <?php include __DIR__ . '/_navbar_admin.php'; ?>

    <div class="barra-admin">
        <h3>Gerenciar categorias</h3>

        <a href="cadastrar_categoria.php" class="btn-primary">+ Cadastrar nova categoria</a>
    </div>

    <?php if ($erroBanco): ?>
        <div class="alerta aviso-admin">Não foi possível ler as categorias. Rode o script <strong>criar_categorias_clientes.sql</strong> no phpMyAdmin.</div>
    <?php endif; ?>

    <?php if ($msg === 'cadastrado'): ?>
        <div class="sucesso aviso-admin">Categoria cadastrada com sucesso!</div>
    <?php elseif ($msg === 'editado'): ?>
        <div class="sucesso aviso-admin">Categoria atualizada com sucesso!</div>
    <?php elseif ($msg === 'removido'): ?>
        <div class="sucesso aviso-admin">Categoria removida.</div>
    <?php elseif ($msg === 'em_uso'): ?>
        <div class="alerta aviso-admin">Esta categoria ainda tem jogos. Mude a categoria desses jogos antes de removê-la.</div>
    <?php elseif ($msg === 'erro'): ?>
        <div class="alerta aviso-admin">Não foi possível concluir a operação. Tente novamente.</div>
    <?php endif; ?>

    <form method="GET" class="filtros">
        <input type="text" name="busca" placeholder="Buscar categoria..." value="<?= e($busca); ?>">

        <select name="uso" onchange="this.form.submit()">
                <option value="">Todas as categorias</option>
                <?php foreach ($rotulosUso as $valor => $rotulo): ?>
                    <option value="<?= e($valor); ?>" <?= $filtroUso === (string)$valor ? 'selected' : ''; ?>><?= e($rotulo); ?></option>
                <?php endforeach; ?>
            </select>

        <select name="ordem" onchange="this.form.submit()">
                                <?php foreach ($rotulosOrdem as $valor => $rotulo): ?>
                    <option value="<?= e($valor); ?>" <?= $ordem === (string)$valor ? 'selected' : ''; ?>><?= e($rotulo); ?></option>
                <?php endforeach; ?>
            </select>

        <button type="submit" class="btn-primary">Filtrar</button>
        <a href="ver_categorias.php" class="btn-secondary">Limpar</a>
    </form>

    <p class="resumo"><?= count($categorias); ?> categoria(s) encontrada(s)</p>

    <table>

        <tr>
            <th>ID</th>
            <th>Nome</th>
            <th>Descrição</th>
            <th>Jogos</th>
            <th>Ações</th>
        </tr>

        <?php if (count($categorias) > 0): ?>

            <?php foreach ($categorias as $c): ?>

                <tr>
                    <td><?= (int)$c['id']; ?></td>
                    <td><?= e($c['nome']); ?></td>
                    <td><?= e($c['descricao']); ?></td>
                    <td><?= (int)$c['total_jogos']; ?></td>

                    <td class="acoes">
                        <a href="editar_categoria.php?id=<?= (int)$c['id']; ?>" class="btn-primary">Editar</a>

                        <form method="POST" action="remover_categoria.php" class="form-inline"
                              onsubmit="return confirm(<?= e(json_encode('Remover a categoria "' . $c['nome'] . '"? Esta ação não pode ser desfeita.')); ?>);">
                            <input type="hidden" name="id" value="<?= (int)$c['id']; ?>">
                            <button type="submit" class="btn-remover">Remover</button>
                        </form>
                    </td>
                </tr>

            <?php endforeach; ?>

        <?php else: ?>

            <tr>
                <td colspan="5">Nenhuma categoria encontrada.</td>
            </tr>

        <?php endif; ?>

    </table>

</body>

</html>
