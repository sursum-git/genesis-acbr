<?php

namespace App\Controller;

use App\Repository\SubscriberAdminRepository;
use App\Repository\WebhookRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class WebhookAdminController extends AbstractController
{
    public function __construct(
        private readonly WebhookRepository $repository,
        private readonly SubscriberAdminRepository $subscriberRepository,
    ) {
    }

    #[Route('/webhooks', name: 'app_webhooks', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $selectedWebhookId = (int) $request->query->get('webhook', 0);
        $selectedBindingId = (int) $request->query->get('binding', 0);
        $webhooks = [];
        $bindings = [];
        $deliveries = [];
        $selectedWebhook = null;
        $selectedBinding = null;
        $subscriberOptions = [];
        $loadError = null;

        try {
            $webhooks = $this->repository->listWebhooks();
            $bindings = $this->repository->listBindings();
            $deliveries = $this->repository->listRecentDeliveries();
            $selectedWebhook = $selectedWebhookId > 0 ? $this->repository->findWebhook($selectedWebhookId) : null;
            $selectedBinding = $selectedBindingId > 0 ? $this->repository->findBinding($selectedBindingId) : null;
            $subscriberOptions = $this->buildSubscriberOptions();
        } catch (Throwable $throwable) {
            $loadError = $throwable->getMessage();
        }

        return $this->render('admin/webhooks.html.twig', [
            'webhooks' => $webhooks,
            'bindings' => $bindings,
            'deliveries' => $deliveries,
            'selectedWebhook' => $selectedWebhook,
            'selectedBinding' => $selectedBinding,
            'subscriberOptions' => $subscriberOptions,
            'generatedSecret' => $request->query->get('secret'),
            'loadError' => $loadError,
        ]);
    }

    #[Route('/webhooks/salvar', name: 'app_webhooks_save', methods: ['POST'])]
    public function saveWebhook(Request $request): RedirectResponse
    {
        $id = (int) $request->request->get('id_t00003', 0);
        $headersJson = trim((string) $request->request->get('t_headers_json', ''));
        $variablesJson = trim((string) $request->request->get('t_variaveis_json', ''));
        $payloadRulesJson = trim((string) $request->request->get('t_success_payload_rules_json', ''));
        foreach ([
            'Headers extras' => ['value' => $headersJson, 'list' => false],
            'Variáveis de query/header/pathparam' => ['value' => $variablesJson, 'list' => false],
            'Condições de payload de sucesso' => ['value' => $payloadRulesJson, 'list' => true],
        ] as $label => $config) {
            if (!$this->isValidJsonShape((string) $config['value'], (bool) $config['list'])) {
                $this->addFlash('error', sprintf('%s devem estar em JSON valido.', $label));

                return $this->redirectToRoute('app_webhooks', $id > 0 ? ['webhook' => $id] : []);
            }
        }

        $secret = trim((string) $request->request->get('t_secret', ''));
        $generatedNewSecret = false;
        if ($id <= 0 && $secret === '') {
            $secret = bin2hex(random_bytes(24));
            $generatedNewSecret = true;
        }

        $payload = [
            'c_nome' => trim((string) $request->request->get('c_nome', '')),
            'c_url' => trim((string) $request->request->get('c_url', '')),
            'c_metodo_http' => trim((string) $request->request->get('c_metodo_http', 'POST')),
            't_headers_json' => $headersJson,
            't_secret' => $secret,
            'si_timeout_segundos' => (int) $request->request->get('si_timeout_segundos', 10),
            't_variaveis_json' => $variablesJson,
            'c_success_mode' => trim((string) $request->request->get('c_success_mode', 'status_only')),
            'c_success_status_codes' => trim((string) $request->request->get('c_success_status_codes', '200,201,202,204')),
            't_success_payload_rules_json' => $payloadRulesJson,
            'si_max_tentativas' => (int) $request->request->get('si_max_tentativas', 3),
            'si_intervalo_tentativas_segundos' => (int) $request->request->get('si_intervalo_tentativas_segundos', 300),
            'log_ativo' => $request->request->has('log_ativo'),
        ];

        if ($payload['c_nome'] === '' || $payload['c_url'] === '') {
            $this->addFlash('error', 'Nome e URL do webhook sao obrigatorios.');

            return $this->redirectToRoute('app_webhooks', $id > 0 ? ['webhook' => $id] : []);
        }

        try {
            $this->repository->saveWebhook($payload, $id > 0 ? $id : null);
            if ($generatedNewSecret) {
                $this->addFlash('success', 'Webhook criado. Secret gerado para configurar no destino: ' . $secret);
            } else {
                $this->addFlash('success', $id > 0 ? 'Webhook atualizado.' : 'Webhook criado.');
            }
        } catch (Throwable $throwable) {
            $this->addFlash('error', 'Falha ao salvar webhook: ' . $throwable->getMessage());
        }

        return $this->redirectToRoute('app_webhooks');
    }

    #[Route('/webhooks/vinculos/salvar', name: 'app_webhook_bindings_save', methods: ['POST'])]
    public function saveBinding(Request $request): RedirectResponse
    {
        $id = (int) $request->request->get('id_t00004', 0);
        $payload = [
            'c_assinante_identificador' => trim((string) $request->request->get('c_assinante_identificador', '')),
            't00003_id' => (int) $request->request->get('t00003_id', 0),
            'c_programa' => trim((string) $request->request->get('c_programa', '*')),
            'c_evento' => trim((string) $request->request->get('c_evento', 'request.completed')),
            'c_caminho' => trim((string) $request->request->get('c_caminho', '')),
            'c_modo_execucao' => trim((string) $request->request->get('c_modo_execucao', 'sync')),
            'log_ativo' => $request->request->has('log_ativo'),
        ];

        if ($payload['c_assinante_identificador'] === '' || $payload['t00003_id'] <= 0) {
            $this->addFlash('error', 'Selecione assinante e webhook para o vinculo.');

            return $this->redirectToRoute('app_webhooks', $id > 0 ? ['binding' => $id] : []);
        }
        if (!in_array($payload['c_modo_execucao'], ['sync', 'async'], true)) {
            $this->addFlash('error', 'Modo de execucao do vinculo deve ser sync ou async.');

            return $this->redirectToRoute('app_webhooks', $id > 0 ? ['binding' => $id] : []);
        }

        try {
            $this->repository->saveBinding($payload, $id > 0 ? $id : null);
            $this->addFlash('success', $id > 0 ? 'Vinculo atualizado.' : 'Vinculo criado.');
        } catch (Throwable $throwable) {
            $this->addFlash('error', 'Falha ao salvar vinculo de webhook: ' . $throwable->getMessage());
        }

        return $this->redirectToRoute('app_webhooks');
    }

    #[Route('/webhooks/gerar-secret', name: 'app_webhooks_generate_secret', methods: ['POST'])]
    public function generateSecret(): RedirectResponse
    {
        return $this->redirectToRoute('app_webhooks', ['secret' => bin2hex(random_bytes(24))]);
    }

    #[Route('/webhooks/{id}/regenerar-secret', name: 'app_webhooks_regenerate_secret', methods: ['POST'])]
    public function regenerateSecret(int $id): RedirectResponse
    {
        try {
            $secret = $this->repository->regenerateWebhookSecret($id);
            $this->addFlash('success', 'Secret do webhook regenerado. Configure este valor no destino: ' . $secret);
        } catch (Throwable $throwable) {
            $this->addFlash('error', 'Falha ao regenerar secret: ' . $throwable->getMessage());
        }

        return $this->redirectToRoute('app_webhooks', ['webhook' => $id]);
    }

    #[Route('/webhooks/entregas/{id}/reprocessar', name: 'app_webhooks_requeue_delivery', methods: ['POST'])]
    public function requeueDelivery(int $id): RedirectResponse
    {
        try {
            $this->repository->requeueDelivery($id);
            $this->addFlash('success', sprintf('Entrega %d reenfileirada para reprocessamento.', $id));
        } catch (Throwable $throwable) {
            $this->addFlash('error', 'Falha ao reenfileirar entrega: ' . $throwable->getMessage());
        }

        return $this->redirectToRoute('app_webhooks');
    }

    /**
     * @return list<string>
     */
    private function buildSubscriberOptions(): array
    {
        $options = [];
        foreach ($this->subscriberRepository->listSubscribers() as $subscriber) {
            $identifier = trim((string) ($subscriber['c_identificador'] ?? ''));
            if ($identifier === '') {
                continue;
            }

            $options[] = $identifier;
        }

        $options = array_values(array_unique($options));
        sort($options);

        return $options;
    }

    private function isValidJsonShape(string $json, bool $allowList): bool
    {
        if (trim($json) === '') {
            return true;
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return false;
        }

        return $allowList || !array_is_list($decoded);
    }
}
