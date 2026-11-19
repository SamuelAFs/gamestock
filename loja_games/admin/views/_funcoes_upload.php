<?php
// Recebe a imagem enviada pelo formulário e salva em admin/public/imagens/.
// Retorna ['arquivo' => nome salvo ou null, 'erro' => mensagem ou null].
function processar_upload_imagem($campo, $prefixo) {

    // Nenhum arquivo escolhido: não é erro (a imagem é opcional)
    if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] === UPLOAD_ERR_NO_FILE) {
        return ['arquivo' => null, 'erro' => null];
    }

    $f = $_FILES[$campo];

    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
        return ['arquivo' => null, 'erro' => 'A imagem é grande demais. O limite é 3 MB.'];
    }

    if ($f['error'] !== UPLOAD_ERR_OK) {
        return ['arquivo' => null, 'erro' => 'Falha no envio da imagem. Tente novamente.'];
    }

    if ($f['size'] > 3 * 1024 * 1024) {
        return ['arquivo' => null, 'erro' => 'A imagem é grande demais. O limite é 3 MB.'];
    }

    // Confere o conteúdo real do arquivo (não só a extensão)
    $permitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $info = @getimagesize($f['tmp_name']);

    if (!$info || !isset($permitidos[$info['mime']])) {
        return ['arquivo' => null, 'erro' => 'Envie uma imagem JPG, PNG ou WebP.'];
    }

    $pasta = __DIR__ . '/../public/imagens/';

    if (!is_dir($pasta) && !mkdir($pasta, 0755, true)) {
        return ['arquivo' => null, 'erro' => 'Não foi possível criar a pasta de imagens.'];
    }

    // Nome seguro e único: titulo-do-jogo-a1b2c3d4.jpg
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string)$prefixo)));
    $slug = trim($slug, '-');
    if ($slug === '') {
        $slug = 'jogo';
    }

    $nome = substr($slug, 0, 40) . '-' . bin2hex(random_bytes(4)) . '.' . $permitidos[$info['mime']];

    if (!move_uploaded_file($f['tmp_name'], $pasta . $nome)) {
        return ['arquivo' => null, 'erro' => 'Não foi possível salvar a imagem no servidor.'];
    }

    return ['arquivo' => $nome, 'erro' => null];
}
