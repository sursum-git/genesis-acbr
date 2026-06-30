# NFe Monitor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build an administrative NFe send monitor with Kendo UI, showing the latest 100 sends, operational status, quick detail modal, and a full detail page.

**Architecture:** Add a dedicated repository that reads from the audit PostgreSQL connection and joins `t99001` with extracted `t99019+` tables. Expose one HTML page, one JSON listing endpoint, one JSON quick-detail endpoint, and one HTML full-detail page, all under the current Symfony/Twig admin portal and backed by Kendo assets already vendored into the repo.

**Tech Stack:** PHP 8.2, Symfony 7, Twig, Doctrine DBAL, Kendo UI, Bootstrap/AdminLTE

---

### Task 1: Baseline the worktree for Symfony commands

**Files:**
- Modify: `vendor` access inside worktree only if missing
- Test: `php bin/console lint:container`

- [ ] **Step 1: Confirm the worktree is missing vendor**

Run: `test -f vendor/autoload_runtime.php`
Expected: exit code non-zero in the worktree

- [ ] **Step 2: Create the minimal local symlink to reuse the existing dependencies**

Run: `ln -s ../../vendor vendor`
Expected: `vendor` becomes available in the worktree without downloading dependencies

- [ ] **Step 3: Verify the Symfony container baseline**

Run: `php bin/console lint:container`
Expected: exit code `0`

- [ ] **Step 4: Commit the worktree setup only if a tracked file was changed**

Run: `git status --short`
Expected: no tracked changes yet, so no commit should be created for this setup-only task

### Task 2: Add a failing repository test for the NFe monitor query contract

**Files:**
- Create: `tests/Repository/NfeMonitorRepositoryTest.php`
- Create: `src/Repository/NfeMonitorRepository.php`
- Test: `tests/Repository/NfeMonitorRepositoryTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace App\Tests\Repository;

use App\Repository\NfeMonitorRepository;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class NfeMonitorRepositoryTest extends TestCase
{
    public function testListLatestReturnsNormalizedMonitorRows(): void
    {
        $connection = DriverManager::getConnection(['url' => 'sqlite:///:memory:']);
        $connection->executeStatement('CREATE TABLE t99001 (id_t99001 INTEGER PRIMARY KEY AUTOINCREMENT, u_c_request_id TEXT, c_caminho TEXT, si_status_processamento INTEGER, si_status_http INTEGER, si_status_extracao INTEGER, t_erro TEXT, t_erro_extracao TEXT, dt_hr_recebimento TEXT, c_nome_operacao TEXT, c_cod_programa TEXT, t_assinante_json TEXT)');
        $connection->executeStatement('CREATE TABLE t99019 (id_t99019 INTEGER PRIMARY KEY AUTOINCREMENT, u_c_request_id TEXT, nNF TEXT, vNF TEXT, ch_nfe TEXT, xNome_destinatario TEXT, dhEmi TEXT, xml_autorizado TEXT, caminho_danfe TEXT)');
        $connection->executeStatement("INSERT INTO t99001 (u_c_request_id, c_caminho, si_status_processamento, si_status_http, si_status_extracao, t_erro, t_erro_extracao, dt_hr_recebimento, c_nome_operacao, c_cod_programa, t_assinante_json) VALUES ('req-1', '/nfe/envio/enviar-sincrono-xml', 3, 200, 3, NULL, NULL, '2026-06-30 10:00:00', 'enviar-sincrono-xml', 'nfe', '{\"c_nome\":\"Cliente API\"}')");
        $connection->executeStatement("INSERT INTO t99019 (u_c_request_id, nNF, vNF, ch_nfe, xNome_destinatario, dhEmi, xml_autorizado, caminho_danfe) VALUES ('req-1', '123', '155.40', '35123456789012345678901234567890123456789012', 'ACME LTDA', '2026-06-30 10:00:00', '<xml/>', '/tmp/danfe.pdf')");

        $repository = new NfeMonitorRepository($connection);

        $rows = $repository->listLatest(100);

        self::assertCount(1, $rows);
        self::assertSame('req-1', $rows[0]['request_id']);
        self::assertSame('123', $rows[0]['numero_nota']);
        self::assertSame('ACME LTDA', $rows[0]['cliente']);
        self::assertSame('155.40', $rows[0]['valor_total']);
        self::assertSame('Enviado com sucesso', $rows[0]['situacao']);
        self::assertSame('envio', $rows[0]['ocorrencia']);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php vendor/bin/phpunit tests/Repository/NfeMonitorRepositoryTest.php`
