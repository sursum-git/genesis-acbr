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
        $auditSummary = $this->auditRepository->getSummary([]);
        $auditMetrics = $this->auditRepository->getAdvancedMetrics([]);
        $recentFailures = $this->auditRepository->findRecentFailures(6);
        $testSummary = $this->testRepository->getSummary();
        $recentTests = $this->testRepository->findRecentObservedTests(6);

        return $this->render('home/hub.html.twig', [
            'apisUrl' => '/index.php/apis',
            'demosUrl' => '/index.php/demos',
            'catalogUrl' => '/index.php/catalogo-programas',
            'testCatalogUrl' => '/index.php/catalogo-testes',
            'auditDashboardUrl' => '/index.php/auditoria-requisicoes',
            'apiOptions' => [
                ['label' => 'API Platform: Tudo', 'url' => '/index.php/docs/todos/', 'description' => 'Documentação completa de todas as APIs.'],
                ['label' => 'API Platform: CEP', 'url' => '/index.php/docs/cep/', 'description' => 'Endpoints e exemplos do módulo CEP.'],
                ['label' => 'API Platform: NFe', 'url' => '/index.php/docs/nfe/', 'description' => 'Endpoints, consultas e operações de NFe.'],
                ['label' => 'API Platform: NFSe', 'url' => '/index.php/docs/nfse/', 'description' => 'Endpoints e operações do módulo NFSe.'],
            ],
            'demoOptions' => [
                ['label' => 'Boleto', 'url' => '/Boleto/ACBrBoletoDemoMT.php', 'description' => 'Demo legado do módulo de boleto.'],
                ['label' => 'CEP', 'url' => '/ConsultaCEP/ACBrCEPDemoMT.php', 'description' => 'Demo legado de consulta CEP.'],
                ['label' => 'Consulta CNPJ', 'url' => '/ConsultaCNPJ/ACBrConsultaCNPJDemoMT.php', 'description' => 'Demo legado de consulta de CNPJ.'],
                ['label' => 'GTIN', 'url' => '/GTIN/ACBrGTINDemoMT.php', 'description' => 'Demo legado do módulo GTIN.'],
                ['label' => 'NFe', 'url' => '/NFe/ACBrNFeDemoMT.php', 'description' => 'Demo legado do módulo NFe.'],
                ['label' => 'NFSe', 'url' => '/NFSe/ACBrNFSeDemoMT.php', 'description' => 'Demo legado do módulo NFSe.'],
            ],
            'testModuleOptions' => [
                ['label' => 'Todos os testes', 'url' => '/index.php/catalogo-testes', 'description' => 'Abrir o catálogo completo de cenários gravados.'],
                ['label' => 'Testes CEP', 'url' => '/index.php/catalogo-testes?grupo=cep', 'description' => 'Filtrar cenários do módulo CEP.'],
                ['label' => 'Testes NFe', 'url' => '/index.php/catalogo-testes?grupo=nfe', 'description' => 'Filtrar cenários do módulo NFe.'],
                ['label' => 'Testes NFSe', 'url' => '/index.php/catalogo-testes?grupo=nfse', 'description' => 'Filtrar cenários do módulo NFSe.'],
            ],
            'auditModuleOptions' => [
                ['label' => 'Auditoria geral', 'url' => '/index.php/auditoria-requisicoes', 'description' => 'Abrir o dashboard completo sem filtro de módulo.'],
                ['label' => 'Auditoria CEP', 'url' => '/index.php/auditoria-requisicoes?programa=cep', 'description' => 'Abrir a auditoria filtrada no módulo CEP.'],
                ['label' => 'Auditoria NFe', 'url' => '/index.php/auditoria-requisicoes?programa=nfe', 'description' => 'Abrir a auditoria filtrada no módulo NFe.'],
                ['label' => 'Auditoria NFSe', 'url' => '/index.php/auditoria-requisicoes?programa=nfse', 'description' => 'Abrir a auditoria filtrada no módulo NFSe.'],
            ],
            'failureModuleOptions' => [
                ['label' => 'Todas as falhas', 'url' => '/index.php/auditoria-requisicoes?status_processamento=4', 'description' => 'Ver a fila completa de falhas recentes.'],
                ['label' => 'Falhas CEP', 'url' => '/index.php/auditoria-requisicoes?programa=cep&status_processamento=4', 'description' => 'Ver falhas recentes do módulo CEP.'],
                ['label' => 'Falhas NFe', 'url' => '/index.php/auditoria-requisicoes?programa=nfe&status_processamento=4', 'description' => 'Ver falhas recentes do módulo NFe.'],
                ['label' => 'Falhas NFSe', 'url' => '/index.php/auditoria-requisicoes?programa=nfse&status_processamento=4', 'description' => 'Ver falhas recentes do módulo NFSe.'],
            ],
            'moduleStacks' => [
                [
                    'label' => 'CEP',
                    'description' => 'Acessos principais do módulo CEP.',
                    'links' => [
                        ['label' => 'Docs', 'url' => '/index.php/docs/cep/'],
                        ['label' => 'Testes', 'url' => '/index.php/catalogo-testes?grupo=cep'],
                        ['label' => 'Auditoria', 'url' => '/index.php/auditoria-requisicoes?programa=cep'],
                    ],
                ],
                [
                    'label' => 'NFe',
                    'description' => 'Acessos principais do módulo NFe.',
                    'links' => [
                        ['label' => 'Docs', 'url' => '/index.php/docs/nfe/'],
                        ['label' => 'Testes', 'url' => '/index.php/catalogo-testes?grupo=nfe'],
                        ['label' => 'Auditoria', 'url' => '/index.php/auditoria-requisicoes?programa=nfe'],
                    ],
                ],
                [
                    'label' => 'NFSe',
                    'description' => 'Acessos principais do módulo NFSe.',
                    'links' => [
                        ['label' => 'Docs', 'url' => '/index.php/docs/nfse/'],
                        ['label' => 'Testes', 'url' => '/index.php/catalogo-testes?grupo=nfse'],
                        ['label' => 'Auditoria', 'url' => '/index.php/auditoria-requisicoes?programa=nfse'],
                    ],
                ],
            ],
            'auditSummary' => $auditSummary,
            'auditMetrics' => $auditMetrics,
            'recentFailures' => $recentFailures,
            'testSummary' => $testSummary,
            'recentTests' => $recentTests,
        ]);
    }

    #[Route('/apis', name: 'app_apis', methods: ['GET'])]
    public function apis(): Response
    {
        return $this->render('home/section.html.twig', [
            'title' => 'APIs',
            'subtitle' => 'Documentação separada por módulo e catálogo completo do API Platform.',
            'items' => [
                ['label' => 'API Platform: Tudo', 'url' => '/index.php/docs/todos/'],
                ['label' => 'API Platform: CEP', 'url' => '/index.php/docs/cep/'],
                ['label' => 'API Platform: NFe', 'url' => '/index.php/docs/nfe/'],
                ['label' => 'API Platform: NFSe', 'url' => '/index.php/docs/nfse/'],
            ],
        ]);
    }

    #[Route('/demos', name: 'app_demos', methods: ['GET'])]
    public function demos(): Response
    {
        return $this->render('home/section.html.twig', [
            'title' => 'Demos Legados',
            'subtitle' => 'Acesso direto às aplicações antigas enquanto a conversão para API continua.',
            'items' => [
                ['label' => 'Boleto', 'url' => '/Boleto/ACBrBoletoDemoMT.php'],
                ['label' => 'CEP', 'url' => '/ConsultaCEP/ACBrCEPDemoMT.php'],
                ['label' => 'ConsultaCNPJ', 'url' => '/ConsultaCNPJ/ACBrConsultaCNPJDemoMT.php'],
                ['label' => 'GTIN', 'url' => '/GTIN/ACBrGTINDemoMT.php'],
                ['label' => 'NFe', 'url' => '/NFe/ACBrNFeDemoMT.php'],
                ['label' => 'NFSe', 'url' => '/NFSe/ACBrNFSeDemoMT.php'],
            ],
        ]);
    }
}
