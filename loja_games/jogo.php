<?php
session_start();
require_once 'admin/config/conexao.php';
require_once __DIR__ . '/includes/funcoes.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM games WHERE id = :id");
$stmt->execute([':id' => $id]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);

// Mensagens vindas do carrinho e da compra direta
$msg = $_GET['msg'] ?? '';

// Jogos relacionados (mesma categoria)
$relacionados = [];

if ($game) {
    $stmt = $pdo->prepare("SELECT id, titulo, preco, estoque, categoria, imagem FROM games WHERE categoria = :cat AND id <> :id ORDER BY RAND() LIMIT 4");
    $stmt->execute([':cat' => $game['categoria'], ':id' => $game['id']]);
    $relacionados = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$capasPagina = []; // esta página usa a capa do próprio jogo como fundo

$esgotado = $game && $game['estoque'] <= 0;
$baixo = $game && !$esgotado && $game['estoque'] <= 5;
$temCapa = $game && capa_existe($game['imagem']);
$h = $game ? matiz($game['categoria']) : 0;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $game ? e($game['titulo']) . ' - ' : ''; ?>GameStock</title>
    <link rel="stylesheet" href="admin/public/css/style.css">
</head>
<body>

    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <?php if (!$game): ?>

        <p class="vazio">Jogo não encontrado. <a href="index.php">Voltar à loja</a></p>

    <?php else: ?>

        <section class="jogo-hero" <?= $temCapa ? 'style="--capa: url(\'' . url_capa($game['imagem']) . '\');"' : ''; ?>>
            <div class="jogo-hero-conteudo">

                <div class="migalhas">
                    <a href="index.php">Loja</a> &rsaquo;
                    <a href="index.php?categoria=<?= urlencode($game['categoria']); ?>"><?= e($game['categoria']); ?></a> &rsaquo;
                    <?= e($game['titulo']); ?>
                </div>

                <h1 class="jogo-titulo"><?= e($game['titulo']); ?></h1>

                <div class="jogo-grid">

                    <div class="jogo-principal">
                        <div class="capa-grande" style="background: linear-gradient(135deg, hsl(<?= $h; ?>,12%,24%), hsl(<?= ($h + 50) % 360; ?>,12%,12%));">
                            <?php if ($temCapa): ?>
                                <img src="<?= url_capa($game['imagem']); ?>" alt="<?= e($game['titulo']); ?>">
                            <?php endif; ?>
                        </div>

                        <section class="bloco">
                            <h3>Sobre este jogo</h3>
                            <p class="texto"><?= nl2br(e($game['descricao'])); ?></p>
                        </section>

                        <section class="bloco">
                            <h3>Detalhes</h3>
                            <table class="ficha">
                                <tr><th>Categoria</th><td><?= e($game['categoria']); ?></td></tr>
                                <tr><th>Código</th><td>#<?= (int)$game['id']; ?></td></tr>
                                <tr>
                                    <th>Disponibilidade</th>
                                    <td>
                                        <span class="estoque <?= $esgotado ? 'esgotado' : ($baixo ? 'baixo' : ''); ?>">
                                            <?php if ($esgotado): ?>
                                                Esgotado
                                            <?php elseif ($baixo): ?>
                                                Últimas <?= (int)$game['estoque']; ?> unidades!
                                            <?php else: ?>
                                                <?= (int)$game['estoque']; ?> em estoque
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr><th>Cadastrado em</th><td><?= date('d/m/Y', strtotime($game['created_at'])); ?></td></tr>
                            </table>
                        </section>
                    </div>

                    <aside class="caixa-compra">

                        <?php if ($msg === 'ok'): ?>
                            <div class="sucesso">Jogo adquirido com sucesso! <a href="meus_pedidos.php">Ver meus pedidos</a></div>
                        <?php elseif ($msg === 'carrinho'): ?>
                            <div class="sucesso">Adicionado ao carrinho! <a href="carrinho.php">Ver carrinho</a></div>
                        <?php elseif ($msg === 'carrinho_limite'): ?>
                            <div class="alerta">Você já tem todas as unidades disponíveis no carrinho. <a href="carrinho.php">Ver carrinho</a></div>
                        <?php elseif ($msg === 'esgotado'): ?>
                            <div class="alerta">Este jogo acabou de esgotar.</div>
                        <?php elseif ($msg === 'erro'): ?>
                            <div class="alerta">Não foi possível concluir a aquisição. Tente novamente.</div>
                        <?php endif; ?>

                        <span class="selo"><?= e($game['categoria']); ?></span>

                        <span class="preco-grande">R$ <?= number_format($game['preco'], 2, ',', '.'); ?></span>

                        <?php if ($esgotado): ?>
                            <span class="btn-comprar btn-grande desabilitado">Indisponível</span>

                        <?php else: ?>
                            <?php if ($logado): ?>
                                <form method="POST" action="adquirir.php">
                                    <input type="hidden" name="id" value="<?= (int)$game['id']; ?>">
                                    <button type="submit" class="btn-comprar btn-grande">Adquirir agora</button>
                                </form>
                            <?php else: ?>
                                <a class="btn-comprar btn-grande" href="login.php">Entrar para adquirir</a>
                            <?php endif; ?>

                            <form method="POST" action="carrinho_acao.php">
                                <input type="hidden" name="acao" value="adicionar">
                                <input type="hidden" name="id" value="<?= (int)$game['id']; ?>">
                                <input type="hidden" name="qtd" value="1">
                                <button type="submit" class="btn-secondary btn-grande">Adicionar ao carrinho</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($ehAdmin): ?>
                            <a class="btn-primary btn-grande" href="admin/views/editar_game.php?id=<?= (int)$game['id']; ?>">Editar este jogo</a>
                        <?php endif; ?>

                        <a class="btn-secondary btn-grande" href="index.php">Continuar navegando</a>

                    </aside>

                </div>
            </div>
        </section>

        <?php if (count($relacionados) > 0): ?>
            <div class="jogo-extra">
                <div class="secao-topo">
                    <h2 class="secao-titulo">Mais em <?= e($game['categoria']); ?></h2>
                </div>

                <div class="grid">
                    <?php foreach ($relacionados as $rel): ?>
                        <?php $hr = matiz($rel['categoria']); ?>

                        <a class="card" href="jogo.php?id=<?= (int)$rel['id']; ?>">
                            <div class="thumb" style="background: linear-gradient(135deg, hsl(<?= $hr; ?>,12%,24%), hsl(<?= ($hr + 50) % 360; ?>,12%,12%));">
                                <?php if (capa_existe($rel['imagem'])): ?>
                                    <img src="<?= url_capa($rel['imagem']); ?>" alt="<?= e($rel['titulo']); ?>">
                                <?php endif; ?>
                            </div>

                            <div class="info">
                                <span class="titulo"><?= e($rel['titulo']); ?></span>

                                <div class="compra">
                                    <span class="estoque <?= $rel['estoque'] <= 0 ? 'esgotado' : ''; ?>">
                                        <?= $rel['estoque'] <= 0 ? 'Esgotado' : (int)$rel['estoque'] . ' em estoque'; ?>
                                    </span>
                                    <span class="preco">R$ <?= number_format($rel['preco'], 2, ',', '.'); ?></span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>

    <?php include __DIR__ . '/includes/rodape.php'; ?>

</body>
</html>
