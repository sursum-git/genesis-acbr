# NFe Library Release Selection Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Select and audit the approved ACBrLibNFe native release by fiscal environment and validity period.

**Architecture:** A PostgreSQL release registry (`t99037_acbr_library_release`) is queried by a Symfony resolver after it determines the fiscal environment from the resolved NFe INI profile. The resolver validates the artifact and sends a short-lived signed selection to the internal legacy call; the legacy loader validates the selection and loads only the approved absolute `.so` path.

**Tech Stack:** PHP 8.2, Symfony 7, Doctrine DBAL 4, PostgreSQL, PHP FFI, HMAC-SHA256, Twig.

**Spec:** `docs/superpowers/specs/2026-10-19-nfe-library-release-selection-design.md`

## Global Constraints

- Keep native artifacts outside Git under `/opt/acbr_libs/releases/`.
- Never accept a native library path or release identifier directly from a public request.
- Allow exactly one active matching release for each module, fiscal environment and instant.
- Reject paths outside `/opt/acbr_libs/releases/`, missing files and SHA-256 mismatches.
- Keep historical records; do not delete or overwrite releases that were already used.
- Do not alter the existing NFe monitor behavior or reuse `t99003`.

## Review Focus

- A release starting exactly at the current instant is selected; a release ending at it is not selected.
- A manually altered `.so` fails checksum verification before FFI loads it.
- A valid-looking signed payload with a different module, profile or expired timestamp is rejected by the legacy endpoint.
- A profile whose `[NFe] Ambiente` is malformed or missing fails without falling back to production.
- A request audit snapshot remains unchanged after its release row is later edited or deactivated.

---

### Task 1: Release Registry and Selection Resolver

**Files:**
- Create: `src/Repository/AcbrLibraryReleaseRepository.php`
- Create: `src/Service/Legacy/AcbrLibraryReleaseResolver.php`
- Modify: `config/services.yaml`
- Test: `tests/Repository/AcbrLibraryReleaseRepositoryTest.php`
- Test: `tests/Service/Legacy/AcbrLibraryReleaseResolverTest.php`

**Interfaces:**
- Produces `AcbrLibraryReleaseRepository::findActive(string $module, string $environment, \DateTimeInterface $at): array`.
- Produces `AcbrLibraryReleaseResolver::resolveNfe(?string $profile, \DateTimeInterface $at): AcbrLibraryReleaseSelection`.
- `AcbrLibraryReleaseSelection` exposes release id, module, environment, profile, release version, canonical path and SHA-256.

- [ ] **Step 1: Write failing repository tests**

Create an in-memory DBAL schema mirroring `t99037_acbr_library_release`; assert exact start/end semantics, inactive rows, no match, and overlapping active periods.

- [ ] **Step 2: Run the repository test to verify it fails**

Run: `php tests/Repository/AcbrLibraryReleaseRepositoryTest.php`

Expected: FAIL because the repository does not exist.

- [ ] **Step 3: Implement the registry schema and repository**

Implement `AcbrLibraryReleaseRepository` using `app.audit_connection`. Add PostgreSQL schema creation with a range exclusion constraint for active ranges, and portable validation for test SQLite. Add `findActive()` that returns no row or throws for ambiguity; never silently chooses an arbitrary row.

- [ ] **Step 4: Run the repository test to verify it passes**

Run: `php tests/Repository/AcbrLibraryReleaseRepositoryTest.php`

Expected: `OK`.

- [ ] **Step 5: Write failing resolver tests**

Use temporary INI fixtures and `.so` fixture files to assert environment parsing, allowed-root enforcement, realpath normalization, SHA-256 verification, profile existence and no production fallback for invalid configuration.

- [ ] **Step 6: Implement `AcbrLibraryReleaseResolver::resolveNfe()`**

Resolve the INI path through `AcbrIniProfileResolver`; read `[NFe] Ambiente` as `2 => homologacao` and `1 => producao`; request the active release; validate the release path and hash; return an immutable selection object.

