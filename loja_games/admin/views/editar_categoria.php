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
    header('Location: ver_categorias.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, nome, descricao FROM categorias WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $categoria = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $ex) {
    header('Location: ver_categorias.php?msg=erro');
    exit;
}

if (!$categoria) {
    header('Location: ver_categorias.php');
    exit;
}

$erros = [];
$nome = $categoria['nome'];
$descricao = (string)$categoria['descricao'];

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
        $stmt = $pdo->prepare("SELECT id FROM categorias WHERE nome = :nome AND id <> :id LIMIT 1");
        $stmt->execute([':nome' => $nome, ':id' => $id]);

        if ($stmt->fetch()) {
            $erros[] = 'Já existe outra categoria com esse nome.';
        }
    }

    if (!$erros) {
        try {
            $pdo->beginTransaction();

            $pdo->prepare("UPDATE categorias SET nome = :nome, descricao = :descricao WHERE id = :id")
                ->execute([':nome' => $nome, ':descricao' => $descricao !== '' ? $descricao : null, ':id' => $id]);

            // Se o nome mudou, os jogos dessa categoria acompanham o novo nome
            if ($nome !== $categoria['nome']) {
                $pdo->prepare("UPDATE games SET categoria = :novo WHERE categoria = :antigo")
                    ->execute([':novo' => $nome, ':antigo' => $categoria['nome']]);
            }

            $pdo->commit();

            header('Location: ver_categorias.php?msg=editado');
            exit;

        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erros[] = 'Não foi possível salvar as alterações. Tente novamente.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>GameStock - Editar Categoria</title>

    <link rel="stylesheet" href="../public/css/style.css">
</head>

<body>

    <?php include __DIR__ . '/_navbar_admin.php'; ?>

    <div class="form-container">

        <h2>Editar categoria</h2>

        <?php foreach ($erros as $erro): ?>
            <div class="alerta"><?= e($erro); ?></div>
        <?php endforeach; ?>

        <form method="POST">

            <div class="form-group">
                <label for="nome">Nome</label>
                <input type="text" id="nome" name="nome" maxlength="50" value="<?= e($nome); ?>" required>
            </div>

            <div class="form-group">
                <label for="descricao">Descrição (opcional)</label>
                <textarea id="descricao" name="descricao" rows="3" maxlength="255"><?= e($descricao); ?></textarea>
            </div>

            <p class="login-box">Se você mudar o nome, os jogos desta categoria passam a usar o nome novo.</p>

            <div class="form-buttons">
                <button type="submit" class="btn-primary">Salvar alterações</button>
                <a href="ver_categorias.php" class="btn-secondary">Cancelar</a>
            </div>

        </form>

    </div>

</body>

</html>
