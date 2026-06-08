<?php

namespace App\Support;

final class ApiExtractionStatus
{
    public const NAO_SE_APLICA = 0;
    public const PENDENTE = 1;
    public const PROCESSANDO = 2;
    public const CONCLUIDO = 3;
    public const FALHA = 4;

    private function __construct()
    {
    }
}
