<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Config\Database;
use Config\Env;
use Assets\Response;
use Assets\Session;
use Assets\Utils;
use Assets\Validator;

//IL numero di rotte è limitato e definito in modo statico 
//per evitare problemi di sicurezza e semplificare la gestione del codice
const ROUTES = [
    'dati' => [
        'file' => 'Dati.php',
        'class' => 'Backend\\Dati'
    ],
    'gestioni' => [
        'file' => 'Gestioni.php',
        'class' => 'Backend\\Gestioni'
    ],
    'servizi' => [
        'file' => 'Servizi.php',
        'class' => 'Backend\\Servizi'
    ],
];

try {
    //Caricamento variabili ambiente
    Env::load(__DIR__);

    $db = new Database();
    $session = new Session($db);
    $utils = new Utils($db);

    //Recupero e validazione dati JSON 
    //(Implementare anche la parte per urlencoded/form-data se necessario)
    $json = file_get_contents('php://input');
    if ($json === false || trim($json) === '') {
        Response::error('Body richiesta mancante', 400);
    }

    //Decodifica JSON in Array associativo
    $JsonParsed = json_decode($json, true);
    if (!is_array($JsonParsed)) {
        Response::error('JSON non valido', 400);
    }

    //Sanitizzazione dati
    $dataSanitized = $utils->sanitizeMixedArray($JsonParsed);
    if (!is_array($dataSanitized)) {
        Response::error('Payload non valido', 400);
    }

    //Validazione dati obbligatori
    $validator = (new Validator($dataSanitized))
        ->required('area')
        ->in('area', array_keys(ROUTES), 'Area non valida')
        ->required('modulo')
        ->regex('modulo', '/^[A-Za-z][A-Za-z0-9_]*$/', 'Nome modulo non valido')
        ->required('azione');

    if ($validator->fails()) {
        Response::validation($validator->errors(), 'Parametri richiesta non validi');
    }

    //Controllo sessione
    if ($dataSanitized['modulo'] !== 'Autenticazione' && !$session->isLoggedIn()) {
        Response::error('Utente non autenticato', 401);
    }

    //Routing verso l'area e modulo specificati
    $route = ROUTES[$dataSanitized['area']];
    $file = __DIR__ . '/' . $route['file'];
    if (!is_file($file)) {
        Response::error('File area non trovato', 500);
    }

    //Inclusione file area e istanziazione classe handler
    require_once $file;

    //Controllo esistenza classe handler
    $class = $route['class'];
    if (!class_exists($class)) {
        Response::error('Classe area non trovata', 500);
    }

    //Esecuzione handler area
    $handler = new $class($dataSanitized, $db, $session, $utils);
    $handler->execute();

} catch (\Throwable $e) {
    Response::error('Errore interno del server: ' . $e->getMessage(), 500);
}
