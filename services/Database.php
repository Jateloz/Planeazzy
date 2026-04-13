<?php
require_once dirname(__DIR__). '/config/config.php';

class Database {
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct() {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => true,  // allows reusing named params in same query
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
        ];
        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
        } catch (PDOException $e) {
            error_log('DB: ' . $e->getMessage());
            throw new RuntimeException('Database unavailable.');
        }
    }

    public static function getInstance(): Database {
        if (!self::$instance) self::$instance = new self();
        return self::$instance;
    }

    public function query(string $sql, array $p = []): PDOStatement {
        $s = $this->pdo->prepare($sql); $s->execute($p); return $s;
    }
    public function fetchOne(string $sql, array $p = []): ?array {
        return $this->query($sql,$p)->fetch() ?: null;
    }
    public function fetchAll(string $sql, array $p = []): array {
        return $this->query($sql,$p)->fetchAll();
    }
    public function insert(string $sql, array $p = []): int {
        $this->query($sql,$p); return (int)$this->pdo->lastInsertId();
    }
    public function beginTransaction(): void { $this->pdo->beginTransaction(); }
    public function commit(): void           { $this->pdo->commit(); }
    public function rollback(): void         { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); }
    private function __clone() {}
}
