<?php

namespace Config;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

class Database
{
    private $host = 'localhost';
    private $db_name = 'my_database';
    private $username = 'root';
    private $password = '';
    private $conn;
    private $lastLogError = null;

    public function __construct()
    {
        $this->conn = $this->connect();
    }

    private function connect()
    {
        $this->conn = null;

        try {
            $this->conn = new PDO('mysql:host=' . $this->host . ';dbname=' . $this->db_name, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw new RuntimeException('Connection Error: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }

        return $this->conn;
    }

    public function selectQuery($query, $params = [])
    {
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
