<?php
require_once dirname(__DIR__). '/config/config.php';

class Database {
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct() {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => true,
            // Keep ONLY the charset here
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
        ];
        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
            
            // RUN THE TIMEZONE COMMAND SEPARATELY HERE
            $this->pdo->exec("SET time_zone = '+03:00'");

        } catch (PDOException $e) {
            $msg = $e->getMessage();
            error_log('[Planeazzy DB] ' . $msg);
            // Show helpful error in production
            if (defined('APP_ENV') && APP_ENV === 'production') {
                $hint = '';
                if (str_contains($msg, 'Unknown database')) {
                    $hint = ' — Run config/schema_fresh.sql in phpMyAdmin to create the database.';
                } elseif (str_contains($msg, 'Access denied')) {
                    $hint = ' — Check DB_USER and DB_PASS in config/config.php.';
                } elseif (str_contains($msg, 'Connection refused') || str_contains($msg, "can't connect")) {
                    $hint = ' — Make sure MySQL/MariaDB is running in XAMPP Control Panel.';
                }
                throw new RuntimeException('Database unavailable: ' . $msg . $hint);
            }
            throw new RuntimeException('Database unavailable. Please try again later.');
        }
    }

    public static function getInstance(): Database {
        if (!self::$instance) self::$instance = new self();
        return self::$instance;
    }

    public function query(string $sql, array $p = []): PDOStatement {
        $s = $this->pdo->prepare($sql);
        $s->execute($p);
        return $s;
    }
    public function fetchOne(string $sql, array $p = []): ?array {
        return $this->query($sql, $p)->fetch() ?: null;
    }
    public function fetchAll(string $sql, array $p = []): array {
        return $this->query($sql, $p)->fetchAll();
    }
    public function insert(string $sql, array $p = []): int {
        $this->query($sql, $p);
        return (int)$this->pdo->lastInsertId();
    }
    public function beginTransaction(): void { $this->pdo->beginTransaction(); }
    public function commit(): void           { $this->pdo->commit(); }
    public function rollback(): void         {
        if ($this->pdo->inTransaction()) $this->pdo->rollBack();
    }
    private function __clone() {}
}