Expected: FAIL because `App\Repository\NfeMonitorRepository` does not exist yet

- [ ] **Step 3: Write the minimal repository implementation**

```php
<?php

namespace App\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class NfeMonitorRepository
{
    public function __construct(private readonly Connection $auditConnection)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listLatest(int $limit = 100): array
    {
        $platform = strtolower($this->auditConnection->getDatabasePlatform()->getName());
        $sql = $platform === 'sqlite'
            ? 'SELECT t.u_c_request_id AS request_id, n.nNF AS numero_nota, COALESCE(n.xNome_destinatario, \'\') AS cliente, COALESCE(n.vNF, \'\') AS valor_total, t.si_status_processamento, t.si_status_http, t.si_status_extracao, t.t_erro, t.t_erro_extracao, t.dt_hr_recebimento FROM t99001 t LEFT JOIN t99019 n ON n.u_c_request_id = t.u_c_request_id WHERE t.c_caminho LIKE :path ORDER BY t.id_t99001 DESC LIMIT :limit'
            : 'SELECT t.u_c_request_id AS request_id, n.nnf::text AS numero_nota, COALESCE(n.xnome_destinatario, \'\') AS cliente, COALESCE(n.vnf::text, \'\') AS valor_total, t.si_status_processamento, t.si_status_http, t.si_status_extracao, t.t_erro, t.t_erro_extracao, t.dt_hr_recebimento FROM t99001 t LEFT JOIN t99019 n ON n.u_c_request_id = t.u_c_request_id WHERE t.c_caminho LIKE :path ORDER BY t.id_t99001 DESC LIMIT :limit';

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->auditConnection->fetchAllAssociative(
            $sql,
            [
                'path' => '/nfe/envio/%',
                'limit' => max(1, min($limit, 100)),
            ],
            [
                'limit' => ParameterType::INTEGER,
            ]
        );

        return array_map(function (array $row): array {
            $situacao = 'Pendente';
            $ocorrencia = 'envio';

            if ((int) ($row['si_status_processamento'] ?? 0) === 3 && (int) ($row['si_status_extracao'] ?? 0) !== 4) {
                $situacao = 'Enviado com sucesso';
            } elseif ((int) ($row['si_status_processamento'] ?? 0) === 4) {
                $situacao = 'Enviado com falha';
                $ocorrencia = 'erro';
            } elseif ((int) ($row['si_status_processamento'] ?? 0) === 2) {
                $situacao = 'Em processamento';
                $ocorrencia = 'processando';
            } elseif ((int) ($row['si_status_extracao'] ?? 0) === 4) {
                $situacao = 'Erro de extracao';
                $ocorrencia = 'erro';
            }

            if (($row['numero_nota'] ?? '') === '') {
                $situacao = $situacao === 'Enviado com sucesso' ? 'Sem nota vinculada' : $situacao;
            }

            $row['situacao'] = $situacao;
            $row['ocorrencia'] = $ocorrencia;

            return $row;
        }, $rows);
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php vendor/bin/phpunit tests/Repository/NfeMonitorRepositoryTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add tests/Repository/NfeMonitorRepositoryTest.php src/Repository/NfeMonitorRepository.php
git commit -m "feat: add nfe monitor repository"
```

### Task 3: Expand the repository for detail payloads and full monitor shape

**Files:**
- Modify: `src/Repository/NfeMonitorRepository.php`
- Test: `tests/Repository/NfeMonitorRepositoryTest.php`

