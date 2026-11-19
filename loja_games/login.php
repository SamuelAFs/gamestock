<?php
session_start();
require_once 'admin/config/conexao.php';
require_once __DIR__ . '/includes/funcoes.php';

// Quem já está logado vai direto para a loja
if (isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

$erro = '';
$msg = $_GET['msg'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    $stmt = $pdo->prepare("SELECT id, nome, email, senha, tipo FROM usuarios WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    // O banco usa SHA2(...,256), que gera o mesmo hash que hash('sha256', ...) do PHP
    if ($usuario && hash_equals($usuario['senha'], hash('sha256', $senha))) {

        session_regenerate_id(true);

        $_SESSION['usuario_id']   = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['usuario_tipo'] = $usuario['tipo'];

        header('Location: index.php');
        exit;
    }

    $erro = 'E-mail ou senha inválidos.';
}

$capas = capas_disponiveis($pdo, 9);
$capasPagina = [];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GameStock - Entrar</title>
    <link rel="stylesheet" href="admin/public/css/style.css">
</head>
<body>

    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <main class="auth">

        <div class="auth-arte">
            <div class="mosaico">
                <?php for ($i = 0; $i < 9; $i++): ?>
                    <div <?= $capas ? 'style="background-image: url(\'' . url_capa($capas[$i % count($capas)]) . '\');"' : ''; ?>></div>
                <?php endfor; ?>
            </div>

            <div class="auth-texto">
                <h2>Seu próximo jogo está aqui</h2>
                <p>Entre na sua conta para comprar, acompanhar seus pedidos e montar seu carrinho.</p>
            </div>
        </div>

        <div class="auth-form">
            <div class="form-container">

                <h2>Entrar na sua conta</h2>

                <?php if ($msg === 'conta_criada'): ?>
                    <div class="sucesso">Conta criada com sucesso! Faça login para continuar.</div>
                    <br>
                <?php endif; ?>

                <?php if ($erro): ?>
                    <div class="alerta"><?= e($erro); ?></div>
                <?php endif; ?>

                <form method="POST">

                    <div class="form-group">
                        <label for="email">E-mail</label>
                        <input type="email" id="email" name="email" placeholder="seu@email.com" required>
                    </div>

                    <div class="form-group">
                        <label for="senha">Senha</label>
                        <input type="password" id="senha" name="senha" placeholder="Sua senha" required>
                    </div>

                    <div class="form-buttons">
                        <button type="submit" class="btn-primary btn-grande">Entrar</button>
                    </div>

                </form>

                <p class="login-box">Ainda não tem conta?</p>
                <a href="criar_conta.php" class="btn-secondary btn-grande">Criar conta</a>

            </div>
        </div>

    </main>

    <?php include __DIR__ . '/includes/rodape.php'; ?>

</body>
</html>
