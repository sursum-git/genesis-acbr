# Painel NFSe Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build an initial `/index.php/monitor-nfse` administrative console for NFSe actions: RPS emission, cancellation, substitution, and Padrao Nacional lookups/events.

**Architecture:** Add a focused Symfony controller for the panel and action proxy routes, a Twig/AdminLTE console template, a small browser script for tab switching/forms, and one menu entry under Monitores. The panel calls existing API Platform NFSe endpoints instead of duplicating ACBr fiscal logic.

**Tech Stack:** PHP 8.2, Symfony 7 attributes, Twig, Bootstrap/AdminLTE, browser `fetch`, existing ACBr legacy API processors.

**Spec:** `docs/superpowers/specs/2026-09-08-nfse-panel-design.md`

## Global Constraints

- Keep existing NFe monitor behavior unchanged.
- Do not revert preexisting dirty worktree changes.
- Use existing `/index.php/nfse/...` API contracts as the source of fiscal execution.
- The first screen must be a usable console, not a landing page.
- Implement with failing tests before production changes.

---

### Task 1: Menu and Page Shell

**Files:**
- Modify: `templates/admin/base.html.twig`
- Create: `src/Controller/NfsePanelController.php`
- Create: `templates/admin/nfse_panel.html.twig`
- Create: `catalog-assets/monitor/nfse-panel.js`
- Test: `tests/Controller/CommonUserMenuTest.php`
- Test: `tests/Controller/CompanyAdminMenuTest.php`
- Test: `tests/Controller/NfsePanelPageTest.php`

**Interfaces:**
- Produces route `app_nfse_panel` at `GET /monitor-nfse`.
- Produces template data keys: `emitRpsUrl`, `cancelUrl`, `substituteUrl`, `nationalEventUrl`, `consultDpsUrl`, `consultNfseUrl`, `docsUrl`.
- Produces `window.NfsePanel` initialized by `catalog-assets/monitor/nfse-panel.js`.

- [ ] **Step 1: Write failing menu tests**

Add assertions:

```php
assertCommonMenuContains('/index.php/monitor-nfse', $content, 'Common user sidebar should show NFSe panel link.');
assertCompanyAdminMenuContains('/index.php/monitor-nfse', $content, 'Company admin sidebar should show NFSe panel link.');
```

- [ ] **Step 2: Write failing page render test**

Create `tests/Controller/NfsePanelPageTest.php`:

```php
<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__, 2) . '/.env');

$kernel = new Kernel('test', true);
$request = Request::create('/monitor-nfse');
$response = $kernel->handle($request);
$content = (string) $response->getContent();

if ($response->getStatusCode() !== 200) {
    fwrite(STDERR, "NFSe panel page should render successfully.\n");
    exit(1);
}

foreach (['NFSe', 'RPS', 'Cancelar', 'Substituir', 'Padrao Nacional', 'Retorno'] as $needle) {
    if (!str_contains($content, $needle)) {
        fwrite(STDERR, "NFSe panel should render {$needle}.\n");
        exit(1);
    }
}

if (!preg_match('#<script src="/catalog-assets/monitor/nfse-panel\.js\?v=[^"&]+"></script>#', $content)) {
    fwrite(STDERR, "NFSe panel JavaScript URL should carry a cache-busting version.\n");
    exit(1);
}

$kernel->terminate($request, $response);
fwrite(STDOUT, "OK\n");
```

- [ ] **Step 3: Run tests to verify they fail**

Run:

```bash
php tests/Controller/CommonUserMenuTest.php
php tests/Controller/CompanyAdminMenuTest.php
php tests/Controller/NfsePanelPageTest.php
```

Expected: menu tests fail missing `/index.php/monitor-nfse`; page test fails missing route/template.

- [ ] **Step 4: Implement minimal shell**

Create controller with `GET /monitor-nfse` rendering `admin/nfse_panel.html.twig`.

Create Twig template with AdminLTE card, tabs/buttons/forms, data URLs, and versioned JS.

Create JS file with tab switching only.

Add menu link under `Monitores`:

