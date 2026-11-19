<?php
session_start();

// Só administradores logados acessam esta página
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '/../config/conexao.php';

$busca = trim($_GET['busca'] ?? '');
$filtroTipo = $_GET['tipo'] ?? '';
$ordem = $_GET['ordem'] ?? 'id';

$ordens = [
    'id'       => 'id ASC',
    'nome'     => 'nome ASC',
    'recentes' => 'created_at DESC, id DESC',
    'antigos'  => 'created_at ASC, id ASC',
];
$rotulosOrdem = [
    'id'       => 'Por código',
    'nome'     => 'Nome (A-Z)',
    'recentes' => 'Mais recentes',
    'antigos'  => 'Mais antigos',
];
$rotulosTipo = ['admin' => 'Administradores', 'user' => 'Clientes'];

if (!isset($ordens[$ordem])) {
    $ordem = 'id';
}
$msg = $_GET['msg'] ?? '';

$sql = "SELECT id, nome, email, tipo, created_at FROM usuarios WHERE 1=1";
$params = [];

if ($busca !== '') {
    $sql .= " AND (nome LIKE :busca_nome OR email LIKE :busca_email)";
    $params[':busca_nome'] = '%' . $busca . '%';
    $params[':busca_email'] = '%' . $busca . '%';
}

if (isset($rotulosTipo[$filtroTipo])) {
    $sql .= " AND tipo = :tipo";
    $params[':tipo'] = $filtroTipo;
}

$sql .= " ORDER BY " . $ordens[$ordem];

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

$meuId = (int)$_SESSION['usuario_id'];
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>GameStock - Usuários</title>

    <link rel="stylesheet" href="../public/css/style.css">
</head>

<body>

    <?php include __DIR__ . '/_navbar_admin.php'; ?>

    <div class="barra-admin">
        <h3>Gerenciar usuários</h3>

        <a href="cadastrar_usuario.php" class="btn-primary">+ Cadastrar novo usuário</a>
    </div>

    <?php if ($msg === 'cadastrado'): ?>
        <div class="sucesso aviso-admin">Usuário cadastrado com sucesso!</div>
    <?php elseif ($msg === 'editado'): ?>
        <div class="sucesso aviso-admin">Usuário atualizado com sucesso!</div>
    <?php elseif ($msg === 'removido'): ?>
        <div class="sucesso aviso-admin">Usuário removido.</div>
    <?php elseif ($msg === 'proprio'): ?>
        <div class="alerta aviso-admin">Você não pode remover a sua própria conta.</div>
    <?php elseif ($msg === 'com_pedidos'): ?>
        <div class="alerta aviso-admin">Este usuário já fez compras e não pode ser removido, para preservar o histórico de vendas.</div>
    <?php elseif ($msg === 'ultimo_admin'): ?>
        <div class="alerta aviso-admin">Não é possível remover o último administrador.</div>
    <?php elseif ($msg === 'erro_remover'): ?>
        <div class="alerta aviso-admin">Não foi possível remover o usuário. Tente novamente.</div>
    <?php endif; ?>

    <form method="GET" class="filtros">
        <input type="text" name="busca" placeholder="Buscar por nome ou e-mail..." value="<?= e($busca); ?>">

        <select name="tipo" onchange="this.form.submit()">
                <option value="">Todos os tipos</option>
                <?php foreach ($rotulosTipo as $valor => $rotulo): ?>
                    <option value="<?= e($valor); ?>" <?= $filtroTipo === (string)$valor ? 'selected' : ''; ?>><?= e($rotulo); ?></option>
                <?php endforeach; ?>
            </select>

        <select name="ordem" onchange="this.form.submit()">
                                <?php foreach ($rotulosOrdem as $valor => $rotulo): ?>
                    <option value="<?= e($valor); ?>" <?= $ordem === (string)$valor ? 'selected' : ''; ?>><?= e($rotulo); ?></option>
                <?php endforeach; ?>
            </select>

        <button type="submit" class="btn-primary">Filtrar</button>
        <a href="ver_usuarios.php" class="btn-secondary">Limpar</a>
    </form>

    <p class="resumo"><?= count($usuarios); ?> usuário(s) encontrado(s)</p>

    <table>

        <tr>
            <th>ID</th>
            <th>Nome</th>
            <th>E-mail</th>
            <th>Tipo</th>
            <th>Cadastro</th>
            <th>Ações</th>
        </tr>

        <?php if (count($usuarios) > 0): ?>

            <?php foreach ($usuarios as $u): ?>

                <tr>

                    <td><?= (int)$u['id']; ?></td>

                    <td>
                        <?= e($u['nome']); ?>
                        <?php if ((int)$u['id'] === $meuId): ?>
                            <small class="voce">(você)</small>
                        <?php endif; ?>
                    </td>

                    <td><?= e($u['email']); ?></td>

                    <td>
                        <span class="tag <?= $u['tipo'] === 'admin' ? 'tag-admin' : 'tag-user'; ?>">
                            <?= $u['tipo'] === 'admin' ? 'Administrador' : 'Cliente'; ?>
                        </span>
                    </td>

                    <td><?= $u['created_at'] ? date('d/m/Y', strtotime($u['created_at'])) : '-'; ?></td>

                    <td class="acoes">
                        <a href="editar_usuario.php?id=<?= (int)$u['id']; ?>" class="btn-primary">Editar</a>

                        <?php if ((int)$u['id'] !== $meuId): ?>
                            <form method="POST" action="remover_usuario.php" class="form-inline"
                                  onsubmit="return confirm(<?= e(json_encode('Remover o usuário "' . $u['nome'] . '"? Esta ação não pode ser desfeita.')); ?>);">
                                <input type="hidden" name="id" value="<?= (int)$u['id']; ?>">
                                <button type="submit" class="btn-remover">Remover</button>
                            </form>
                        <?php endif; ?>
                    </td>

                </tr>

            <?php endforeach; ?>

        <?php else: ?>

            <tr>
                <td colspan="6">Nenhum usuário encontrado.</td>
            </tr>

        <?php endif; ?>

    </table>

</body>

</html>
