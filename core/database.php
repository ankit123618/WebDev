<?php
declare(strict_types=1);

namespace core;

use PDO;
use PDOException;
/**
 * Lazily creates and reuses the application's PDO database connection.
 *
 * Connection settings come from the environment service and failures are logged.
 */
class database {

    private ?PDO $pdo = null;

    /**
     * Stores the configuration and logger dependencies used for connection setup.
     */
    public function __construct(private env $env, private \Core\logger $logger)
    {
    }

    /**
     * Returns a connected PDO instance, creating it the first time it is needed.
     */
    public function connect(): PDO
    {
        if (!$this->pdo) {
            $host = (string) $this->env->get('DB_HOST', '127.0.0.1');
            $port = (string) $this->env->get('DB_PORT', '3306');
            $db   = (string) $this->env->get('DB_NAME', '');
            $user = (string) $this->env->get('DB_USER', '');
            $pass = (string) $this->env->get('DB_PASS', '');
            $charset = (string) $this->env->get('DB_CHARSET', 'utf8');
            if ($charset === 'utf-8') {
                $charset = 'utf8';
            }

            try {
                $this->pdo = new PDO(
                    "mysql:host=$host;port=$port;dbname=$db;charset=$charset",
                    $user,
                    $pass,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                    ]
                );
            } catch (PDOException $e) {
                $this->logger->exception($e, 'Database connection failed', [
                    'db_host' => $host,
                    'db_port' => $port,
                    'db_name' => $db,
                    'db_user' => $user,
                ]);
                throw $e;
            }
        }

        return $this->pdo;
    }

}