- [ ] **Step 1: Extend the failing test with quick detail and full detail lookups**

```php
public function testFindDetailReturnsOperationalAndFiscalFields(): void
{
    $connection = DriverManager::getConnection(['url' => 'sqlite:///:memory:']);
    $connection->executeStatement('CREATE TABLE t99001 (id_t99001 INTEGER PRIMARY KEY AUTOINCREMENT, u_c_request_id TEXT, c_caminho TEXT, si_status_processamento INTEGER, si_status_http INTEGER, si_status_extracao INTEGER, t_erro TEXT, t_erro_extracao TEXT, dt_hr_recebimento TEXT, t_corpo_resposta TEXT, t_corpo_requisicao TEXT)');
    $connection->executeStatement('CREATE TABLE t99019 (id_t99019 INTEGER PRIMARY KEY AUTOINCREMENT, u_c_request_id TEXT, nNF TEXT, vNF TEXT, ch_nfe TEXT, xNome_destinatario TEXT, xml_autorizado TEXT, caminho_danfe TEXT)');
    $connection->executeStatement("INSERT INTO t99001 (u_c_request_id, c_caminho, si_status_processamento, si_status_http, si_status_extracao, t_erro, t_erro_extracao, dt_hr_recebimento, t_corpo_resposta, t_corpo_requisicao) VALUES ('req-2', '/nfe/envio/enviar-sincrono-xml', 4, 500, 4, 'falha', 'extracao', '2026-06-30 11:00:00', '{\"ok\":false}', '<envio/>')");
    $connection->executeStatement("INSERT INTO t99019 (u_c_request_id, nNF, vNF, ch_nfe, xNome_destinatario, xml_autorizado, caminho_danfe) VALUES ('req-2', '999', '42.00', '99999999999999999999999999999999999999999999', 'Foo SA', '<xml/>', '/tmp/foo.pdf')");

    $repository = new NfeMonitorRepository($connection);
    $detail = $repository->findDetailByRequestId('req-2');

    self::assertNotNull($detail);
    self::assertSame('999', $detail['numero_nota']);
    self::assertSame('Foo SA', $detail['cliente']);
    self::assertSame('falha', $detail['erro_execucao']);
    self::assertSame('extracao', $detail['erro_extracao']);
    self::assertSame('/tmp/foo.pdf', $detail['caminho_danfe']);
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php vendor/bin/phpunit tests/Repository/NfeMonitorRepositoryTest.php`
Expected: FAIL because `findDetailByRequestId()` does not exist yet

- [ ] **Step 3: Implement the detail query and normalized output**

Code to add in `src/Repository/NfeMonitorRepository.php`:

```php
    /**
     * @return array<string, mixed>|null
     */
    public function findDetailByRequestId(string $requestId): ?array
    {
        $platform = strtolower($this->auditConnection->getDatabasePlatform()->getName());
        $sql = $platform === 'sqlite'
            ? 'SELECT t.u_c_request_id AS request_id, t.c_caminho, t.si_status_processamento, t.si_status_http, t.si_status_extracao, t.t_erro AS erro_execucao, t.t_erro_extracao AS erro_extracao, t.dt_hr_recebimento, t.t_corpo_resposta, t.t_corpo_requisicao, n.nNF AS numero_nota, n.vNF AS valor_total, n.ch_nfe AS chave_nfe, n.xNome_destinatario AS cliente, n.xml_autorizado, n.caminho_danfe FROM t99001 t LEFT JOIN t99019 n ON n.u_c_request_id = t.u_c_request_id WHERE t.u_c_request_id = :request_id ORDER BY t.id_t99001 DESC LIMIT 1'
            : 'SELECT t.u_c_request_id AS request_id, t.c_caminho, t.si_status_processamento, t.si_status_http, t.si_status_extracao, t.t_erro AS erro_execucao, t.t_erro_extracao AS erro_extracao, t.dt_hr_recebimento, t.t_corpo_resposta, t.t_corpo_requisicao, n.nnf::text AS numero_nota, n.vnf::text AS valor_total, n.ch_nfe AS chave_nfe, n.xnome_destinatario AS cliente, n.xml_autorizado, n.caminho_danfe FROM t99001 t LEFT JOIN t99019 n ON n.u_c_request_id = t.u_c_request_id WHERE t.u_c_request_id = :request_id ORDER BY t.id_t99001 DESC LIMIT 1';

        $row = $this->auditConnection->fetchAssociative($sql, ['request_id' => $requestId]);

        if ($row === false) {
            return null;
        }

        $row['situacao'] = $this->resolveSituacao($row);
        $row['ocorrencia'] = $this->resolveOcorrencia($row);

        return $row;
    }
```

