<?php
declare(strict_types=1);

class Database {
    private static ?PDO $instance = null;

    public static function get(): PDO {
        if (self::$instance === null) {
            $host = DB_HOST;
            $port = defined('DB_PORT') ? DB_PORT : '3306';
            $name = DB_NAME;

            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            // Azure MySQL requires SSL — use MYSQLI_CLIENT_SSL via PDO driver options
            $isAzure = str_contains($host, 'azure.com') || str_contains($host, 'mysql.database');
            $forceSsl = defined('DB_SSL') && DB_SSL;
            if ($isAzure || $forceSsl) {
                // Use PDO MySQL driver-level SSL options (compatible with PHP 8.x)
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
                // Empty string means use system CA store (works on Azure App Service)
                $options[PDO::MYSQL_ATTR_SSL_CA] = '';
            }

            self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
        }
        return self::$instance;
    }

    public static function query(string $sql, array $params = []): PDOStatement {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetchAll(string $sql, array $params = []): array {
        return self::query($sql, $params)->fetchAll();
    }

    public static function fetchOne(string $sql, array $params = []): array|false {
        return self::query($sql, $params)->fetch();
    }

    public static function execute(string $sql, array $params = []): int {
        return self::query($sql, $params)->rowCount();
    }

    public static function insert(string $sql, array $params = []): int|string {
        self::query($sql, $params);
        return self::get()->lastInsertId();
    }
}
