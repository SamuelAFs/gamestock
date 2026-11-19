<?php
// Funções usadas pelas páginas públicas da loja

if (!function_exists('e')) {
    function e($valor) {
        return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
    }
}

// Cada categoria tem sempre a mesma cor (0-359)
if (!function_exists('matiz')) {
    function matiz($categoria) {
        return abs(crc32((string)$categoria)) % 360;
    }
}

// Caminho da capa a partir da pasta do projeto
if (!function_exists('url_capa')) {
    function url_capa($arquivo) {
        return 'admin/public/imagens/' . rawurlencode(basename((string)$arquivo));
    }
}

// A capa existe de verdade na pasta de imagens?
if (!function_exists('capa_existe')) {
    function capa_existe($arquivo) {
        return $arquivo !== null && $arquivo !== '' && is_file(__DIR__ . '/../admin/public/imagens/' . basename((string)$arquivo));
    }
}

// Lista de capas que existem de verdade (em ordem aleatória)
if (!function_exists('capas_disponiveis')) {
    function capas_disponiveis($pdo, $limite = 12) {
        $capas = [];

        try {
            $stmt = $pdo->query("SELECT imagem FROM games WHERE imagem IS NOT NULL AND imagem <> '' ORDER BY RAND()");

            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $img) {
                if (capa_existe($img)) {
                    $capas[$img] = $img;
                }

                if (count($capas) >= $limite) {
                    break;
                }
            }
        } catch (PDOException $ex) {
            // sem capas: as páginas usam o fundo padrão
        }

        return array_values($capas);
    }
}
