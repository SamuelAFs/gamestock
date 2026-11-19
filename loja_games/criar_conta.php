<?php
session_start();
require_once 'admin/config/conexao.php';
require_once __DIR__ . '/includes/funcoes.php';

// Quem já está logado não precisa criar conta
if (isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

$erros = [];
$nome = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $confirmar = $_POST['confirmar'] ?? '';

    if ($nome === '' || mb_strlen($nome) > 100) {
        $erros[] = 'Informe um nome com até 100 caracteres.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
        $erros[] = 'Informe um e-mail válido.';
    }

    if (strlen($senha) < 6) {
        $erros[] = 'A senha precisa ter pelo menos 6 caracteres.';
    }

    if ($senha !== $confirmar) {
        $erros[] = 'As senhas não conferem.';
    }

    // E-mail já cadastrado?
    if (!$erros) {
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);

        if ($stmt->fetch()) {
            $erros[] = 'Este e-mail já está cadastrado.';
        }
    }

    // Grava no banco (o tipo é sempre 'user', nunca vem do formulário)
    if (!$erros) {
        try {
            $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, tipo) VALUES (:nome, :email, :senha, 'user')");
            $stmt->execute([
                ':nome'  => $nome,
                ':email' => $email,
                ':senha' => hash('sha256', $senha),
            ]);

            header('Location: login.php?msg=conta_criada');
            exit;

        } catch (PDOException $ex) {
            $erros[] = $ex->getCode() === '23000'
                ? 'Este e-mail já está cadastrado.'
                : 'Não foi possível criar a conta. Tente novamente.';
        }
    }
}

$capas = capas_disponiveis($pdo, 9);
$capasPagina = [];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GameStock - Criar conta</title>
    <link rel="stylesheet" href="admin/public/css/style.css">
</head>
<body>

    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <main class="auth">

        <div class="auth-arte">
            <div class="mosaico">
                <?php for ($i = 0; $i < 9; $i++): ?>
                    <div <?= $capas ? 'style="background-image: url(\'' . url_capa($capas[($i + 3) % count($capas)]) . '\');"' : ''; ?>></div>
                <?php endfor; ?>
            </div>

            <div class="auth-texto">
                <h2>Crie sua conta grátis</h2>
                <p>Monte seu carrinho, finalize compras e acompanhe seus pedidos em um só lugar.</p>
            </div>
        </div>

        <div class="auth-form">
            <div class="form-container">

                <h2>Criar sua conta</h2>

                <?php foreach ($erros as $erro): ?>
                    <div class="alerta"><?= e($erro); ?></div>
                <?php endforeach; ?>

                <form method="POST">

                    <div class="form-group">
                        <label for="nome">Nome</label>
                        <input type="text" id="nome" name="nome" maxlength="100" placeholder="Seu nome" value="<?= e($nome); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">E-mail</label>
                        <input type="email" id="email" name="email" maxlength="150" placeholder="seu@email.com" value="<?= e($email); ?>" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="senha">Senha</label>
                            <input type="password" id="senha" name="senha" minlength="6" placeholder="Mínimo 6 caracteres" required>
                        </div>

                        <div class="form-group">
                            <label for="confirmar">Confirmar senha</label>
                            <input type="password" id="confirmar" name="confirmar" minlength="6" placeholder="Repita a senha" required>
                        </div>
                    </div>

                    <div class="form-buttons">
                        <button type="submit" class="btn-primary btn-grande">Criar conta</button>
                    </div>

                </form>

                <p class="login-box">Já tem uma conta?</p>
                <a href="login.php" class="btn-secondary btn-grande">Entrar</a>

            </div>
        </div>

    </main>

    <?php include __DIR__ . '/includes/rodape.php'; ?>

</body>
</html>
