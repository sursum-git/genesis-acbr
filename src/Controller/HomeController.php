<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->render('home/hub.html.twig', [
            'apisUrl' => '/apis/',
            'demosUrl' => '/demos/',
            'catalogUrl' => '/catalogo-programas/',
        ]);
    }

    #[Route('/apis', name: 'app_apis', methods: ['GET'])]
    public function apis(): Response
    {
        return $this->render('home/section.html.twig', [
            'title' => 'APIs',
            'subtitle' => 'Documentação separada por módulo e catálogo completo do API Platform.',
            'items' => [
                ['label' => 'API Platform: Tudo', 'url' => '/docs/todos/'],
                ['label' => 'API Platform: CEP', 'url' => '/docs/cep/'],
                ['label' => 'API Platform: NFe', 'url' => '/docs/nfe/'],
                ['label' => 'API Platform: NFSe', 'url' => '/docs/nfse/'],
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
