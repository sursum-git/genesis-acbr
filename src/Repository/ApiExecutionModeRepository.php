<?php

namespace App\Repository;

use Doctrine\DBAL\Connection;
use Throwable;

final class ApiExecutionModeRepository
{
    public function __construct(private readonly Connection $auditConnection)
    {
    }

    public function findModeForOperation(string $operationName, string $path): ?string
    {
        try {
            $row = $this->auditConnection->fetchAssociative(
                <<<'SQL'
                SELECT c_modo_execucao
                FROM t99003
                WHERE log_ativo = TRUE
                  AND (
                    c_nome_operacao = :c_nome_operacao
                    OR c_caminho = :c_caminho
                    OR c_chave_configuracao = 'global'
                  )
                ORDER BY
                    CASE
                        WHEN c_nome_operacao = :c_nome_operacao THEN 1
                        WHEN c_caminho = :c_caminho THEN 2
                        WHEN c_chave_configuracao = 'global' THEN 3
                        ELSE 4
                    END,
                    id_t99003 ASC
                LIMIT 1
                SQL,
                [
                    'c_nome_operacao' => $operationName,
                    'c_caminho' => $path,
                ]
            );
        } catch (Throwable) {
            return null;
        }

        if ($row === false) {
            return null;
        }

        $mode = strtolower(trim((string) ($row['c_modo_execucao'] ?? '')));

        return in_array($mode, ['sync', 'async'], true) ? $mode : null;
    }
}
