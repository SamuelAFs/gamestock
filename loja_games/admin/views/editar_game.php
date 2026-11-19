<?php
session_start();

// Só administradores logados acessam esta página
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '/../models/games.php';
require_once __DIR__ . '/_funcoes_upload.php';

$gameModel = new Game($pdo);

try {
    $categorias = $pdo->query("SELECT nome FROM categorias ORDER BY nome")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $ex) {
    $categorias = [];
}

$id = $_GET['id'] ?? null;

if (!$id) {
    header('Location: ver_games.php');
    exit;
}

$game = $gameModel->buscarPorId($id);

if (!$game) {
    echo "Jogo não encontrado.";
    exit;
}

$erroUpload = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $titulo = $_POST['titulo'];
    $descricao = $_POST['descricao'];
    $preco = $_POST['preco'];
    $estoque = $_POST['estoque'];
    $categoria = $_POST['categoria'];

    // Mantém a imagem atual, a menos que uma nova seja enviada
    $imagem = $game['imagem'];
    $upload = processar_upload_imagem('imagem', $titulo);

    if ($upload['erro']) {
        $erroUpload = $upload['erro'];
    } else {
        if ($upload['arquivo']) {
            $imagem = $upload['arquivo'];
        }

        $sucesso = $gameModel->editar(
            $id,
            $titulo,
            $descricao,
            $preco,
            $estoque,
            $categoria,
            $imagem
        );

        if ($sucesso) {
            header('Location: ver_games.php?msg=editado');
            exit;
        }

        echo "Erro ao editar o jogo.";
    }
}

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>GameStock - Editar Jogo</title>

    <link rel="stylesheet" href="../public/css/style.css">

</head>

<body>

    <?php include __DIR__ . '/_navbar_admin.php'; ?>

    <div class="form-container">

        <h2>✏️ Editar Jogo</h2>

        <?php if ($erroUpload): ?>
            <div class="alerta"><?= htmlspecialchars($erroUpload); ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="form-game">

            <div class="form-group">

                <label for="titulo">
                    Título
                </label>

                <input
                    type="text"
                    id="titulo"
                    name="titulo"
                    value="<?= htmlspecialchars($game['titulo']); ?>"
                    required
                >

            </div>


            <div class="form-group">

                <label for="descricao">
                    Descrição
                </label>

                <textarea
                    id="descricao"
                    name="descricao"
                    rows="4"
                ><?= htmlspecialchars($game['descricao']); ?></textarea>

            </div>


            <div class="form-row">

                <div class="form-group">

                    <label for="preco">
                        Preço
                    </label>

                    <input
                        type="number"
                        id="preco"
                        name="preco"
                        value="<?= $game['preco']; ?>"
                        step="0.01"
                        min="0"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="estoque">
                        Estoque
                    </label>

                    <input
                        type="number"
                        id="estoque"
                        name="estoque"
                        value="<?= $game['estoque']; ?>"
                        min="0"
                        required
                    >

                </div>

            </div>


            <div class="form-group">

                <label for="categoria">
                    Categoria
                </label>

                <?php
                    // Garante que a categoria atual apareça mesmo que não esteja cadastrada
                    $opcoes = $categorias;
                    if ($game['categoria'] !== '' && !in_array($game['categoria'], $opcoes, true)) {
                        $opcoes[] = $game['categoria'];
                    }
                ?>
                <select id="categoria" name="categoria" required>
                    <?php foreach ($opcoes as $cat): ?>
                        <option value="<?= htmlspecialchars($cat); ?>" <?= $cat === $game['categoria'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <small>Não encontrou? <a href="cadastrar_categoria.php">Cadastre uma categoria</a></small>

            </div>


            <div class="form-group">

                <label for="imagem">
                    Imagem
                </label>

                <img id="previa" class="previa" alt="Imagem atual"
                     src="../public/imagens/<?= htmlspecialchars($game['imagem']); ?>"
                     <?= $game['imagem'] ? '' : 'hidden'; ?>
                     onerror="this.hidden = true">

                <input
                    type="file"
                    id="imagem"
                    name="imagem"
                    accept="image/jpeg,image/png,image/webp"
                >

                <small>Para trocar a capa, escolha um arquivo novo (JPG, PNG ou WebP, até 3 MB). Sem arquivo, a atual é mantida.</small>

            </div>


            <div class="form-buttons">

                <button
                    type="submit"
                    class="btn-primary"
                >
                    Salvar Alterações
                </button>


                <a
                    href="ver_games.php"
                    class="btn-secondary"
                >
                    Cancelar
                </a>

            </div>

        </form>

    </div>


    <script>
        // Validação do formulário e prévia da imagem
        (function () {
            var form = document.getElementById('form-game');
            var input = document.getElementById('imagem');
            var previa = document.getElementById('previa');
            var maxBytes = 3 * 1024 * 1024;
            var tipos = ['image/jpeg', 'image/png', 'image/webp'];

            input.addEventListener('change', function () {
                var arquivo = input.files[0];
                if (!arquivo) { return; }

                if (tipos.indexOf(arquivo.type) === -1) {
                    alert('Escolha uma imagem JPG, PNG ou WebP.');
                    input.value = '';
                    return;
                }

                if (arquivo.size > maxBytes) {
                    alert('A imagem deve ter no máximo 3 MB.');
                    input.value = '';
                    return;
                }

                previa.src = URL.createObjectURL(arquivo);
                previa.hidden = false;
            });

            form.addEventListener('submit', function (e) {
                var titulo = form.titulo.value.trim();
                var preco = parseFloat(form.preco.value);
                var estoque = parseInt(form.estoque.value, 10);
                var erro = '';

                if (titulo === '') { erro = 'Informe o título do jogo.'; }
                else if (isNaN(preco) || preco < 0) { erro = 'Informe um preço válido (zero ou maior).'; }
                else if (isNaN(estoque) || estoque < 0) { erro = 'Informe um estoque válido (zero ou maior).'; }
                else if (form.categoria.value === '') { erro = 'Escolha uma categoria.'; }

                if (erro) {
                    e.preventDefault();
                    alert(erro);
                }
            });
        })();
    </script>

</body>

</html>