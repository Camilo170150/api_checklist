<?php
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $port;
    public $conn;

    public function __construct() {
        // Soporta DATABASE_URL (Railway) o variables sueltas
        $databaseUrl = getenv('DATABASE_URL') ?: getenv('CLEARDB_DATABASE_URL') ?: '';
        if ($databaseUrl) {
            $parts = parse_url($databaseUrl);
            $this->host = $parts['host'] ?? 'localhost';
            $this->username = $parts['user'] ?? 'root';
            $this->password = $parts['pass'] ?? '';
            $this->db_name = isset($parts['path']) ? ltrim($parts['path'], '/') : 'railway';
            $this->port = $parts['port'] ?? 3306;
        } else {
            $this->host = getenv('DB_HOST') ?: 'localhost';
            $this->db_name = getenv('DB_NAME') ?: 'railway';
            $this->username = getenv('DB_USER') ?: 'root';
            $this->password = getenv('DB_PASS') ?: '';
            $this->port = getenv('DB_PORT') ?: 3306;
        }
    }

    public function getConnection() {
        $this->conn = null;

        try {
            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->db_name};charset=utf8mb4";
            $this->conn = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $exception) {
            // No imprimir secrets en producción. Loguear y devolver mensaje genérico.
            error_log('DB connection error: ' . $exception->getMessage());
            echo "Error de conexión a la base de datos.";
        }

        return $this->conn;
    }
}
?>
