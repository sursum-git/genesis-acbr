<?php

declare(strict_types=1);

require dirname(__DIR__) . '/ACBrComum/ACBrComum.php';

putenv('ACBR_LIBRARY_SELECTION_SECRET');
$envFile = tempnam(sys_get_temp_dir(), 'acbr-secret-');
if ($envFile === false) {
    fwrite(STDERR, "Não foi possível preparar o arquivo de ambiente.\n");
    exit(1);
}

file_put_contents($envFile, "APP_ENV=prod\nACBR_LIBRARY_SELECTION_SECRET=segredo-de-teste\n");

try {
    if (AcbrLibrarySelectionSecret($envFile) !== 'segredo-de-teste') {
        fwrite(STDERR, "O segredo deve ser lido do arquivo .env quando não estiver no ambiente.\n");
        exit(1);
    }
} finally {
    unlink($envFile);
}

fwrite(STDOUT, "OK\n");
