<?php
session_start();

// Só administradores logados acessam esta página
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/_funcoes_cliente.php';

$erros = [];
$nome = trim($_POST['nome'] ?? '');
$cpf = trim($_POST['cpf'] ?? '');
$telefone = trim($_POST['telefone'] ?? '');
$endereco = trim($_POST['endereco'] ?? '');
$usuarioId = (int)($_POST['usuario_id'] ?? 0);

try {
    // Só contas que ainda não estão ligadas a outro cliente
    $usuarios = $pdo->query("SELECT id, nome, email FROM usuarios
                             WHERE id NOT IN (SELECT usuario_id FROM clientes WHERE usuario_id IS NOT NULL)
                             ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $ex) {
    $usuarios = [];
    $erros[] = 'Tabela de clientes não encontrada. Rode o script criar_categorias_clientes.sql no phpMyAdmin.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$erros) {

    if ($nome === '' || mb_strlen($nome) > 100) {
        $erros[] = 'Informe um nome com até 100 caracteres.';
    }

    if (!cpf_valido($cpf)) {
        $erros[] = 'Informe um CPF válido.';
    }

    if (mb_strlen($telefone) > 20) {
        $erros[] = 'O telefone pode ter no máximo 20 caracteres.';
    }

    if (mb_strlen($endereco) > 255) {
        $erros[] = 'O endereço pode ter no máximo 255 caracteres.';
    }

    if (!$erros) {
        $cpfLimpo = cpf_somente_digitos($cpf);

        $stmt = $pdo->prepare("SELECT id FROM clientes WHERE cpf = :cpf LIMIT 1");
        $stmt->execute([':cpf' => $cpfLimpo]);
        if ($stmt->fetch()) {
            $erros[] = 'Já existe um cliente com esse CPF.';
        }

        if ($usuarioId > 0) {
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE id = :id");
            $stmt->execute([':id' => $usuarioId]);
            if (!$stmt->fetch()) {
                $erros[] = 'Conta de login não encontrada.';
            }

            $stmt = $pdo->prepare("SELECT id FROM clientes WHERE usuario_id = :id LIMIT 1");
            $stmt->execute([':id' => $usuarioId]);
            if ($stmt->fetch()) {
                $erros[] = 'Essa conta de login já está ligada a outro cliente.';
            }
        }
    }

    if (!$erros) {
        try {
            $stmt = $pdo->prepare("INSERT INTO clientes (nome, cpf, telefone, endereco, usuario_id)
                                   VALUES (:nome, :cpf, :telefone, :endereco, :usuario)");
            $stmt->execute([
                ':nome'     => $nome,
                ':cpf'      => $cpfLimpo,
                ':telefone' => $telefone !== '' ? $telefone : null,
                ':endereco' => $endereco !== '' ? $endereco : null,
                ':usuario'  => $usuarioId > 0 ? $usuarioId : null,
            ]);

            header('Location: ver_clientes.php?msg=cadastrado');
            exit;

        } catch (PDOException $ex) {
            $erros[] = $ex->getCode() === '23000'
                ? 'Já existe um cliente com esse CPF ou essa conta de login.'
                : 'Não foi possível cadastrar o cliente. Tente novamente.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>GameStock - Cadastrar Cliente</title>

    <link rel="stylesheet" href="../public/css/style.css">
</head>

<body>

    <?php include __DIR__ . '/_navbar_admin.php'; ?>

    <div class="form-container">

        <h2>Cadastrar cliente</h2>

        <?php foreach ($erros as $erro): ?>
            <div class="alerta"><?= e($erro); ?></div>
        <?php endforeach; ?>

        <form method="POST">

            <div class="form-group">
                <label for="nome">Nome</label>
                <input type="text" id="nome" name="nome" maxlength="100" value="<?= e($nome); ?>" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="cpf">CPF</label>
                    <input type="text" id="cpf" name="cpf" maxlength="14" placeholder="000.000.000-00" value="<?= e($cpf); ?>" required>
                </div>

                <div class="form-group">
                    <label for="telefone">Telefone</label>
                    <input type="text" id="telefone" name="telefone" maxlength="20" placeholder="(00) 00000-0000" value="<?= e($telefone); ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="endereco">Endereço</label>
                <input type="text" id="endereco" name="endereco" maxlength="255" value="<?= e($endereco); ?>">
            </div>

            <div class="form-group">
                <label for="usuario_id">Conta de login (opcional)</label>
                <select id="usuario_id" name="usuario_id">
                    <option value="0">Sem conta de login</option>
                    <?php foreach ($usuarios as $u): ?>
                        <option value="<?= (int)$u['id']; ?>" <?= $usuarioId === (int)$u['id'] ? 'selected' : ''; ?>>
                            <?= e($u['nome']); ?> (<?= e($u['email']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-buttons">
                <button type="submit" class="btn-primary">Cadastrar cliente</button>
                <a href="ver_clientes.php" class="btn-secondary">Voltar</a>
            </div>

        </form>

    </div>

</body>

</html>
