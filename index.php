<?php

declare(strict_types=1);

/**
 * Boots the application, registers shared services, loads routes, and dispatches the request.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require __DIR__ . '/vendor/autoload.php';

require_once 'helpers/auth.php';
require_once 'helpers/data.php';
require_once 'helpers/debug.php';
require_once 'helpers/location.php';
require_once 'helpers/request.php';
require_once 'helpers/string.php';
require_once 'helpers/app.php';

require_once 'core/app.php';
require_once 'core/container.php';
require_once 'core/router.php';
require_once 'core/database.php';
require_once 'core/csrf.php';
require_once 'core/env.php';
require_once 'core/logger.php';
require_once 'core/mailer.php';
require_once 'core/debug_auth.php';
require_once 'core/auth.php';
require_once 'core/acl.php';
require_once 'core/cache.php';
require_once 'core/remember_me.php';
require_once 'core/two_factor.php';
require_once 'core/uploader.php';

require_once 'models/user.php';

require_once 'controllers/home.php';
require_once 'controllers/auth.php';
require_once 'controllers/dashboard.php';
require_once 'controllers/admin.php';
require_once 'controllers/upload.php';


$container = new \core\container();
\core\app::setContainer($container);

$container->singleton(\core\env::class, static fn(): \core\env => new \core\env(__DIR__));
$container->singleton(\Core\logger::class, static fn(\core\container $container): \Core\logger => new \Core\logger(
    $container->get(\core\env::class)
));
$container->singleton(\core\database::class, static fn(\core\container $container): \core\database => new \core\database(
    $container->get(\core\env::class),
    $container->get(\Core\logger::class)
));
$container->singleton(\core\csrf::class, static fn(): \core\csrf => new \core\csrf());
$container->singleton(\core\auth::class, static fn(): \core\auth => new \core\auth());
$container->singleton(\core\acl::class, static fn(): \core\acl => new \core\acl());
$container->singleton(\core\cache::class, static fn(\core\container $container): \core\cache => new \core\cache(
    $container->get(\core\env::class)
));
$container->singleton(\core\mailer::class, static fn(\core\container $container): \core\mailer => new \core\mailer(
    $container->get(\core\env::class),
    $container->get(\Core\logger::class)
));
$container->singleton(\core\debug_auth::class, static fn(\core\container $container): \core\debug_auth => new \core\debug_auth(
    $container->get(\core\env::class)
));
$container->singleton(\core\uploader::class, static fn(\core\container $container): \core\uploader => new \core\uploader(
    $container->get(\core\env::class)
));
$container->singleton(\core\remember_me::class, static fn(\core\container $container): \core\remember_me => new \core\remember_me(
    $container->get(\models\user::class),
    $container->get(\Core\logger::class)
));
$container->singleton(\models\user::class, static fn(\core\container $container): \models\user => new \models\user(
    $container->get(\core\database::class),
    $container->get(\Core\logger::class)
));
$container->singleton(\core\router::class, static fn(\core\container $container): \core\router => new \core\router(
    $container,
    $container->get(\Core\logger::class)
));

$container->get(\core\env::class)->load();
$container->get(\core\remember_me::class)->attemptAutoLogin();
behaviour(); 

$router = $container->get(\core\router::class);
$routes = require __DIR__ . '/routes/web.php';
$routes($router);
$router->run();
