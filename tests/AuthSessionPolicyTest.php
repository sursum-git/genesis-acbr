<?php

declare(strict_types=1);

use App\Repository\Auth\AuthSchemaManager;
use App\Repository\Auth\AuthUserRepository;
use App\Service\Auth\AuthTokenService;
use App\Service\Auth\CurrentUserContext;
use Doctrine\DBAL\DriverManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

require dirname(__DIR__) . '/vendor/autoload.php';

function assertSessionPolicyTrue(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$frameworkConfig = file_get_contents(dirname(__DIR__) . '/config/packages/framework.yaml');
assertSessionPolicyTrue(is_string($frameworkConfig), 'Could not read framework session config.');
assertSessionPolicyTrue(str_contains($frameworkConfig, 'gc_maxlifetime: 3600'), 'Admin session must expire after 1 hour of inactivity.');
assertSessionPolicyTrue(str_contains($frameworkConfig, 'cookie_lifetime: 0'), 'Admin session cookie must remain a browser-session cookie.');

$connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
$connection->executeStatement('CREATE TABLE t00002 (id_t00002 INTEGER PRIMARY KEY AUTOINCREMENT, c_nome TEXT, c_identificador TEXT, c_token TEXT, log_ativo INTEGER DEFAULT 1)');
(new AuthSchemaManager($connection))->ensureSchema();

$request = Request::create('/logout');
$session = new Session(new MockArraySessionStorage());
$session->set(CurrentUserContext::SESSION_KEY, ['id' => 1, 'subscriber_id' => null]);
$session->set('unrelated_session_data', 'must be cleared');
$request->setSession($session);

$requestStack = new RequestStack();
$requestStack->push($request);

$context = new CurrentUserContext(
    $requestStack,
    new AuthUserRepository($connection),
    new AuthTokenService('test-secret')
);
$context->logoutSession();

assertSessionPolicyTrue(!$session->has(CurrentUserContext::SESSION_KEY), 'Logout must clear the authenticated user.');
assertSessionPolicyTrue(!$session->has('unrelated_session_data'), 'Logout must invalidate the whole session.');

fwrite(STDOUT, "OK\n");