Also extract the status mapping used in `listLatest()` into:

```php
    private function resolveSituacao(array $row): string
    {
        // same branch logic already proven in Task 2
    }

    private function resolveOcorrencia(array $row): string
    {
        // same branch logic already proven in Task 2
    }
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php vendor/bin/phpunit tests/Repository/NfeMonitorRepositoryTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add tests/Repository/NfeMonitorRepositoryTest.php src/Repository/NfeMonitorRepository.php
git commit -m "feat: add nfe monitor detail query"
```

### Task 4: Add controller endpoints for HTML and JSON

**Files:**
- Create: `src/Controller/NfeMonitorController.php`
- Modify: `templates/admin/base.html.twig`
- Test: `php bin/console lint:container`

- [ ] **Step 1: Write the failing controller-focused test by linting the container after referencing a missing controller**

Create the controller file with the class declaration and missing methods first:

```php
<?php

namespace App\Controller;

final class NfeMonitorController
{
}
```

Run: `php bin/console lint:container`
Expected: FAIL after the next step when routes are introduced but the class is incomplete

- [ ] **Step 2: Implement the controller with the four routes**

```php
<?php

namespace App\Controller;

use App\Repository\NfeMonitorRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class NfeMonitorController extends AbstractController
{
    public function __construct(private readonly NfeMonitorRepository $monitorRepository)
    {
    }

    #[Route('/monitor-envios-nfe', name: 'app_nfe_monitor', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/nfe_monitor.html.twig', [
            'dataUrl' => '/index.php/monitor-envios-nfe/dados',
            'detailUrlTemplate' => '/index.php/monitor-envios-nfe/detalhe/__REQUEST_ID__',
            'pageUrlTemplate' => '/index.php/monitor-envios-nfe/saida/__REQUEST_ID__',
        ]);
    }

    #[Route('/monitor-envios-nfe/dados', name: 'app_nfe_monitor_data', methods: ['GET'])]
    public function data(): JsonResponse
    {
        return $this->json([
            'data' => $this->monitorRepository->listLatest(100),
        ]);
    }

    #[Route('/monitor-envios-nfe/detalhe/{requestId}', name: 'app_nfe_monitor_detail', methods: ['GET'])]
    public function detail(string $requestId): JsonResponse
    {
        $detail = $this->monitorRepository->findDetailByRequestId($requestId);
        if ($detail === null) {
            return $this->json(['mensagem' => 'Registro nao encontrado.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($detail);
    }

    #[Route('/monitor-envios-nfe/saida/{requestId}', name: 'app_nfe_monitor_output', methods: ['GET'])]
    public function output(string $requestId): Response
    {
        $detail = $this->monitorRepository->findDetailByRequestId($requestId);
        if ($detail === null) {
            throw $this->createNotFoundException('Registro do monitor nao encontrado.');
        }

        return $this->render('admin/nfe_monitor_output.html.twig', [
            'detail' => $detail,
        ]);
    }
}
```

- [ ] **Step 3: Add the menu link in the admin navigation**

Insert a new menu item in `templates/admin/base.html.twig` near the operational pages:

```twig
<li class="nav-item">
    <a href="{{ app.request.basePath ~ '/index.php/monitor-envios-nfe' }}" class="nav-link{% if currentRoute == 'app_nfe_monitor' or currentRoute == 'app_nfe_monitor_output' %} active{% endif %}">
        <i class="nav-icon bi bi-receipt"></i>
        <p>Monitor de envios NFe</p>
    </a>
</li>
```

