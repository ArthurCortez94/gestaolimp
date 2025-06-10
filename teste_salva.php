<?php
$pasta = __DIR__ . '/pdf_ordens/';
if (!file_exists($pasta)) mkdir($pasta, 0775, true);
if (file_put_contents($pasta . 'teste.pdf', 'Teste') === false) {
    die("Falha ao salvar");
} else {
    echo "Arquivo salvo com sucesso";
}
?>