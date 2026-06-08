<?php

namespace App\ApiResource\AcbrCep;

final class AcbrCepConfiguracaoResource
{
    public function __construct(
        public ?string $usuario = '',
        public ?string $senha = '',
        public ?string $chaveAcesso = '',
        public ?string $webservice = '0',
        public ?string $mensagem = null,
        public ?array $resultado = null,
        public ?string $request_id = null,
        public ?string $status = null,
        public ?string $endpoint = null,
        public ?string $received_at = null,
    ) {
        $this->request_id ??= $this->extractString($resultado, 'request_id');
        $this->status ??= $this->extractString($resultado, 'status');
        $this->endpoint ??= $this->extractString($resultado, 'endpoint');
        $this->received_at ??= $this->extractString($resultado, 'received_at');
    }

    private function extractString(?array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