- [ ] **Step 7: Run focused resolver tests**

Run: `php tests/Service/Legacy/AcbrLibraryReleaseResolverTest.php`

Expected: `OK`.

### Task 2: Signed Internal Selection and Legacy Loader

**Files:**
- Create: `src/Service/Legacy/AcbrLibrarySelectionSigner.php`
- Modify: `src/Service/Legacy/AcbrLegacyScriptExecutor.php`
- Modify: `ACBrComum/ACBrComum.php`
- Modify: `NFe/MT/ACBrNFeServicosMT.php`
- Modify: `NFe/MT/ACBrNFeMT.php`
- Test: `tests/Service/Legacy/AcbrLibrarySelectionSignerTest.php`
- Test: `tests/Assets/AcbrNfeLibraryLoaderTest.php`

**Interfaces:**
- Consumes `AcbrLibraryReleaseSelection` from Task 1.
- Produces `AcbrLibrarySelectionSigner::sign(AcbrLibraryReleaseSelection $selection, int $ttlSeconds): string`.
- Produces `AcbrLibrarySelectionSigner::verify(string $token, string $module): array`.
- Extends `CarregaDll(string $dir, string $libraryName, ?string $approvedPath = null): string|int`.

- [ ] **Step 1: Write failing signer tests**

Assert a valid token verifies, and modified signature, expired token, changed module and changed profile fail.

- [ ] **Step 2: Run the signer test to verify it fails**

Run: `php tests/Service/Legacy/AcbrLibrarySelectionSignerTest.php`

Expected: FAIL because the signer does not exist.

- [ ] **Step 3: Implement signed selection transport**

Add an environment secret dedicated to library selection. The signer must encode release id, module, profile, canonical path, SHA-256 and expiration, then authenticate the payload with HMAC-SHA256. `AcbrLegacyScriptExecutor` resolves and signs the NFe selection before its internal cURL call, and sends it in a dedicated internal header.

- [ ] **Step 4: Write failing loader tests**

Assert the legacy script rejects a public `ACBrLibraryPath`/release parameter, accepts only a valid signed internal token, and invokes `CarregaDll()` with the verified canonical path.

- [ ] **Step 5: Implement verified loading and NFE version reporting**

Make the legacy endpoint verify the internal selection, reject public overrides and pass its verified path to `CarregaDll()`. Keep existing lookup only as an explicit development fallback. Add a read-only `versao` method backed by existing `NFEVersao()` that returns the loaded ACBr version and selected release metadata.

- [ ] **Step 6: Run the signer and loader tests**

Run: `php tests/Service/Legacy/AcbrLibrarySelectionSignerTest.php && php tests/Assets/AcbrNfeLibraryLoaderTest.php`

Expected: both print `OK`.

### Task 3: Request Snapshot and Operational Administration

**Files:**
- Modify: `src/Repository/ApiAuditRepository.php`
- Modify: `src/Service/Api/ApiAuditManager.php`
- Create: `src/Controller/AcbrLibraryReleaseController.php`
- Create: `templates/admin/acbr_library_releases.html.twig`
- Modify: `templates/admin/base.html.twig`
- Modify: `src/EventSubscriber/AuthGateSubscriber.php`
- Test: `tests/Repository/ApiAuditLibraryReleaseSnapshotTest.php`
- Test: `tests/Controller/AcbrLibraryReleasePageTest.php`

**Interfaces:**
- Consumes `AcbrLibraryReleaseSelection` resolved in Task 1.
- Produces release snapshot fields on `t99001` and a `super_admin` administration route at `/bibliotecas-acbr`.

- [ ] **Step 1: Write failing audit snapshot tests**

Create the minimal `t99001` test table and assert that the selected release id, environment, declared version, path, hash, profile and reported ACBr version are stored with a request and remain immutable after registry changes.

- [ ] **Step 2: Implement audit schema compatibility and snapshot persistence**

