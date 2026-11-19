<?php
session_start();

// Só administradores logados acessam esta página
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '/../config/conexao.php';

$erros = [];
$nome = '';
$email = '';
$tipo = 'user';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $tipo = $_POST['tipo'] ?? 'user';

    if ($nome === '' || mb_strlen($nome) > 100) {
        $erros[] = 'Informe um nome com até 100 caracteres.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
        $erros[] = 'Informe um e-mail válido.';
    }

    if (strlen($senha) < 6) {
        $erros[] = 'A senha precisa ter pelo menos 6 caracteres.';
    }

    if (!in_array($tipo, ['admin', 'user'], true)) {
        $erros[] = 'Tipo de usuário inválido.';
    }

    if (!$erros) {
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);

        if ($stmt->fetch()) {
            $erros[] = 'Este e-mail já está cadastrado.';
        }
    }

    if (!$erros) {
        try {
            $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, tipo) VALUES (:nome, :email, :senha, :tipo)");
            $stmt->execute([
                ':nome'  => $nome,
                ':email' => $email,
                ':senha' => hash('sha256', $senha),
                ':tipo'  => $tipo,
            ]);

            header('Location: ver_usuarios.php?msg=cadastrado');
            exit;

        } catch (PDOException $ex) {
            $erros[] = $ex->getCode() === '23000'
                ? 'Este e-mail já está cadastrado.'
                : 'Não foi possível cadastrar o usuário. Tente novamente.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>GameStock - Cadastrar Usuário</title>

    <link rel="stylesheet" href="../public/css/style.css">
</head>

<body>

    <?php include __DIR__ . '/_navbar_admin.php'; ?>

    <div class="form-container">

        <h2>Cadastrar usuário</h2>

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
                    <label for="senha">Senha</label>
                    <input type="password" id="senha" name="senha" minlength="6" placeholder="Mínimo 6 caracteres" required>
                </div>

                <div class="form-group">
                    <label for="tipo">Tipo</label>
                    <select id="tipo" name="tipo">
                        <option value="user" <?= $tipo === 'user' ? 'selected' : ''; ?>>Cliente</option>
                        <option value="admin" <?= $tipo === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                    </select>
                </div>
            </div>

            <div class="form-buttons">
                <button type="submit" class="btn-primary">Cadastrar usuário</button>
                <a href="ver_usuarios.php" class="btn-secondary">Voltar</a>
            </div>

        </form>

    </div>

</body>

</html>
