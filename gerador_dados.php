<?php

/**
 * Gera um número de CPF válido e aleatório.
 * @return string O CPF gerado, apenas números.
 */
function generateCPF() {
    $n1 = rand(0, 9);
    $n2 = rand(0, 9);
    $n3 = rand(0, 9);
    $n4 = rand(0, 9);
    $n5 = rand(0, 9);
    $n6 = rand(0, 9);
    $n7 = rand(0, 9);
    $n8 = rand(0, 9);
    $n9 = rand(0, 9);

    // Calcula o primeiro dígito verificador (d1)
    $d1 = $n9 * 2 + $n8 * 3 + $n7 * 4 + $n6 * 5 + $n5 * 6 + $n4 * 7 + $n3 * 8 + $n2 * 9 + $n1 * 10;
    $d1 = 11 - ($d1 % 11);
    if ($d1 >= 10) {
        $d1 = 0;
    }

    // Calcula o segundo dígito verificador (d2)
    $d2 = $d1 * 2 + $n9 * 3 + $n8 * 4 + $n7 * 5 + $n6 * 6 + $n5 * 7 + $n4 * 8 + $n3 * 9 + $n2 * 10 + $n1 * 11;
    $d2 = 11 - ($d2 % 11);
    if ($d2 >= 10) {
        $d2 = 0;
    }

    return "$n1$n2$n3$n4$n5$n6$n7$n8$n9$d1$d2";
}

/**
 * Gera um nome e sobrenome aleatório a partir de listas.
 * @return string O nome completo gerado.
 */
function generateName() {
    $firstNames = ["Marcos", "Ana", "Carlos", "Julia", "Pedro", "Sofia", "Lucas", "Beatriz", "Mateus", "Larissa"];
    $lastNames = ["Silva", "Santos", "Oliveira", "Souza", "Rodrigues", "Ferreira", "Almeida", "Pereira", "Gomes", "Costa"];

    $firstName = $firstNames[array_rand($firstNames)];
    $lastName = $lastNames[array_rand($lastNames)];

    return "$firstName $lastName";
}

/**
 * Gera um e-mail aleatório, opcionalmente baseado em um nome.
 * @param string|null $name O nome para basear o e-mail.
 * @return string O endereço de e-mail gerado.
 */
function generateEmail($name = null) {
    $domains = ["gmail.com", "hotmail.com", "yahoo.com", "outlook.com", "teste.com"];
    $randomDomain = $domains[array_rand($domains)];

    if ($name) {
        // Limpa o nome para usar no e-mail (remove espaços, acentos e coloca em minúsculas)
        $baseName = strtolower(str_replace(' ', '.', iconv('UTF-8', 'ASCII//TRANSLIT', $name)));
        return $baseName . rand(10, 999) . "@" . $randomDomain;
    } else {
        // Se não tiver nome, gera um e-mail totalmente aleatório
        $randomString = substr(str_shuffle(str_repeat('0123456789abcdefghijklmnopqrstuvwxyz', 8)), 0, 8);
        return $randomString . "@" . $randomDomain;
    }
}

?>