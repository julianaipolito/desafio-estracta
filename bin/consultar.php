<?php

declare(strict_types=1);

use ProvaSpider\Spider\SefazBaSpider;
use ProvaSpider\ValueObject\Consulta;

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Execute composer install antes de consultar.\n");
    exit(1);
}
require $autoload;

if ($argc !== 2) {
    fwrite(STDERR, "Uso: php bin/consultar.php <cnpj>\n");
    exit(1);
}

try {
    $consulta = new Consulta($argv[1]);
    $cadastro = (new SefazBaSpider())->consultar($consulta);

    echo json_encode($cadastro, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