```twig
<a href="{{ app.request.basePath ~ '/index.php/monitor-nfse' }}" class="nav-link{% if currentRoute starts with 'app_nfse_' %} active{% endif %}">
    <i class="nav-icon bi bi-receipt"></i>
    <p>NFSe</p>
</a>
```

- [ ] **Step 5: Run tests to verify they pass**

Run the same three PHP tests. Expected: all print `OK`.

### Task 2: Action Proxy Validation

**Files:**
- Modify: `src/Controller/NfsePanelController.php`
- Modify: `catalog-assets/monitor/nfse-panel.js`
- Test: `tests/Controller/NfsePanelActionValidationTest.php`

**Interfaces:**
- Produces `POST /monitor-nfse/acoes/emitir-rps`
- Produces `POST /monitor-nfse/acoes/cancelar`
- Produces `POST /monitor-nfse/acoes/substituir`
- Produces `POST /monitor-nfse/acoes/padrao-nacional/enviar-evento`
- Produces `GET /monitor-nfse/acoes/padrao-nacional/consultar-dps`
- Produces `GET /monitor-nfse/acoes/padrao-nacional/consultar-nfse`

- [ ] **Step 1: Write failing validation test**

Create requests against `POST /monitor-nfse/acoes/cancelar` with `{}` and `POST /monitor-nfse/acoes/substituir` with `{}`. Assert HTTP 400 and messages mentioning payload invalid or required fields.

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
php tests/Controller/NfsePanelActionValidationTest.php
```

Expected: fails because routes do not exist.

- [ ] **Step 3: Implement validation and forwarding**

Implement helpers in `NfsePanelController`:

```php
private function jsonPayload(Request $request): ?array
private function forwardJson(Request $request, string $method, string $path, array $payload): JsonResponse
private function forwardRaw(Request $request, string $method, string $path, string $body, array $query = []): JsonResponse
private function apiToken(): ?string
```

Forwarding uses `CurrentUserContext::activeSubscriberId()` and `subscriberToken()` like the NFe output monitor.

Validation:

- emit RPS requires non-empty `conteudo` and `modo` in `emitir-nota`, `enviar-um-rps`, `enviar-lote-rps-sincrono`, `enviar-lote-rps-assincrono`;
- cancel requires non-empty `conteudo`;
- substitute requires `conteudo`, `ANumeroNFSe`, `ASerieNFSe`, `ACodigoCancelamento`, `AMotivoCancelamento`, `ANumeroLote`, `ACodigoVerificacao`;
- national event requires non-empty `conteudo`;
- consult DPS requires `AChaveDPS`;
- consult NFSe requires `AChaveNFSe`.

- [ ] **Step 4: Wire JS forms**

Implement submit handlers that collect fields, call data URLs, disable submit buttons during request, and render returned JSON/status into `#nfse-result-output`.

- [ ] **Step 5: Run validation test**

Run:

```bash
php tests/Controller/NfsePanelActionValidationTest.php
```

Expected: prints `OK`.

### Task 3: Final Verification

**Files:**
- Verify all files changed in Tasks 1 and 2.

- [ ] **Step 1: PHP syntax checks**

Run:

```bash
php -l src/Controller/NfsePanelController.php
php -l tests/Controller/NfsePanelPageTest.php
php -l tests/Controller/NfsePanelActionValidationTest.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 2: Focused tests**

Run:

```bash
php tests/Controller/CommonUserMenuTest.php
php tests/Controller/CompanyAdminMenuTest.php
php tests/Controller/NfsePanelPageTest.php
php tests/Controller/NfsePanelActionValidationTest.php
```

Expected: each prints `OK`.

- [ ] **Step 3: Git review**

Run:

```bash
git status --short --branch
git diff -- templates/admin/base.html.twig src/Controller/NfsePanelController.php templates/admin/nfse_panel.html.twig catalog-assets/monitor/nfse-panel.js tests/Controller/CommonUserMenuTest.php tests/Controller/CompanyAdminMenuTest.php tests/Controller/NfsePanelPageTest.php tests/Controller/NfsePanelActionValidationTest.php
```

Expected: only intended changes for the NFSe panel plus existing unrelated dirty files remain.
