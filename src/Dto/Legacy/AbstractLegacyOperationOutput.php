<?php

declare(strict_types=1);

namespace App\Dto\Legacy;

abstract class AbstractLegacyOperationOutput
{
    public ?array $resultado;
    public ?string $mensagem;
    public ?string $request_id;
    public ?string $status;
    public ?string $endpoint;
    public ?string $received_at;

    public function __construct(
        ?array $resultado = null,
        ?string $mensagem = null,
        ?string $request_id = null,
        ?string $status = null,
        ?string $endpoint = null,
        ?string $received_at = null,
    ) {
        $this->resultado = $resultado;
        $this->mensagem = $this->normalizeMensagem($resultado, $mensagem);
        $this->request_id = $request_id ?? $this->extractString($resultado, 'request_id');
        $this->status = $status ?? $this->extractString($resultado, 'status');
        $this->endpoint = $endpoint ?? $this->extractString($resultado, 'endpoint');
        $this->received_at = $received_at ?? $this->extractString($resultado, 'received_at');
    }

    private function normalizeMensagem(?array $resultado, ?string $mensagem): ?string
    {
        if ($mensagem === null) {
            return null;
        }

        $mensagemResultado = $this->extractString($resultado, 'mensagem');

        if ($mensagemResultado !== null && $mensagemResultado === $mensagem) {
            return null;
        }

        return $mensagem;
    }

    private function extractString(?array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