- [ ] **Step 4: Run container lint to verify controller wiring**

Run: `php bin/console lint:container`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Controller/NfeMonitorController.php templates/admin/base.html.twig
git commit -m "feat: add nfe monitor endpoints"
```

### Task 5: Build the Twig pages and Kendo-based frontend

**Files:**
- Create: `templates/admin/nfe_monitor.html.twig`
- Create: `templates/admin/nfe_monitor_output.html.twig`
- Create: `catalog-assets/monitor/nfe-monitor.css`
- Create: `catalog-assets/monitor/nfe-monitor.js`
- Test: `php bin/console lint:twig templates/admin/nfe_monitor.html.twig templates/admin/nfe_monitor_output.html.twig`

- [ ] **Step 1: Create the monitor page template**

```twig
{% extends 'admin/base.html.twig' %}

{% block title %}Monitor de envios NFe{% endblock %}
{% block page_heading %}Monitor de envios NFe{% endblock %}
{% block heading_text %}Visao operacional dos ultimos 100 envios de NFe com status, detalhe rapido e tela de saida.{% endblock %}

{% block page_styles %}
    <link rel="stylesheet" href="{{ app.request.basePath ~ '/catalog-assets/kendo/styles/default-main.css' }}">
    <link rel="stylesheet" href="{{ app.request.basePath ~ '/catalog-assets/monitor/nfe-monitor.css' }}">
{% endblock %}

{% block body %}
    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h2 class="h4 mb-1">Ultimos 100 registros</h2>
                    <p class="text-muted mb-0">Recarregue a pagina para atualizar os dados.</p>
                </div>
            </div>
            <div
                id="nfe-monitor-app"
                data-data-url="{{ dataUrl }}"
                data-detail-url-template="{{ detailUrlTemplate }}"
                data-page-url-template="{{ pageUrlTemplate }}"
            >
                <div id="nfe-monitor-grid"></div>
                <div id="nfe-monitor-window" style="display:none;"></div>
            </div>
        </div>
    </section>
{% endblock %}

{% block page_scripts %}
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="{{ app.request.basePath ~ '/catalog-assets/kendo/js/kendo.all.min.js' }}"></script>
    <script src="{{ app.request.basePath ~ '/catalog-assets/monitor/nfe-monitor.js' }}"></script>
{% endblock %}
```

- [ ] **Step 2: Create the full output page template**

```twig
{% extends 'admin/base.html.twig' %}

{% block title %}Saida da NFe {{ detail.numero_nota ?: detail.request_id }}{% endblock %}
{% block page_heading %}Saida da NFe{% endblock %}
{% block heading_text %}Detalhe operacional e fiscal do envio selecionado.{% endblock %}

