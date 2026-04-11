<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ModuleDocsController
{
    #[Route('/docs/todos', name: 'app_docs_all', methods: ['GET'])]
    public function all(): RedirectResponse
    {
        return new RedirectResponse('/index.php/docs');
    }

    #[Route('/docs/cep', name: 'app_docs_cep', methods: ['GET'])]
    public function cep(): RedirectResponse
    {
        return new RedirectResponse('/index.php/docs?filter_tags[]=cep');
    }

    #[Route('/docs/nfe', name: 'app_docs_nfe', methods: ['GET'])]
    public function nfe(): RedirectResponse
    {
        return new RedirectResponse('/index.php/docs?filter_tags[]=nfe');
    }

    #[Route('/docs/nfse', name: 'app_docs_nfse', methods: ['GET'])]
    public function nfse(): RedirectResponse
    {
        return new RedirectResponse('/index.php/docs?filter_tags[]=nfse');
    }
}
