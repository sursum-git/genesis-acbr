<?php

namespace App\Controller;

use App\Repository\ExecutionConfigRepository;
use App\Repository\WorkerCapacityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class OperationsAdminController extends AbstractController
{
    public function __construct(
        private readonly ExecutionConfigRepository $executionConfigRepository,
        private readonly WorkerCapacityRepository $workerCapacityRepository,
    ) {
    }

    #[Route('/configuracao-execucao', name: 'app_execution_config', methods: ['GET'])]
    public function executionConfig(Request $request): Response
    {
        $selectedId = (int) $request->query->get('id', 0);
        $selectedConfig = null;
        $configs = [];
        $loadError = null;

        try {
            $configs = $this->executionConfigRepository->listConfigs();
            $selectedConfig = $selectedId > 0 ? $this->executionConfigRepository->findConfig($selectedId) : null;
        } catch (Throwable $throwable) {
            $loadError = $throwable->getMessage();
        }

        return $this->render('admin/execution_config.html.twig', [
            'configs' => $configs,
            'selectedConfig' => $selectedConfig,
            'loadError' => $loadError,
            'tableGuide' => [
                ['table' => 't99001', 'description' => 'Requisição principal: request, response, status, tempos, token hash, snapshot do assinante e status de extração.'],
                ['table' => 't99002', 'description' => 'Tentativas do worker para requisições assíncronas.'],
                ['table' => 't99003', 'description' => 'Regras de execução síncrona/assíncrona por operação, caminho ou chave global.'],
                ['table' => 't99004', 'description' => 'Eventos operacionais ligados à requisição, incluindo fila, worker e webhook.'],
                ['table' => 't99007', 'description' => 'Execução da consulta DF-e: envelope, NSU de entrada, faixa de NSU e status técnico do retorno.'],
                ['table' => 't99008', 'description' => 'Documentos `docZip` retornados pela distribuição, com schema exato, XML bruto e hash do conteúdo.'],
                ['table' => 't99009-t99018', 'description' => 'Normalização dos documentos distribuídos: resumo e XML processado de NFe, eventos, itens, totais e inutilização.'],
            ],
        ]);
    }

    #[Route('/configuracao-execucao/salvar', name: 'app_execution_config_save', methods: ['POST'])]
    public function saveExecutionConfig(Request $request): RedirectResponse
    {
        $id = (int) $request->request->get('id', 0);
        $path = trim((string) $request->request->get('c_caminho', ''));
        $operation = trim((string) $request->request->get('c_nome_operacao', ''));
        $key = trim((string) $request->request->get('c_chave_configuracao', ''));
        if ($key === '') {
            $key = $operation !== '' ? 'op_' . preg_replace('/[^a-z0-9_]+/i', '_', strtolower($operation)) : '';
        }
        if ($key === '' && $path !== '') {
            $key = 'path_' . preg_replace('/[^a-z0-9_]+/i', '_', strtolower(trim($path, '/')));
        }
        if ($key === '' && $path === '' && $operation === '') {
            $this->addFlash('error', 'Informe ao menos caminho, operacao ou chave de configuracao.');

            return $this->redirectToRoute('app_execution_config');
        }

        $payload = [
            'c_chave_configuracao' => $key !== '' ? $key : 'global',
            'c_caminho' => $path,
            'c_nome_operacao' => $operation,
            'c_modo_execucao' => strtolower(trim((string) $request->request->get('c_modo_execucao', 'sync'))),
            'log_ativo' => $request->request->has('log_ativo'),
        ];

        if (!in_array($payload['c_modo_execucao'], ['sync', 'async'], true)) {
            $this->addFlash('error', 'Modo de execucao invalido.');

            return $this->redirectToRoute('app_execution_config', $id > 0 ? ['id' => $id] : []);
        }

        try {
            if ($payload['log_ativo'] && $this->executionConfigRepository->hasActiveConflict($payload, $id > 0 ? $id : null)) {
                $this->addFlash('error', 'Ja existe uma regra ativa com a mesma chave, operacao e caminho.');

                return $this->redirectToRoute('app_execution_config', $id > 0 ? ['id' => $id] : []);
            }

            $this->executionConfigRepository->save($payload, $id > 0 ? $id : null);
            $this->addFlash('success', $id > 0 ? 'Regra de execucao atualizada.' : 'Regra de execucao criada.');
        } catch (Throwable $throwable) {
            $this->addFlash('error', 'Falha ao salvar regra de execucao: ' . $throwable->getMessage());
        }

        return $this->redirectToRoute('app_execution_config');
    }

    #[Route('/capacidade-workers', name: 'app_worker_capacity', methods: ['GET'])]
    public function workerCapacity(Request $request): Response
    {
        $selectedId = (int) $request->query->get('id', 0);
        $selectedConfig = null;
        $configs = [];
        $currentConfig = null;
        $loadError = null;

        try {
            $configs = $this->workerCapacityRepository->listConfigs();
            $selectedConfig = $selectedId > 0 ? $this->workerCapacityRepository->findConfig($selectedId) : null;
            $currentConfig = $this->workerCapacityRepository->findCurrent();
        } catch (Throwable $throwable) {
            $loadError = $throwable->getMessage();
        }

        return $this->render('admin/worker_capacity.html.twig', [
            'configs' => $configs,
            'selectedConfig' => $selectedConfig,
            'currentConfig' => $currentConfig,
            'loadError' => $loadError,
        ]);
    }

    #[Route('/capacidade-workers/salvar', name: 'app_worker_capacity_save', methods: ['POST'])]
    public function saveWorkerCapacity(Request $request): RedirectResponse
    {
        $id = (int) $request->request->get('id', 0);
        $start = trim((string) $request->request->get('dt_inicio_vigencia', ''));
        $end = trim((string) $request->request->get('dt_fim_vigencia', ''));
        if ($start === '') {
            $this->addFlash('error', 'Data inicial de vigencia e obrigatoria.');

            return $this->redirectToRoute('app_worker_capacity', $id > 0 ? ['id' => $id] : []);
        }
        if ($end !== '' && strtotime($end) !== false && strtotime($start) !== false && strtotime($end) < strtotime($start)) {
            $this->addFlash('error', 'Data final nao pode ser anterior a inicial.');

            return $this->redirectToRoute('app_worker_capacity', $id > 0 ? ['id' => $id] : []);
        }

        $payload = [
            'qtd_workers' => (int) $request->request->get('qtd_workers', 1),
            'dt_inicio_vigencia' => $start,
            'dt_fim_vigencia' => $end,
            'log_ativo' => $request->request->has('log_ativo'),
            't_observacao' => trim((string) $request->request->get('t_observacao', '')),
        ];

        if ($payload['qtd_workers'] < 1) {
            $this->addFlash('error', 'Quantidade de workers deve ser maior ou igual a 1.');

            return $this->redirectToRoute('app_worker_capacity', $id > 0 ? ['id' => $id] : []);
        }

        try {
            if ($id === 0) {
                $this->workerCapacityRepository->closePreviousActiveRanges($payload);
            }

            if ($this->workerCapacityRepository->hasOverlap($payload, $id > 0 ? $id : null)) {
                $this->addFlash('error', 'Ja existe outra faixa ativa sobrepondo este periodo. Edite a faixa existente ou escolha um inicio/fim sem cruzamento.');

                return $this->redirectToRoute('app_worker_capacity', $id > 0 ? ['id' => $id] : []);
            }

            $this->workerCapacityRepository->save($payload, $id > 0 ? $id : null);
            $this->addFlash('success', $id > 0 ? 'Capacidade de workers atualizada.' : 'Capacidade de workers criada.');
        } catch (Throwable $throwable) {
            $this->addFlash('error', 'Falha ao salvar capacidade de workers: ' . $throwable->getMessage());
        }

        return $this->redirectToRoute('app_worker_capacity');
    }
}