{% block body %}
    <section class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-3 justify-content-between align-items-start">
                <div>
                    <h2 class="h4 mb-1">Nota {{ detail.numero_nota ?: 'sem numero estruturado' }}</h2>
                    <p class="text-muted mb-0">{{ detail.cliente ?: 'Cliente nao identificado' }}</p>
                </div>
                <span class="badge text-bg-primary">{{ detail.situacao }}</span>
            </div>
        </div>
    </section>

    <div class="row g-4">
        <div class="col-12 col-xl-6">
            <section class="card h-100">
                <div class="card-header"><h3 class="card-title">Resumo</h3></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Request ID</dt><dd class="col-sm-8"><code>{{ detail.request_id }}</code></dd>
                        <dt class="col-sm-4">Numero</dt><dd class="col-sm-8">{{ detail.numero_nota ?: '-' }}</dd>
                        <dt class="col-sm-4">Chave</dt><dd class="col-sm-8">{{ detail.chave_nfe ?: '-' }}</dd>
                        <dt class="col-sm-4">Valor</dt><dd class="col-sm-8">{{ detail.valor_total ?: '-' }}</dd>
                        <dt class="col-sm-4">Recebimento</dt><dd class="col-sm-8">{{ detail.dt_hr_recebimento ?: '-' }}</dd>
                        <dt class="col-sm-4">DANFE</dt><dd class="col-sm-8">{{ detail.caminho_danfe ?: '-' }}</dd>
                    </dl>
                </div>
            </section>
        </div>
        <div class="col-12 col-xl-6">
            <section class="card h-100">
                <div class="card-header"><h3 class="card-title">Falhas e resposta</h3></div>
                <div class="card-body">
                    <p><strong>Erro de execucao:</strong> {{ detail.erro_execucao ?: 'nenhum' }}</p>
                    <p><strong>Erro de extracao:</strong> {{ detail.erro_extracao ?: 'nenhum' }}</p>
                    <p><strong>Resposta:</strong></p>
                    <pre class="monitor-pre">{{ detail.t_corpo_resposta ?: 'sem resposta registrada' }}</pre>
                </div>
            </section>
        </div>
    </div>
{% endblock %}
```

- [ ] **Step 3: Create the Kendo page script**

```javascript
(function () {
    const root = document.getElementById('nfe-monitor-app');
    if (!root || typeof window.kendo === 'undefined' || typeof window.jQuery === 'undefined') {
        return;
    }

    const dataUrl = root.dataset.dataUrl || '';
    const detailUrlTemplate = root.dataset.detailUrlTemplate || '';
    const pageUrlTemplate = root.dataset.pageUrlTemplate || '';
    const $grid = window.jQuery('#nfe-monitor-grid');
    const $window = window.jQuery('#nfe-monitor-window');

    const detailWindow = $window.kendoWindow({
        modal: true,
        visible: false,
        width: 720,
        title: 'Detalhe do envio',
    }).data('kendoWindow');

    function detailUrl(requestId) {
        return detailUrlTemplate.replace('__REQUEST_ID__', encodeURIComponent(requestId));
    }

    function pageUrl(requestId) {
        return pageUrlTemplate.replace('__REQUEST_ID__', encodeURIComponent(requestId));
    }

    function badgeClass(ocorrencia) {
        if (ocorrencia === 'erro') {
            return 'text-bg-danger';
        }
        if (ocorrencia === 'processando') {
            return 'text-bg-warning';
        }
        return 'text-bg-success';
    }

    function openDetail(requestId) {
        window.jQuery.getJSON(detailUrl(requestId)).done(function (detail) {
            detailWindow.content(
                '<div class="monitor-detail">' +
                '<div class="d-flex justify-content-between align-items-start gap-3 mb-3">' +
                '<div><h3 class="h5 mb-1">Nota ' + (detail.numero_nota || 'sem numero') + '</h3><p class="text-muted mb-0">' + (detail.cliente || 'Cliente nao identificado') + '</p></div>' +
                '<span class="badge ' + badgeClass(detail.ocorrencia) + '">' + (detail.situacao || '-') + '</span>' +
                '</div>' +
                '<dl class="row mb-3">' +
                '<dt class="col-sm-4">Request ID</dt><dd class="col-sm-8"><code>' + (detail.request_id || '') + '</code></dd>' +
                '<dt class="col-sm-4">Valor</dt><dd class="col-sm-8">' + (detail.valor_total || '-') + '</dd>' +
                '<dt class="col-sm-4">Erro</dt><dd class="col-sm-8">' + (detail.erro_execucao || detail.erro_extracao || 'nenhum') + '</dd>' +
                '</dl>' +
                '<pre class="monitor-pre">' + (detail.t_corpo_resposta || 'sem resposta registrada') + '</pre>' +
                '<div class="mt-3"><a class="btn btn-primary" href="' + pageUrl(requestId) + '">Abrir tela completa</a></div>' +
                '</div>'
            );
            detailWindow.center().open();
        });
    }

    $grid.kendoGrid({
        dataSource: {
            transport: {
                read: {
                    url: dataUrl,
                    dataType: 'json'
                }
            },
            schema: {
                data: 'data'
            },
            pageSize: 100
        },
        height: 640,
        sortable: true,
        scrollable: true,
        pageable: false,
        columns: [
            { field: 'numero_nota', title: 'Numero da nota', width: 150, template: '#= numero_nota || "—" #' },
            { field: 'cliente', title: 'Cliente', width: 240, template: '#= cliente || "Nao identificado" #' },
            { field: 'dt_hr_recebimento', title: 'Data', width: 180 },
            { field: 'valor_total', title: 'Valor', width: 120, template: '#= valor_total || "—" #' },
            { field: 'situacao', title: 'Situacao', width: 180 },
            { field: 'ocorrencia', title: 'Ocorrencia', width: 130, template: function (row) { return '<span class="badge ' + badgeClass(row.ocorrencia) + '">' + row.ocorrencia + '</span>'; } },
            {
                title: 'Acoes',
                width: 220,
                template: function (row) {
                    return '<div class="d-flex gap-2">' +
                        '<button type="button" class="btn btn-sm btn-outline-primary js-open-detail" data-request-id="' + row.request_id + '">Detalhe rapido</button>' +
                        '<a class="btn btn-sm btn-primary" href="' + pageUrl(row.request_id) + '">Tela de saida</a>' +
                        '</div>';
                }
            }
        ],
        dataBound: function () {
            $grid.find('.js-open-detail').off('click').on('click', function () {
                openDetail(this.getAttribute('data-request-id') || '');
            });
        }
    });
}());
```

- [ ] **Step 4: Create the page stylesheet**

```css
.monitor-pre {
    max-height: 320px;
    overflow: auto;
    padding: 1rem;
    border-radius: 0.75rem;
    background: #0f172a;
    color: #e2e8f0;
    font-size: 0.85rem;
    white-space: pre-wrap;
    word-break: break-word;
}

