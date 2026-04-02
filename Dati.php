<?php

declare(strict_types=1);

namespace Backend;

class Dati
{
    private array $data;
    private $db;
    private $session;
    private $utils;

    public function __construct(array $data, $db, $session, $utils)
    {
        $this->data = $data;
        $this->db = $db;
        $this->session = $session;
        $this->utils = $utils;
    }

    public function execute(): void
    {
        $this->callMotoreBackend();
    }

    public function callMotoreBackend(): void
    {
        $payload = $this->data;
        $payload['area'] = 'Dati';

        $motoreBackend = new MotoreBackend($this->db, $this->session, $this->utils);
        $motoreBackend->callModulo($payload);
    }
}

