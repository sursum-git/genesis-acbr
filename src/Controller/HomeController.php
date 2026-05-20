<?php

namespace App\Controller;

use App\Repository\ApiAuditDashboardRepository;
use App\Repository\ApiTestCatalogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public function __construct(
        private readonly ApiAuditDashboardRepository $auditRepository,
        private readonly ApiTestCatalogRepository $testRepository,
    ) {
    }

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->render('home/dashboard.html.twig', array_merge($this->buildDashboardContext(), [
            'shortcuts' => [
                [
                    'title' => 'Operação por módulo',
                    'description' => 'Docs, testes e auditoria separados por CEP, NFe e NFSe.',
                    'meta' => 'Página dedicada a fluxos por módulo',
                ],
                [
                    'title' => 'Documentação',
                    'description' => 'Entradas diretas para o API Platform geral e filtrado.',
                    'meta' => 'Somente navegação de docs',
                ],
                [
                    'title' => 'Monitoramento',
                    'description' => 'Falhas, testes recentes e atalhos para auditoria e catálogos.',
                    'meta' => 'Painel de acompanhamento operacional',
                ],
            ],
            'focusAreas' => [
                [
                    'title' => 'Operação por módulo',
                    'description' => 'Escolha CEP, NFe ou NFSe e entre nos três fluxos centrais de cada módulo.',
                    'badges' => ['Docs', 'Testes', 'Auditoria'],
                    'url' => '/index.php/operacao',
                ],
                [
                    'title' => 'Documentação',
                    'description' => 'Acesso limpo ao Swagger/API Platform sem misturar atalhos operacionais.',
                    'badges' => ['Todos', 'CEP', 'NFe', 'NFSe'],
                    'url' => '/index.php/apis',
                ],
                [
                    'title' => 'Demos legados',
                    'description' => 'Entrada separada para os aplicativos antigos ainda usados no runtime.',
                    'badges' => ['Boleto', 'CEP', 'Consulta CNPJ', 'GTIN', 'NFe', 'NFSe'],
                    'url' => '/index.php/demos',
                ],
                [
                    'title' => 'Monitoramento',
                    'description' => 'Falhas recentes, testes observados e acesso rápido aos catálogos de inspeção.',
                    'badges' => ['Falhas', 'Testes', 'Auditoria'],
                    'url' => '/index.php/monitoramento',
                ],
            ],
        ]));
    }

    #[Route('/operacao', name: 'app_operations', methods: ['GET'])]
    public function operations(): Response
    {
        return $this->render('home/focus_page.html.twig', [
            'title' => 'Operação por Módulo',
            'subtitle' => 'Cada card agrupa apenas os acessos operacionais do mesmo módulo: documentação, testes gravados e auditoria filtrada.',
            'items' => [
                [
                    'label' => 'CEP',
                    'description' => 'Operação principal do módulo CEP com docs, catálogo de testes e auditoria filtrada.',
                    'meta' => ['Docs', 'Testes', 'Auditoria'],
                    'actions' => [
                        ['label' => 'Docs', 'url' => '/index.php/docs/cep/', 'variant' => 'button-primary'],
                        ['label' => 'Testes', 'url' => '/index.php/catalogo-testes?grupo=cep', 'variant' => 'button-secondary'],
                        ['label' => 'Auditoria', 'url' => '/index.php/auditoria-requisicoes?programa=cep', 'variant' => 'button-tertiary'],
                    ],
                ],
                [
                    'label' => 'NFe',
                    'description' => 'Acesso direto aos fluxos mais usados de NFe sem passar por páginas misturadas.',
                    'meta' => ['Docs', 'Testes', 'Auditoria'],
                    'actions' => [
                        ['label' => 'Docs', 'url' => '/index.php/docs/nfe/', 'variant' => 'button-primary'],
                        ['label' => 'Testes', 'url' => '/index.php/catalogo-testes?grupo=nfe', 'variant' => 'button-secondary'],
                        ['label' => 'Auditoria', 'url' => '/index.php/auditoria-requisicoes?programa=nfe', 'variant' => 'button-tertiary'],
                    ],
                ],
                [
                    'label' => 'NFSe',
                    'description' => 'Entrada unificada do módulo NFSe para consulta, operação e acompanhamento.',
                    'meta' => ['Docs', 'Testes', 'Auditoria'],
                    'actions' => [
                        ['label' => 'Docs', 'url' => '/index.php/docs/nfse/', 'variant' => 'button-primary'],
                        ['label' => 'Testes', 'url' => '/index.php/catalogo-testes?grupo=nfse', 'variant' => 'button-secondary'],
                        ['label' => 'Auditoria', 'url' => '/index.php/auditoria-requisicoes?programa=nfse', 'variant' => 'button-tertiary'],
                    ],
                ],
                [
                    'label' => 'Catálogo de Programas',
                    'description' => 'Inventário técnico do backend para navegar por programa e histórico.',
                    'meta' => ['SQLite', 'Inventário'],
                    'url' => '/index.php/catalogo-programas',
                ],
            ],
            'metrics' => [
                ['label' => 'Módulos centrais', 'value' => '3'],
                ['label' => 'Catálogos', 'value' => '2'],
                ['label' => 'Painel auditável', 'value' => '1'],
            ],
        ]);
    }

    #[Route('/apis', name: 'app_apis', methods: ['GET'])]
    public function apis(): Response
    {
        return $this->render('home/focus_page.html.twig', [
            'title' => 'Documentação',
            'subtitle' => 'Página dedicada apenas aos acessos do API Platform, com recortes separados por módulo.',
            'items' => [
                ['label' => 'API Platform: Tudo', 'url' => '/index.php/docs/todos/', 'description' => 'Documentação completa de todas as APIs publicadas.', 'meta' => ['Visão geral']],
                ['label' => 'API Platform: CEP', 'url' => '/index.php/docs/cep/', 'description' => 'Endpoints e exemplos do módulo CEP.', 'meta' => ['Filtro CEP']],
                ['label' => 'API Platform: NFe', 'url' => '/index.php/docs/nfe/', 'description' => 'Consultas, envio, eventos e ferramentas de NFe.', 'meta' => ['Filtro NFe']],
                ['label' => 'API Platform: NFSe', 'url' => '/index.php/docs/nfse/', 'description' => 'Operações e grupos de endpoints da NFSe.', 'meta' => ['Filtro NFSe']],
            ],
            'metrics' => [
                ['label' => 'Visões de docs', 'value' => '4'],
            ],
        ]);
    }

    #[Route('/demos', name: 'app_demos', methods: ['GET'])]
    public function demos(): Response
    {
        return $this->render('home/focus_page.html.twig', [
            'title' => 'Demos Legados',
            'subtitle' => 'Página exclusiva para as aplicações antigas que continuam úteis para validação do legado ACBr.',
            'items' => [
                ['label' => 'Boleto', 'url' => '/Boleto/ACBrBoletoDemoMT.php', 'description' => 'Demo legado do módulo de boleto.', 'meta' => ['Legado'], 'target_blank' => true],
                ['label' => 'CEP', 'url' => '/ConsultaCEP/ACBrCEPDemoMT.php', 'description' => 'Demo legado de consulta CEP.', 'meta' => ['Legado'], 'target_blank' => true],
                ['label' => 'Consulta CNPJ', 'url' => '/ConsultaCNPJ/ACBrConsultaCNPJDemoMT.php', 'description' => 'Demo legado de consulta de CNPJ.', 'meta' => ['Legado'], 'target_blank' => true],
                ['label' => 'GTIN', 'url' => '/GTIN/ACBrGTINDemoMT.php', 'description' => 'Demo legado do módulo GTIN.', 'meta' => ['Legado'], 'target_blank' => true],
                ['label' => 'NFe', 'url' => '/NFe/ACBrNFeDemoMT.php', 'description' => 'Demo legado do módulo NFe.', 'meta' => ['Legado'], 'target_blank' => true],
                ['label' => 'NFSe', 'url' => '/NFSe/ACBrNFSeDemoMT.php', 'description' => 'Demo legado do módulo NFSe.', 'meta' => ['Legado'], 'target_blank' => true],
            ],
            'metrics' => [
                ['label' => 'Demos ativas', 'value' => '6'],
            ],
        ]);
    }

    #[Route('/monitoramento', name: 'app_monitoring', methods: ['GET'])]
    public function monitoring(): Response
    {
        $context = $this->buildDashboardContext();

        return $this->render('home/monitoring.html.twig', array_merge($context, [
            'overviewItems' => [
                [
                    'label' => 'Auditoria completa',
                    'description' => 'Abrir o dashboard com filtros, métricas e detalhe completo das requisições.',
                    'url' => '/index.php/auditoria-requisicoes',
                ],
                [
                    'label' => 'Catálogo de Testes',
                    'description' => 'Inspecionar cenários gravados, payloads e reruns operacionais.',
                    'url' => '/index.php/catalogo-testes',
                ],
                [
                    'label' => 'Catálogo de Programas',
                    'description' => 'Consultar o inventário técnico e o histórico local de módulos.',
                    'url' => '/index.php/catalogo-programas',
                ],
            ],
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDashboardContext(): array
    {
        return [
            'auditSummary' => $this->auditRepository->getSummary([]),
            'auditMetrics' => $this->auditRepository->getAdvancedMetrics([]),
            'recentFailures' => $this->auditRepository->findRecentFailures(6),
            'testSummary' => $this->testRepository->getSummary(),
            'recentTests' => $this->testRepository->findRecentObservedTests(6),
        ];
    }
}
