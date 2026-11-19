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

$erroUpload = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $titulo = $_POST['titulo'];
    $descricao = $_POST['descricao'];
    $preco = $_POST['preco'];
    $estoque = $_POST['estoque'];
    $categoria = $_POST['categoria'];

    // Upload da imagem (opcional)
    $upload = processar_upload_imagem('imagem', $titulo);

    if ($upload['erro']) {
        $erroUpload = $upload['erro'];
    } else {
        $imagem = $upload['arquivo'] ?? '';

        $sucesso = $gameModel->cadastrar(
            $titulo,
            $descricao,
            $preco,
            $estoque,
            $categoria,
            $imagem
        );

        if ($sucesso) {
            header('Location: ver_games.php?msg=cadastrado');
            exit;
        }

        echo "Erro ao cadastrar o jogo.";
    }
}

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>GameStock - Cadastrar Jogo</title>

    <link rel="stylesheet" href="../public/css/style.css">
</head>

<body>

    <?php include __DIR__ . '/_navbar_admin.php'; ?>

    <div class="form-container">

        <h2>🎮 Cadastrar Jogo</h2>

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
                    placeholder="Digite o título do jogo"
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
                    placeholder="Digite a descrição do jogo"
                    rows="4"
                ></textarea>

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
                        step="0.01"
                        min="0"
                        placeholder="0.00"
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
                        min="0"
                        placeholder="0"
                        required
                    >

                </div>

            </div>


            <div class="form-group">

                <label for="categoria">
                    Categoria
                </label>

                <select id="categoria" name="categoria" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?= htmlspecialchars($cat); ?>"><?= htmlspecialchars($cat); ?></option>
                    <?php endforeach; ?>
                </select>

                <small>Não encontrou? <a href="cadastrar_categoria.php">Cadastre uma categoria</a></small>

            </div>


            <div class="form-group">

                <label for="imagem">
                    Imagem
                </label>

                <input
                    type="file"
                    id="imagem"
                    name="imagem"
                    accept="image/jpeg,image/png,image/webp"
                >

                <small>JPG, PNG ou WebP, até 3 MB. Ideal: imagem horizontal 16:9 (ex.: 1280 x 720).</small>

                <img id="previa" class="previa" alt="Prévia da imagem" hidden>

            </div>


            <div class="form-buttons">

                <button
                    type="submit"
                    class="btn-primary"
                >
                    Cadastrar Jogo
                </button>


                <a
                    href="ver_games.php"
                    class="btn-secondary"
                >
                    Voltar
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