Add additive columns to `t99001` only when absent. Extend `ApiAuditManager` and `ApiAuditRepository` to store the selection snapshot before legacy execution and the `NFE_Versao` result after execution without exposing secrets.

- [ ] **Step 3: Run the audit snapshot test**

Run: `php tests/Repository/ApiAuditLibraryReleaseSnapshotTest.php`

Expected: `OK`.

- [ ] **Step 4: Write failing administration page tests**

Assert `super_admin` can render the registry page, non-admin users are denied, and invalid environment, checksum, profile, overlap and unsafe path are rejected.

- [ ] **Step 5: Implement the restricted registry page and save endpoint**

Follow `OperationsAdminController` and `execution_config.html.twig` patterns. Restrict routes to `super_admin`; list historical rows; create and deactivate records; validate the installed artifact rather than accepting uploads.

- [ ] **Step 6: Run administration page tests**

Run: `php tests/Controller/AcbrLibraryReleasePageTest.php`

Expected: `OK`.

### Task 4: Homologation Matrix and Documentation

**Files:**
- Modify: `docs/MANUAL_OPERACIONAL_PROJETO.md`
- Modify: `docs/CONTEXTO_SESSAO_ATUAL.md`
- Modify: `docs/GUIA_RETORNO_RAPIDO.md`
- Create: `testes_api_platform/nfe_library_release_homologacao.sh`
- Test: `tests/Assets/AcbrLibraryReleaseDocumentationTest.php`

**Interfaces:**
- Consumes release administration and version endpoint from Tasks 1–3.
- Produces a repeatable homologation command that records the selected release before exercising NFe flows.

- [ ] **Step 1: Write a failing documentation/script contract test**

Assert the test script performs a version query, includes release/profile metadata in its output and covers status, consultation, distribution, emission, event, inutilization, DANFE/PDF and email paths.

- [ ] **Step 2: Implement the homologation script and operational documentation**

Use the project endpoint convention and require explicit test credentials through environment variables. Document artifact installation, checksum capture, homologation scheduling, production promotion and rollback by new registry validity.

- [ ] **Step 3: Run the documentation contract test**

Run: `php tests/Assets/AcbrLibraryReleaseDocumentationTest.php`

Expected: `OK`.

### Task 5: End-to-End Verification

**Files:**
- Verify all files changed in Tasks 1–4.

- [ ] **Step 1: Run syntax checks**

Run: `php -l src/Repository/AcbrLibraryReleaseRepository.php && php -l src/Service/Legacy/AcbrLibraryReleaseResolver.php && php -l src/Service/Legacy/AcbrLibrarySelectionSigner.php && php -l src/Controller/AcbrLibraryReleaseController.php && php -l NFe/MT/ACBrNFeServicosMT.php && php -l ACBrComum/ACBrComum.php`

Expected: every command reports no syntax errors.

- [ ] **Step 2: Run focused automated tests**

Run: `php tests/Repository/AcbrLibraryReleaseRepositoryTest.php && php tests/Service/Legacy/AcbrLibraryReleaseResolverTest.php && php tests/Service/Legacy/AcbrLibrarySelectionSignerTest.php && php tests/Assets/AcbrNfeLibraryLoaderTest.php && php tests/Repository/ApiAuditLibraryReleaseSnapshotTest.php && php tests/Controller/AcbrLibraryReleasePageTest.php && php tests/Assets/AcbrLibraryReleaseDocumentationTest.php`

Expected: every script prints `OK`.

- [ ] **Step 3: Run container and Twig validation**

Run: `php bin/console lint:container && php bin/console lint:twig templates/admin/acbr_library_releases.html.twig`

Expected: both commands succeed.

- [ ] **Step 4: Run homologation only with approved credentials and a non-production release**

Run: `bash testes_api_platform/nfe_library_release_homologacao.sh`

Expected: selected version is reported and all enabled homologation checks complete without production fiscal effects.
