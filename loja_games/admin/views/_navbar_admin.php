<?php
// Topo das telas do painel admin (inclua depois de abrir o <body>)

if (!function_exists('e')) {
    function e($valor) {
        return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
    }
}

// Devolve o atributo style com uma capa de jogo para usar de fundo nos painéis
if (!function_exists('estilo_painel')) {
    function estilo_painel() {
        static $i = 0;

        $capas = $GLOBALS['capasAdmin'] ?? [];

        if (!$capas) {
            return '';
        }

        $arquivo = $capas[$i % count($capas)];
        $i++;

        return ' style="--capa: url(\'../public/imagens/' . rawurlencode($arquivo) . '\');"';
    }
}

// Capas que existem de verdade na pasta de imagens
$capasAdmin = [];

if (isset($pdo)) {
    $pastaImagens = __DIR__ . '/../public/imagens/';

    try {
        $imagens = $pdo->query("SELECT imagem FROM games WHERE imagem IS NOT NULL AND imagem <> '' ORDER BY RAND()")
                       ->fetchAll(PDO::FETCH_COLUMN);

        foreach ($imagens as $img) {
            if (is_file($pastaImagens . basename($img))) {
                $capasAdmin[] = basename($img);
            }

            if (count($capasAdmin) >= 8) {
                break;
            }
        }
    } catch (PDOException $ex) {
        // sem capas: o painel usa o fundo padrão
    }
}
?>
<header class="navbar">
    <a class="brand" href="../../index.php">GAME<span>STOCK</span></a>

    <nav class="nav-links">
        <a href="../../index.php">Loja</a>

        <div class="dropdown">
            <button type="button" class="dropdown-toggle">Administração</button>
            <div class="submenu">
                <a href="dashboard.php">Dashboard</a>
                <a href="ver_games.php">Gerenciar jogos</a>
                <a href="ver_categorias.php">Gerenciar categorias</a>
                <a href="ver_clientes.php">Gerenciar clientes</a>
                <a href="ver_pedidos.php">Gerenciar pedidos</a>
                <a href="ver_usuarios.php">Gerenciar usuários</a>
            </div>
        </div>
    </nav>

    <div class="nav-user">
        <span class="ola">Olá, <strong><?= e($_SESSION['usuario_nome'] ?? ''); ?></strong></span>
        <a class="btn-sair" href="../../logout.php">Sair</a>
    </div>
</header>
<?php if ($capasAdmin): ?>
<style>:root { --capa-banner: url('../public/imagens/<?= rawurlencode($capasAdmin[0]); ?>'); }</style>
<?php endif; ?>
