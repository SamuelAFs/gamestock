<?php

define('servidor', 'localhost');
define('usuario', 'root');
define('senha', '');
define('bd', 'loja_games');

try {
    $pdo = new PDO(
        "mysql:host=" . servidor . ";dbname=" . bd . ";charset=utf8mb4",
        usuario,
        senha,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die('Erro! Não foi possível conectar o banco. Erro: ' . $e->getMessage());
}
