<?php

namespace Assets;

class Session
{
    private $db;
    public function __construct($db)
    {
        $this->db = $db;

        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function set($key, $value)
    {
        $_SESSION[$key] = $value;
    }

    public function get($key)
    {
        return isset($_SESSION[$key]) ? $_SESSION[$key] : null;
    }

    public function delete($key)
    {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }

    public function destroy()
    {
        session_destroy();
    }

    public function isLoggedIn()
    {
        return isset($_SESSION['utente_id']);
    }

    public function getUtente()
    {
        return $this->db->selectQuery(
            "SELECT * FROM utenti WHERE id = :id",
            [
                ":id" => $this->get('utente_id')
            ]
        );
    }
}