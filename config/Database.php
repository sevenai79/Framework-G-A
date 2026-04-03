<?php

declare(strict_types=1);

namespace Config;

use Assets\Validator;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

class Database
{
    private string $host;
    private int $port;
    private string $dbName;
    private string $username;
    private string $password;
    private string $charset;
    private ?PDO $conn;
    private ?string $lastLogError = null;

    public function __construct()
    {
        $this->host = Env::getString('DB_HOST', 'localhost') ?? 'localhost';
        $this->port = Env::getInt('DB_PORT', 3306);
        $this->dbName = Env::getString('DB_NAME', 'my_database') ?? 'my_database';
        $this->username = Env::getString('DB_USER', 'root') ?? 'root';
        $this->password = Env::getString('DB_PASS', '') ?? '';
        $this->charset = Env::getString('DB_CHARSET', 'utf8mb4') ?? 'utf8mb4';

        $this->validateConfiguration();
        $this->conn = $this->connect();
    }

    private function connect(): PDO
    {
        $this->conn = null;

        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $this->host,
                $this->port,
                $this->dbName,
                $this->charset
            );

            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new RuntimeException('Connection Error: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }

        return $this->conn;
    }

    private function validateConfiguration(): void
    {
        if ($this->host === '') {
            throw new RuntimeException('Configurazione DB non valida: DB_HOST mancante');
        }

        if ($this->dbName === '') {
            throw new RuntimeException('Configurazione DB non valida: DB_NAME mancante');
        }

        if ($this->username === '') {
            throw new RuntimeException('Configurazione DB non valida: DB_USER mancante');
        }

        if ($this->port < 1 || $this->port > 65535) {
            throw new RuntimeException('Configurazione DB non valida: DB_PORT fuori range');
        }

        if (!preg_match('/^[A-Za-z0-9_]+$/', $this->charset)) {
            throw new RuntimeException('Configurazione DB non valida: DB_CHARSET non supportato');
        }
    }

    public function selectQuery($query, $params = [])
    {
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function insertQuery($query, $params = [])
    {
        $stmt = $this->conn->prepare($query);
        return $stmt->execute($params);
    }

    public function updateQuery($query, $params = [])
    {
        $stmt = $this->conn->prepare($query);
        return $stmt->execute($params);
    }

    public function deleteQuery($query, $params = [])
    {
        $stmt = $this->conn->prepare($query);
        return $stmt->execute($params);
    }

    public function writeLog($posto, $operazione, $operatore, $file, $datiMandati = [], $dataOra = null)
    {
        try {
            $this->lastLogError = null;

            $payload = [
                'posto' => $posto,
                'operazione' => $operazione,
                'operatore' => $operatore,
                'file' => $file,
            ];

            $validator = (new Validator($payload))
                ->required('posto')
                ->required('operazione')
                ->required('operatore')
                ->required('file')
                ->max('posto', 100)
                ->max('operazione', 100)
                ->max('operatore', 100)
                ->max('file', 255);

            if ($validator->fails()) {
                $this->lastLogError = $validator->first() ?? 'Dati log non validi';
                return false;
            }

            if (!is_array($datiMandati)) {
                $datiMandati = ['raw' => $datiMandati];
            }

            $datiJson = json_encode($datiMandati, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($datiJson === false) {
                $this->lastLogError = 'Impossibile serializzare i dati del log';
                return false;
            }

            $timestamp = $this->normalizeLogDateTime($dataOra);
            if ($timestamp === null) {
                $this->lastLogError = 'Formato data/ora non valido';
                return false;
            }

            return $this->insertQuery(
                'INSERT INTO logs (posto, operazione, operatore, file, dati_mandati, scritto_il)
                 VALUES (:posto, :operazione, :operatore, :file, :dati_mandati, :scritto_il)',
                [
                    ':posto' => trim((string) $posto),
                    ':operazione' => trim((string) $operazione),
                    ':operatore' => trim((string) $operatore),
                    ':file' => trim((string) $file),
                    ':dati_mandati' => $datiJson,
                    ':scritto_il' => $timestamp,
                ]
            );
        } catch (Throwable $e) {
            $this->lastLogError = $e->getMessage();
            return false;
        }
    }

    // Variante "fire-and-forget": non interrompe mai il flusso applicativo.
    public function writeLogNonBlocking($posto, $operazione, $operatore, $file, $datiMandati = [], $dataOra = null)
    {
        try {
            $this->writeLog($posto, $operazione, $operatore, $file, $datiMandati, $dataOra);
        } catch (Throwable $e) {
            $this->lastLogError = $e->getMessage();
        }
    }

    public function getLastLogError()
    {
        return $this->lastLogError;
    }

    private function normalizeLogDateTime($value)
    {
        if ($value === null || trim((string) $value) === '') {
            return date('Y-m-d H:i:s');
        }

        $timestamp = strtotime((string) $value);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }
}
