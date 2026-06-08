<?php

namespace App\Controller;

use App\Repository\SubscriberAdminRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class SubscriberAdminController extends AbstractController
{
    public function __construct(private readonly SubscriberAdminRepository $repository)
    {
    }

    #[Route('/assinantes', name: 'app_subscribers', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $metadata = ['primary_key' => null, 'columns' => []];
        $primaryKey = null;
        $selectedSubscriber = null;
        $subscribers = [];
        $loadError = null;

        try {
            $metadata = $this->repository->getTableMetadata();
            $primaryKey = $metadata['primary_key'];
            $selectedValue = trim((string) $request->query->get('id', ''));
            $selectedSubscriber = $selectedValue !== '' ? $this->repository->findSubscriber($selectedValue) : null;
            $subscribers = $this->repository->listSubscribers();
        } catch (Throwable $throwable) {
            $loadError = $throwable->getMessage();
        }

        return $this->render('admin/subscribers.html.twig', [
            'metadata' => $metadata,
            'primaryKey' => $primaryKey,
            'subscribers' => $subscribers,
            'selectedSubscriber' => $selectedSubscriber,
            'loadError' => $loadError,
        ]);
    }

    #[Route('/assinantes/salvar', name: 'app_subscribers_save', methods: ['POST'])]
    public function save(Request $request): RedirectResponse
    {
        try {
            $metadata = $this->repository->getTableMetadata();
            $primaryKey = $metadata['primary_key'];
            $primaryValue = $primaryKey !== null ? trim((string) $request->request->get($primaryKey, '')) : '';
            $payload = [];

            foreach ($metadata['columns'] as $column) {
                if ($column['editable'] !== true) {
                    continue;
                }

                $name = $column['name'];
                if ($name === 'c_token') {
                    continue;
                }

                if ($column['type'] === 'boolean' || str_starts_with($name, 'log_')) {
                    $payload[$name] = $request->request->has($name) ? '1' : '0';
                    continue;
                }

                $payload[$name] = (string) $request->request->get($name, '');
            }

            if ($primaryValue === '' && $this->repository->hasColumn('c_token')) {
                $payload['c_token'] = $this->repository->generateToken();
            }

            $this->repository->save($payload, $primaryValue !== '' ? $primaryValue : null);
            $this->addFlash('success', $primaryValue !== '' ? 'Assinante atualizado.' : 'Assinante criado.');
        } catch (Throwable $throwable) {
            $this->addFlash('error', 'Falha ao salvar assinante: ' . $throwable->getMessage());
        }

        return $this->redirectToRoute('app_subscribers');
    }
}
