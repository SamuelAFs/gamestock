<?php
session_start();
require_once 'admin/config/conexao.php';
require_once __DIR__ . '/includes/funcoes.php';

$busca = trim($_GET['busca'] ?? '');
$categoria = $_GET['categoria'] ?? '';
$ordem = $_GET['ordem'] ?? 'titulo';

$ordens = [
    'titulo'      => 'titulo ASC',
    'recentes'    => 'id DESC',
    'menor_preco' => 'preco ASC, titulo ASC',
    'maior_preco' => 'preco DESC, titulo ASC',
];
$rotulosOrdem = [
    'titulo'      => 'Ordem alfabética',
    'recentes'    => 'Mais recentes',
    'menor_preco' => 'Menor preço',
    'maior_preco' => 'Maior preço',
];

if (!isset($ordens[$ordem])) {
    $ordem = 'titulo';
}

// Categorias para as abas
$categorias = $pdo->query("SELECT DISTINCT categoria FROM games WHERE categoria IS NOT NULL AND categoria <> '' ORDER BY categoria")
                  ->fetchAll(PDO::FETCH_COLUMN);

// Lista de jogos (busca + categoria + ordem)
$sql = "SELECT * FROM games WHERE 1=1";
$params = [];

if ($busca !== '') {
    $sql .= " AND titulo LIKE :busca";
    $params[':busca'] = '%' . $busca . '%';
}

if ($categoria !== '') {
    $sql .= " AND categoria = :categoria";
    $params[':categoria'] = $categoria;
}

$sql .= " ORDER BY " . $ordens[$ordem];

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$games = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filtrando = ($busca !== '' || $categoria !== '');

// Destaques: só jogos com estoque e com capa de verdade na pasta de imagens
$destaques = [];

if (!$filtrando) {
    $candidatos = $pdo->query("SELECT * FROM games WHERE estoque > 0 AND imagem IS NOT NULL AND imagem <> '' ORDER BY RAND()")
                      ->fetchAll(PDO::FETCH_ASSOC);

    foreach ($candidatos as $g) {
        if (capa_existe($g['imagem'])) {
            $destaques[] = $g;
        }

        if (count($destaques) >= 5) {
            break;
        }
    }
}

// Monta o link de um filtro mantendo os outros
function url_loja($busca, $categoria, $ordem) {
    $q = [];

    if ($busca !== '') { $q['busca'] = $busca; }
    if ($categoria !== '') { $q['categoria'] = $categoria; }
    if ($ordem !== 'titulo') { $q['ordem'] = $ordem; }

    return 'index.php' . ($q ? '?' . http_build_query($q) : '');
}

$capasPagina = []; // a página inicial usa o carrossel, não a faixa de fundo
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GameStock - Loja de Jogos</title>
    <link rel="stylesheet" href="admin/public/css/style.css">
