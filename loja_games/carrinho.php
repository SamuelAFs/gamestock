<?php
session_start();
require_once 'admin/config/conexao.php';
require_once __DIR__ . '/includes/funcoes.php';

$carrinho = $_SESSION['carrinho'] ?? [];
if (!is_array($carrinho)) {
    $carrinho = [];
}

$itens = [];
$total = 0.0;
$temProblema = false;

if ($carrinho) {
    $ids = array_map('intval', array_keys($carrinho));
    $marcas = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $pdo->prepare("SELECT id, titulo, preco, estoque, imagem FROM games WHERE id IN ($marcas)");
    $stmt->execute($ids);

    $encontrados = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $g) {
        $encontrados[(int)$g['id']] = $g;
    }

    foreach ($ids as $id) {
        // Jogo removido do catálogo: sai do carrinho
        if (!isset($encontrados[$id])) {
            unset($_SESSION['carrinho'][$id]);
            continue;
        }

        $g = $encontrados[$id];
        $qtd = (int)$carrinho[$id];
        $subtotal = $qtd * $g['preco'];
        $semEstoque = $g['estoque'] < $qtd;

        if ($semEstoque) {
            $temProblema = true;
        }

        $total += $subtotal;
        $itens[] = ['jogo' => $g, 'qtd' => $qtd, 'subtotal' => $subtotal, 'semEstoque' => $semEstoque];
    }
}

$msg = $_GET['msg'] ?? '';
$erroEstoque = $_SESSION['carrinho_erro'] ?? '';
unset($_SESSION['carrinho_erro']);

$qtdTotal = array_sum($_SESSION['carrinho'] ?? []);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GameStock - Carrinho</title>
    <link rel="stylesheet" href="admin/public/css/style.css">
</head>
<body>

    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <section class="hero">
        <h1>Meu carrinho</h1>
        <p><?= (int)$qtdTotal; ?> item(ns)</p>
    </section>

    <?php if ($msg === 'limite'): ?>
        <div class="alerta aviso-admin">A quantidade foi ajustada ao estoque disponível.</div>
    <?php endif; ?>

    <?php if ($erroEstoque): ?>
        <div class="alerta aviso-admin"><?= e($erroEstoque); ?></div>
    <?php endif; ?>

    <?php if (count($itens) > 0): ?>

        <table>

            <tr>
                <th>Jogo</th>
                <th>Preço</th>
                <th>Quantidade</th>
                <th>Subtotal</th>
                <th>Ações</th>
            </tr>

            <?php foreach ($itens as $item): ?>
                <?php $g = $item['jogo']; ?>

                <tr>
                    <td>
                        <div class="item-jogo">
                            <?php if (capa_existe($g['imagem'])): ?>
                                <img class="mini" src="<?= url_capa($g['imagem']); ?>" alt="">
                            <?php endif; ?>
                            <div>
                                <a href="jogo.php?id=<?= (int)$g['id']; ?>"><?= e($g['titulo']); ?></a>
                                <?php if ($item['semEstoque']): ?>
                                    <br><small class="aviso-estoque">Estoque insuficiente: restam <?= (int)$g['estoque']; ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>

                    <td>R$ <?= number_format($g['preco'], 2, ',', '.'); ?></td>

                    <td>
                        <form method="POST" action="carrinho_acao.php" class="form-inline form-qtd">
                            <input type="hidden" name="acao" value="atualizar">
                            <input type="hidden" name="id" value="<?= (int)$g['id']; ?>">
                            <input type="number" name="qtd" min="0" max="<?= max(1, (int)$g['estoque']); ?>" value="<?= (int)$item['qtd']; ?>">
                            <button type="submit" class="btn-secondary">Atualizar</button>
                        </form>
                    </td>

                    <td>R$ <?= number_format($item['subtotal'], 2, ',', '.'); ?></td>

                    <td class="acoes">
                        <form method="POST" action="carrinho_acao.php" class="form-inline">
                            <input type="hidden" name="acao" value="remover">
                            <input type="hidden" name="id" value="<?= (int)$g['id']; ?>">
                            <button type="submit" class="btn-remover">Remover</button>
                        </form>
                    </td>
                </tr>

            <?php endforeach; ?>

        </table>

        <div class="resumo-carrinho">
            <div class="total">
                <span>Total</span>
                <strong>R$ <?= number_format($total, 2, ',', '.'); ?></strong>
            </div>

            <div class="acoes-carrinho">
                <a href="index.php" class="btn-secondary">Continuar comprando</a>

                <form method="POST" action="carrinho_acao.php" class="form-inline"
                      onsubmit="return confirm('Esvaziar o carrinho?');">
                    <input type="hidden" name="acao" value="limpar">
                    <button type="submit" class="btn-secondary">Esvaziar carrinho</button>
                </form>

                <?php if ($logado): ?>
                    <form method="POST" action="finalizar_compra.php" class="form-inline">
                        <button type="submit" class="btn-comprar" <?= $temProblema ? 'disabled' : ''; ?>>Finalizar compra</button>
                    </form>
                <?php else: ?>
                    <a href="login.php" class="btn-comprar">Entrar para finalizar</a>
                <?php endif; ?>
            </div>
        </div>

    <?php else: ?>

        <p class="vazio">Seu carrinho está vazio. <a href="index.php">Ver jogos</a></p>

    <?php endif; ?>

    <?php include __DIR__ . '/includes/rodape.php'; ?>

</body>
</html>
