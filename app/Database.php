<?php
declare(strict_types=1);

class Database {
    private static ?PDO $instance = null;

    public static function get(): PDO {
        if (self::$instance === null) {
            $host = DB_HOST;
            $port = defined('DB_PORT') ? DB_PORT : '3306';
            $name = DB_NAME;

            // Auto-enable SSL for Azure MySQL via DSN parameter
            $ssl = '';
            if (str_contains($host, 'azure.com') || str_contains($host, 'mysql.database') || (defined('DB_SSL') && DB_SSL)) {
                $ssl = ';sslmode=required';
            }

            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4{$ssl}";

            self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
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
