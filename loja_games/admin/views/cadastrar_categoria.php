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
$descricao = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nome = trim($_POST['nome'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');

    if ($nome === '' || mb_strlen($nome) > 50) {
        $erros[] = 'Informe um nome com até 50 caracteres.';
    }

    if (mb_strlen($descricao) > 255) {
        $erros[] = 'A descrição pode ter no máximo 255 caracteres.';
    }

    if (!$erros) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM categorias WHERE nome = :nome LIMIT 1");
            $stmt->execute([':nome' => $nome]);

            if ($stmt->fetch()) {
                $erros[] = 'Já existe uma categoria com esse nome.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO categorias (nome, descricao) VALUES (:nome, :descricao)");
                $stmt->execute([':nome' => $nome, ':descricao' => $descricao !== '' ? $descricao : null]);

                header('Location: ver_categorias.php?msg=cadastrado');
                exit;
            }
        } catch (PDOException $ex) {
            $erros[] = $ex->getCode() === '23000'
                ? 'Já existe uma categoria com esse nome.'
                : 'Não foi possível cadastrar. Rode o script criar_categorias_clientes.sql e tente novamente.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>GameStock - Cadastrar Categoria</title>

    <link rel="stylesheet" href="../public/css/style.css">
</head>

<body>

    <?php include __DIR__ . '/_navbar_admin.php'; ?>

    <div class="form-container">

        <h2>Cadastrar categoria</h2>

        <?php foreach ($erros as $erro): ?>
            <div class="alerta"><?= e($erro); ?></div>
        <?php endforeach; ?>

        <form method="POST">

            <div class="form-group">
                <label for="nome">Nome</label>
                <input type="text" id="nome" name="nome" maxlength="50" placeholder="Ex: RPG" value="<?= e($nome); ?>" required>
            </div>

            <div class="form-group">
                <label for="descricao">Descrição (opcional)</label>
                <textarea id="descricao" name="descricao" rows="3" maxlength="255"><?= e($descricao); ?></textarea>
            </div>

            <div class="form-buttons">
                <button type="submit" class="btn-primary">Cadastrar categoria</button>
                <a href="ver_categorias.php" class="btn-secondary">Voltar</a>
            </div>

        </form>

    </div>

</body>

</html>
