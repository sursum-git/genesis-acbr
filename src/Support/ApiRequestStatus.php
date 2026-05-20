<?php

namespace App\Support;

final class ApiRequestStatus
{
    public const RECEBIDA = 0;
    public const ENFILEIRADA = 1;
    public const PROCESSANDO = 2;
    public const CONCLUIDA = 3;
    public const FALHA = 4;
    public const NAO_AUTORIZADA = 5;

    private function __construct()
    {
    }
}