.monitor-detail code {
    word-break: break-all;
}

#nfe-monitor-grid .k-grid-content {
    font-size: 0.92rem;
}
```

- [ ] **Step 5: Run Twig lint**

Run: `php bin/console lint:twig templates/admin/nfe_monitor.html.twig templates/admin/nfe_monitor_output.html.twig`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add templates/admin/nfe_monitor.html.twig templates/admin/nfe_monitor_output.html.twig catalog-assets/monitor/nfe-monitor.css catalog-assets/monitor/nfe-monitor.js
git commit -m "feat: add nfe monitor ui"
```

### Task 6: Final verification and integration checks

**Files:**
- Modify: any touched files from previous tasks if verification exposes issues
- Test: `php bin/console lint:container`, `php bin/console lint:twig ...`, `php vendor/bin/phpunit tests/Repository/NfeMonitorRepositoryTest.php`

- [ ] **Step 1: Run repository test suite for the new monitor**

Run: `php vendor/bin/phpunit tests/Repository/NfeMonitorRepositoryTest.php`
Expected: PASS

- [ ] **Step 2: Run Symfony container lint**

Run: `php bin/console lint:container`
Expected: PASS

- [ ] **Step 3: Run Twig lint on the monitor pages**

Run: `php bin/console lint:twig templates/admin/nfe_monitor.html.twig templates/admin/nfe_monitor_output.html.twig templates/admin/base.html.twig`
Expected: PASS

- [ ] **Step 4: Check git diff for accidental unrelated changes**

Run: `git status --short`
Expected: only the monitor-related files should be modified or newly added in the worktree

- [ ] **Step 5: Commit the final verification fixes if any were needed**

```bash
git add src/Controller/NfeMonitorController.php src/Repository/NfeMonitorRepository.php templates/admin/base.html.twig templates/admin/nfe_monitor.html.twig templates/admin/nfe_monitor_output.html.twig catalog-assets/monitor/nfe-monitor.css catalog-assets/monitor/nfe-monitor.js tests/Repository/NfeMonitorRepositoryTest.php
git commit -m "fix: polish nfe monitor integration"
```