</head>
<body>

    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <?php if ($destaques): ?>
        <section class="destaques">

            <div class="slides">
                <?php foreach ($destaques as $i => $d): ?>
                    <article class="slide <?= $i === 0 ? 'ativo' : ''; ?>"
                             style="background-image: url('<?= url_capa($d['imagem']); ?>');">
                        <div class="slide-conteudo">
                            <span class="selo">Em destaque</span>
                            <h2><?= e($d['titulo']); ?></h2>
                            <p class="slide-cat"><?= e($d['categoria']); ?></p>
                            <p class="slide-preco">R$ <?= number_format($d['preco'], 2, ',', '.'); ?></p>

                            <div class="slide-botoes">
                                <a class="btn-primary" href="jogo.php?id=<?= (int)$d['id']; ?>">Ver jogo</a>

                                <form method="POST" action="carrinho_acao.php">
                                    <input type="hidden" name="acao" value="adicionar">
                                    <input type="hidden" name="id" value="<?= (int)$d['id']; ?>">
                                    <input type="hidden" name="qtd" value="1">
                                    <button type="submit" class="btn-secondary">+ Carrinho</button>
                                </form>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="slide-lista">
                <?php foreach ($destaques as $i => $d): ?>
                    <button type="button" class="slide-item <?= $i === 0 ? 'ativo' : ''; ?>">
                        <img src="<?= url_capa($d['imagem']); ?>" alt="">
                        <span><?= e($d['titulo']); ?></span>
                    </button>
                <?php endforeach; ?>
            </div>

        </section>
    <?php endif; ?>

    <div class="conteudo">

        <div class="secao-topo">
            <h2 class="secao-titulo">
                <?php if ($busca !== ''): ?>
                    Resultados para "<?= e($busca); ?>"
                <?php elseif ($categoria !== ''): ?>
                    <?= e($categoria); ?>
                <?php else: ?>
                    Todos os jogos
                <?php endif; ?>
                <small class="voce">(<?= count($games); ?>)</small>
            </h2>

            <form method="GET" action="index.php">
                <?php if ($busca !== ''): ?><input type="hidden" name="busca" value="<?= e($busca); ?>"><?php endif; ?>
                <?php if ($categoria !== ''): ?><input type="hidden" name="categoria" value="<?= e($categoria); ?>"><?php endif; ?>

                <select name="ordem" onchange="this.form.submit()">
                    <?php foreach ($rotulosOrdem as $valor => $rotulo): ?>
                        <option value="<?= $valor; ?>" <?= $ordem === $valor ? 'selected' : ''; ?>><?= $rotulo; ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <nav class="chips">
            <a class="chip <?= $categoria === '' ? 'ativo' : ''; ?>" href="<?= e(url_loja($busca, '', $ordem)); ?>">Todos</a>

            <?php foreach ($categorias as $cat): ?>
                <a class="chip <?= $categoria === $cat ? 'ativo' : ''; ?>" href="<?= e(url_loja($busca, $cat, $ordem)); ?>"><?= e($cat); ?></a>
            <?php endforeach; ?>
        </nav>

        <div class="grid">

            <?php if (count($games) > 0): ?>

                <?php foreach ($games as $game): ?>

                    <?php
                        $h = matiz($game['categoria']);
                        $esgotado = $game['estoque'] <= 0;
                        $baixo = !$esgotado && $game['estoque'] <= 5;
                    ?>

                    <a class="card" href="jogo.php?id=<?= (int)$game['id']; ?>">
                        <div class="thumb" style="background: linear-gradient(135deg, hsl(<?= $h; ?>,12%,24%), hsl(<?= ($h + 50) % 360; ?>,12%,12%));">
                            <?php if (capa_existe($game['imagem'])): ?>
                                <img src="<?= url_capa($game['imagem']); ?>" alt="<?= e($game['titulo']); ?>">
                            <?php endif; ?>
                        </div>

                        <div class="info">
                            <span class="titulo"><?= e($game['titulo']); ?></span>

                            <div class="compra">
                                <span class="estoque <?= $esgotado ? 'esgotado' : ($baixo ? 'baixo' : ''); ?>">
                                    <?php if ($esgotado): ?>
                                        Esgotado
                                    <?php elseif ($baixo): ?>
                                        Últimas <?= (int)$game['estoque']; ?> unidades!
                                    <?php else: ?>
                                        <?= (int)$game['estoque']; ?> em estoque
                                    <?php endif; ?>
                                </span>

                                <span class="preco">R$ <?= number_format($game['preco'], 2, ',', '.'); ?></span>
                            </div>
                        </div>
                    </a>

                <?php endforeach; ?>

            <?php else: ?>

                <p class="vazio">Nenhum jogo encontrado. <a href="index.php">Ver todos</a></p>

            <?php endif; ?>

        </div>

    </div>

    <?php include __DIR__ . '/includes/rodape.php'; ?>

    <script>
        // Carrossel de destaques: troca ao passar o mouse, clicar ou automaticamente
        (function () {
            var slides = [].slice.call(document.querySelectorAll('.slide'));
            var itens = [].slice.call(document.querySelectorAll('.slide-item'));
            var caixa = document.querySelector('.destaques');

            if (slides.length < 2 || !caixa) { return; }

            var atual = 0;
            var timer = null;

            function ir(i) {
                slides[atual].classList.remove('ativo');
                itens[atual].classList.remove('ativo');
                atual = (i + slides.length) % slides.length;
                slides[atual].classList.add('ativo');
                itens[atual].classList.add('ativo');
            }

            function iniciar() {
                parar();
                timer = setInterval(function () { ir(atual + 1); }, 6000);
            }

            function parar() {
                if (timer) { clearInterval(timer); timer = null; }
            }

            itens.forEach(function (botao, i) {
                botao.addEventListener('mouseenter', function () { ir(i); });
                botao.addEventListener('click', function () { ir(i); });
            });

            caixa.addEventListener('mouseenter', parar);
            caixa.addEventListener('mouseleave', iniciar);

            iniciar();
        })();
    </script>

</body>
</html>
