<?php
// Barra de navegação das páginas públicas.
// Antes de incluir: session_start() e a conexão $pdo.
// Opcional: $capasPagina (lista de capas) e $buscaNav (texto da busca).

require_once __DIR__ . '/funcoes.php';

$logado = isset($_SESSION['usuario_id']);
$ehAdmin = $logado && ($_SESSION['usuario_tipo'] ?? '') === 'admin';
$qtdCarrinho = array_sum($_SESSION['carrinho'] ?? []);

if (!isset($capasPagina)) {
    $capasPagina = capas_disponiveis($pdo, 6);
}

$buscaNav = $buscaNav ?? trim($_GET['busca'] ?? '');
?>
<header class="navbar">
    <a class="brand" href="index.php">GAME<span>STOCK</span></a>

    <nav class="nav-links">
        <a href="index.php">Loja</a>
        <?php if ($logado): ?>
            <a href="meus_pedidos.php">Meus pedidos</a>
        <?php endif; ?>
        <?php if ($ehAdmin): ?>
            <div class="dropdown">
                <button type="button" class="dropdown-toggle">Administração</button>
                <div class="submenu">
                    <a href="admin/views/dashboard.php">Dashboard</a>
                    <a href="admin/views/ver_games.php">Gerenciar jogos</a>
                    <a href="admin/views/ver_categorias.php">Gerenciar categorias</a>
                    <a href="admin/views/ver_clientes.php">Gerenciar clientes</a>
                    <a href="admin/views/ver_pedidos.php">Gerenciar pedidos</a>
                    <a href="admin/views/ver_usuarios.php">Gerenciar usuários</a>
                </div>
            </div>
        <?php endif; ?>
    </nav>

    <form method="GET" action="index.php" class="busca-nav">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
        <input type="text" name="busca" placeholder="Buscar jogos" value="<?= e($buscaNav); ?>">
    </form>

    <div class="nav-user">
        <a class="carrinho-link" href="carrinho.php" title="Carrinho" aria-label="Carrinho"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg><?php if ($qtdCarrinho > 0): ?><span class="contador"><?= (int)$qtdCarrinho; ?></span><?php endif; ?></a>
        <?php if ($logado): ?>
            <span class="ola">Olá, <strong><?= e($_SESSION['usuario_nome']); ?></strong></span>
            <a class="btn-sair" href="logout.php">Sair</a>
        <?php else: ?>
            <a class="btn-entrar" href="login.php">Entrar</a>
        <?php endif; ?>
    </div>
</header>
<?php if (!empty($capasPagina)): ?>
<style>:root { --capa-banner: url('<?= url_capa($capasPagina[0]); ?>'); }</style>
<?php endif; ?>
