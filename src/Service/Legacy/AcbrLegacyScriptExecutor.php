<?php

namespace App\Service\Legacy;

use App\Http\Exception\AcbrLegacyApiException;
use Symfony\Component\HttpFoundation\RequestStack;

final class AcbrLegacyScriptExecutor
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function execute(string $scriptRelativePath, string $metodo, array $payload = [], ?string $iniProfile = null): array
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null) {
            throw new AcbrLegacyApiException('Requisição HTTP atual indisponível para execução do legado ACBr.');
        }

        $baseUrl = $request->getSchemeAndHttpHost();
        $scriptPath = '/' . ltrim($scriptRelativePath, '/');
        $postData = array_merge(['metodo' => $metodo], $payload);
        $resolvedIniProfile = $this->resolveIniProfile($iniProfile);
        if ($resolvedIniProfile !== null && !isset($postData['ACBrIniProfile'])) {
            $postData['ACBrIniProfile'] = $resolvedIniProfile;
        }

        $curl = curl_init($baseUrl . $scriptPath);
        if ($curl === false) {
            throw new AcbrLegacyApiException('Falha ao inicializar cURL para o legado ACBr.');
        }

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $responseBody = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($responseBody === false) {
            throw new AcbrLegacyApiException($curlError !== '' ? $curlError : 'Falha ao chamar o endpoint legado ACBr.');
        }

        $responseBody = trim((string) $responseBody);
        $decoded = json_decode($responseBody, true);

        if ($httpCode >= 400) {
            if (is_array($decoded) && isset($decoded['mensagem'])) {
                throw new AcbrLegacyApiException((string) $decoded['mensagem']);
            }

            throw new AcbrLegacyApiException($responseBody !== '' ? $responseBody : "Falha HTTP {$httpCode} ao chamar o legado ACBr.");
        }

        if (is_array($decoded)) {
            return $decoded;
        }

        return $responseBody === '' ? ['mensagem' => 'Operação executada sem conteúdo de resposta.'] : ['mensagem' => $responseBody, 'raw' => $responseBody];
    }

    private function resolveIniProfile(?string $iniProfile): ?string
    {
        $request = $this->requestStack->getCurrentRequest();
        $iniProfile ??= $request?->headers->get('X-ACBr-Ini-Profile');
        $iniProfile ??= is_string($request?->query->get('ACBrIniProfile')) ? (string) $request?->query->get('ACBrIniProfile') : null;
        $iniProfile ??= is_string($request?->request->get('ACBrIniProfile')) ? (string) $request?->request->get('ACBrIniProfile') : null;

        if ($iniProfile === null || trim($iniProfile) === '') {
            return null;
        }

        $iniProfile = trim($iniProfile);
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/', $iniProfile) || str_contains($iniProfile, '..') || str_contains($iniProfile, '/') || str_contains($iniProfile, '\\')) {
            throw new AcbrLegacyApiException('Perfil INI invalido. Use apenas letras, numeros, ponto, hifen ou underline.');
        }

        return $iniProfile;
    }
}
