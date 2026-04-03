<?php

declare(strict_types=1);

namespace Backend;

use Assets\Response;

class MotoreBackend
{
    private $db;
    private $session;
    private $utils;

    public function __construct($db, $session, $utils)
    {
        $this->db = $db;
        $this->session = $session;
        $this->utils = $utils;
    }

    public function callModulo($data)
    {
        $modulo = $data['modulo'];
        $area = $data['area'];

        $file = __DIR__ . '/' . $modulo . '/' . $area . '.php';
        if (!is_file($file)) {
            Response::error('File modulo non trovato', 404, [
                'modulo' => $modulo,
                'area' => $area
            ]);
        }

        require_once $file;

        $class = $modulo . '\\' . $area;
        if (!class_exists($class)) {
            Response::error('Classe modulo non trovata', 500, [
                'class' => $class
            ]);
        }

        $object = new $class($data, $this->db, $this->session, $this->utils);
        $object->execute();
    }
}

