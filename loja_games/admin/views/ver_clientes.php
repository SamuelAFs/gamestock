<?php
session_start();

// Só administradores logados acessam esta página
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/_funcoes_cliente.php';

$msg = $_GET['msg'] ?? '';
$busca = trim($_GET['busca'] ?? '');
$filtroConta = $_GET['conta'] ?? '';
$ordem = $_GET['ordem'] ?? 'nome';

$ordens = [
    'nome'     => 'c.nome ASC',
    'recentes' => 'c.id DESC',
    'antigos'  => 'c.id ASC',
];
$rotulosOrdem = [
    'nome'     => 'Nome (A-Z)',
    'recentes' => 'Mais recentes',
    'antigos'  => 'Mais antigos',
];
$rotulosConta = ['com' => 'Com conta de login', 'sem' => 'Sem conta de login'];

if (!isset($ordens[$ordem])) {
    $ordem = 'nome';
}
$clientes = [];
$erroBanco = false;

try {
    $sql = "SELECT c.id, c.nome, c.cpf, c.telefone, c.endereco, c.created_at, u.email AS conta
            FROM clientes c
            LEFT JOIN usuarios u ON u.id = c.usuario_id
            WHERE 1=1";
    $params = [];

    if ($busca !== '') {
        $sql .= " AND (c.nome LIKE :b1 OR c.cpf LIKE :b2 OR c.telefone LIKE :b3)";
        $params[':b1'] = '%' . $busca . '%';
        $params[':b2'] = '%' . cpf_somente_digitos($busca) . '%';
        $params[':b3'] = '%' . $busca . '%';
    }

    if ($filtroConta === 'com') {
        $sql .= " AND c.usuario_id IS NOT NULL";
    } elseif ($filtroConta === 'sem') {
        $sql .= " AND c.usuario_id IS NULL";
    }

    $sql .= " ORDER BY " . $ordens[$ordem];

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $ex) {
    $erroBanco = true;
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>GameStock - Clientes</title>

    <link rel="stylesheet" href="../public/css/style.css">
</head>

<body>

    <?php include __DIR__ . '/_navbar_admin.php'; ?>

    <div class="barra-admin">
        <h3>Gerenciar clientes</h3>

        <a href="cadastrar_cliente.php" class="btn-primary">+ Cadastrar novo cliente</a>
    </div>

    <?php if ($erroBanco): ?>
        <div class="alerta aviso-admin">Não foi possível ler os clientes. Rode o script <strong>criar_categorias_clientes.sql</strong> no phpMyAdmin.</div>
    <?php endif; ?>

    <?php if ($msg === 'cadastrado'): ?>
        <div class="sucesso aviso-admin">Cliente cadastrado com sucesso!</div>
    <?php elseif ($msg === 'editado'): ?>
        <div class="sucesso aviso-admin">Cliente atualizado com sucesso!</div>
    <?php elseif ($msg === 'removido'): ?>
        <div class="sucesso aviso-admin">Cliente removido.</div>
    <?php elseif ($msg === 'erro'): ?>
        <div class="alerta aviso-admin">Não foi possível concluir a operação. Tente novamente.</div>
    <?php endif; ?>

    <form method="GET" class="filtros">
        <input type="text" name="busca" placeholder="Buscar por nome, CPF ou telefone..." value="<?= e($busca); ?>">

        <select name="conta" onchange="this.form.submit()">
                <option value="">Todas as contas</option>
                <?php foreach ($rotulosConta as $valor => $rotulo): ?>
                    <option value="<?= e($valor); ?>" <?= $filtroConta === (string)$valor ? 'selected' : ''; ?>><?= e($rotulo); ?></option>
                <?php endforeach; ?>
            </select>

        <select name="ordem" onchange="this.form.submit()">
                                <?php foreach ($rotulosOrdem as $valor => $rotulo): ?>
                    <option value="<?= e($valor); ?>" <?= $ordem === (string)$valor ? 'selected' : ''; ?>><?= e($rotulo); ?></option>
                <?php endforeach; ?>
            </select>

        <button type="submit" class="btn-primary">Filtrar</button>
        <a href="ver_clientes.php" class="btn-secondary">Limpar</a>
    </form>

    <p class="resumo"><?= count($clientes); ?> cliente(s) encontrado(s)</p>

    <table>

        <tr>
            <th>ID</th>
            <th>Nome</th>
            <th>CPF</th>
            <th>Telefone</th>
            <th>Endereço</th>
            <th>Conta de login</th>
            <th>Ações</th>
        </tr>

        <?php if (count($clientes) > 0): ?>

            <?php foreach ($clientes as $c): ?>

                <tr>
                    <td><?= (int)$c['id']; ?></td>
                    <td><?= e($c['nome']); ?></td>
                    <td><?= e(cpf_formatar($c['cpf'])); ?></td>
                    <td><?= e($c['telefone']); ?></td>
                    <td><?= e(mb_strimwidth((string)$c['endereco'], 0, 40, '...')); ?></td>
                    <td><?= $c['conta'] ? e($c['conta']) : '-'; ?></td>

                    <td class="acoes">
                        <a href="editar_cliente.php?id=<?= (int)$c['id']; ?>" class="btn-primary">Editar</a>

                        <form method="POST" action="remover_cliente.php" class="form-inline"
                              onsubmit="return confirm(<?= e(json_encode('Remover o cliente "' . $c['nome'] . '"? Esta ação não pode ser desfeita.')); ?>);">
                            <input type="hidden" name="id" value="<?= (int)$c['id']; ?>">
                            <button type="submit" class="btn-remover">Remover</button>
                        </form>
                    </td>
                </tr>

            <?php endforeach; ?>

        <?php else: ?>

            <tr>
                <td colspan="7">Nenhum cliente encontrado.</td>
            </tr>

        <?php endif; ?>

    </table>

</body>

</html>
