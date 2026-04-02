<?php

declare(strict_types=1);

namespace Autenticazione;

use Config\Database;
use Config\Response;
use Config\Session;
use Config\Utils;
use Config\Validator;

class Servizi
{
    private array $data;
    private Database $db;
    private Session $session;
    private Utils $utils;

    public function __construct(array $data, Database $db, Session $session, Utils $utils)
    {
        $this->data = $data;
        $this->db = $db;
        $this->session = $session;
        $this->utils = $utils;
    }

    public function execute(): void
    {
        $azione = $this->data['azione'] ?? '';

        switch ($azione) {
            case 'login':
                $this->login();
                return;

            case 'logout':
                $this->logout();
                return;

            default:
                Response::error('Azione non valida', 400);
        }
    }

    private function login(): void
    {
        $validator = (new Validator($this->data))
            ->required('username')
            ->required('password');

        if ($validator->fails()) {
            Response::validation($validator->errors(), 'Username e password sono obbligatori');
        }

        $rows = $this->db->selectQuery(
            'SELECT id, username, password
             FROM utenti
             WHERE username = :username
             LIMIT 1',
            [
                ':username' => $this->data['username']
            ]
        );

        $utente = $rows[0] ?? null;
        $isPasswordValid = is_array($utente)
            && isset($utente['password'])
            && password_verify((string) $this->data['password'], (string) $utente['password']);

        if (!$isPasswordValid) {
            Response::error('Credenziali non valide', 401);
        }

        $this->session->set('utente_id', (int) $utente['id']);

        Response::ok([
            'utente_id' => (int) $utente['id'],
            'username' => (string) ($utente['username'] ?? '')
        ], 'Login effettuato con successo');
    }

    private function logout(): void
    {
        $this->session->destroy();
        Response::ok([], 'Logout effettuato con successo');
    }
}
