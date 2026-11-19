<?php
session_start();

// Só administradores logados acessam esta página
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '/../config/conexao.php';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: ver_usuarios.php');
    exit;
}

$stmt = $pdo->prepare("SELECT id, nome, email, tipo FROM usuarios WHERE id = :id");
$stmt->execute([':id' => $id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    header('Location: ver_usuarios.php');
    exit;
}

$editandoAMimMesmo = ($id === (int)$_SESSION['usuario_id']);
$erros = [];

$nome = $usuario['nome'];
$email = $usuario['email'];
$tipo = $usuario['tipo'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $tipo = $_POST['tipo'] ?? $usuario['tipo'];

    // O admin não pode mudar o próprio tipo (evita ficar sem administradores)
    if ($editandoAMimMesmo) {
        $tipo = $usuario['tipo'];
    }

    if ($nome === '' || mb_strlen($nome) > 100) {
        $erros[] = 'Informe um nome com até 100 caracteres.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
        $erros[] = 'Informe um e-mail válido.';
    }

    if ($senha !== '' && strlen($senha) < 6) {
        $erros[] = 'A nova senha precisa ter pelo menos 6 caracteres.';
    }

    if (!in_array($tipo, ['admin', 'user'], true)) {
        $erros[] = 'Tipo de usuário inválido.';
    }

    // E-mail não pode pertencer a outro usuário
    if (!$erros) {
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email AND id <> :id LIMIT 1");
        $stmt->execute([':email' => $email, ':id' => $id]);

        if ($stmt->fetch()) {
            $erros[] = 'Este e-mail já pertence a outro usuário.';
        }
    }

    // Não deixa o sistema sem nenhum administrador
    if (!$erros && $usuario['tipo'] === 'admin' && $tipo !== 'admin') {
        $admins = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE tipo = 'admin'")->fetchColumn();

        if ($admins <= 1) {
            $erros[] = 'Este é o último administrador, o tipo dele não pode ser alterado.';
        }
    }

    if (!$erros) {
        try {
            if ($senha !== '') {
                $stmt = $pdo->prepare("UPDATE usuarios SET nome = :nome, email = :email, tipo = :tipo, senha = :senha WHERE id = :id");
                $stmt->execute([
                    ':nome' => $nome, ':email' => $email, ':tipo' => $tipo,
                    ':senha' => hash('sha256', $senha), ':id' => $id,
                ]);
            } else {
                $stmt = $pdo->prepare("UPDATE usuarios SET nome = :nome, email = :email, tipo = :tipo WHERE id = :id");
                $stmt->execute([':nome' => $nome, ':email' => $email, ':tipo' => $tipo, ':id' => $id]);
            }

            // Mantém o nome do topo atualizado se o admin editou a si mesmo
            if ($editandoAMimMesmo) {
                $_SESSION['usuario_nome'] = $nome;
            }

            header('Location: ver_usuarios.php?msg=editado');
            exit;

        } catch (PDOException $ex) {
            $erros[] = $ex->getCode() === '23000'
                ? 'Este e-mail já pertence a outro usuário.'
                : 'Não foi possível salvar as alterações. Tente novamente.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>GameStock - Editar Usuário</title>

    <link rel="stylesheet" href="../public/css/style.css">
</head>

<body>

    <?php include __DIR__ . '/_navbar_admin.php'; ?>

    <div class="form-container">

        <h2>Editar usuário</h2>

        <?php foreach ($erros as $erro): ?>
            <div class="alerta"><?= e($erro); ?></div>
        <?php endforeach; ?>

        <form method="POST">

            <div class="form-group">
                <label for="nome">Nome</label>
                <input type="text" id="nome" name="nome" maxlength="100" value="<?= e($nome); ?>" required>
            </div>

            <div class="form-group">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" maxlength="150" value="<?= e($email); ?>" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="senha">Nova senha (opcional)</label>
                    <input type="password" id="senha" name="senha" minlength="6" placeholder="Deixe em branco para manter">
                </div>

                <div class="form-group">
                    <label for="tipo">Tipo</label>
                    <select id="tipo" name="tipo" <?= $editandoAMimMesmo ? 'disabled' : ''; ?>>
                        <option value="user" <?= $tipo === 'user' ? 'selected' : ''; ?>>Cliente</option>
                        <option value="admin" <?= $tipo === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                    </select>
                </div>
            </div>

            <?php if ($editandoAMimMesmo): ?>
                <p class="login-box">Você está editando a sua própria conta, por isso o tipo não pode ser alterado.</p>
            <?php endif; ?>

            <div class="form-buttons">
                <button type="submit" class="btn-primary">Salvar alterações</button>
                <a href="ver_usuarios.php" class="btn-secondary">Cancelar</a>
            </div>

        </form>

    </div>

</body>

</html>
