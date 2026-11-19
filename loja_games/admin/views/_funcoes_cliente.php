<?php
// Funções de CPF usadas nas telas de clientes

function cpf_somente_digitos($cpf) {
    return preg_replace('/\D/', '', (string)$cpf);
}

function cpf_valido($cpf) {
    $cpf = cpf_somente_digitos($cpf);

    // 11 dígitos e não pode ser sequência repetida (111.111.111-11 etc.)
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }

    // Confere os dois dígitos verificadores
    for ($t = 9; $t < 11; $t++) {
        $soma = 0;
        for ($c = 0; $c < $t; $c++) {
            $soma += (int)$cpf[$c] * (($t + 1) - $c);
        }
        $digito = ((10 * $soma) % 11) % 10;

        if ((int)$cpf[$t] !== $digito) {
            return false;
        }
    }

    return true;
}

function cpf_formatar($cpf) {
    $c = cpf_somente_digitos($cpf);

    if (strlen($c) !== 11) {
        return (string)$cpf;
    }

    return substr($c, 0, 3) . '.' . substr($c, 3, 3) . '.' . substr($c, 6, 3) . '-' . substr($c, 9, 2);
}
