# Mini Framework Backend PHP - Documentazione Completa

## 1) Obiettivo del framework
Questo progetto è un mini framework backend in PHP pensato per:

- avere un solo punto di ingresso (`router.php`)
- instradare richieste verso aree (`dati`, `gestioni`, `servizi`)
- delegare la logica ai moduli (`Autenticazione`, `Pazienti`, ecc.)
- uniformare validazioni e risposte JSON
- gestire email SMTP e logging su database

Il framework è adatto a progetti gestionali o API custom con struttura modulare.

---

## 2) Struttura del progetto (non vendor)

### File principali root
- `router.php` -> entrypoint HTTP
- `Dati.php` -> dispatcher area `dati`
- `Gestioni.php` -> dispatcher area `gestioni`
- `Servizi.php` -> dispatcher area `servizi`
- `MotoreBackend.php` -> risoluzione dinamica modulo/area

### Moduli
- `Autenticazione/Servizi.php` -> modulo autenticazione (login/logout)

### Config
- `config/Database.php` -> connessione DB + query helper + logging su DB
- `config/Session.php` -> gestione sessione utente
- `config/Utils.php` -> sanificazione input e URL helper
- `config/Response.php` -> risposte JSON standard
- `config/Validator.php` -> validazione campi input
- `config/SendEmail.php` -> invio email via SMTP con config da DB

### Tabelle SQL
- `tables/smtp_config.sql` -> tabella configurazione SMTP
- `tables/logs.sql` -> tabella logs applicativi

---

## 3) Autoload e namespace
Da `composer.json`:

- `Config\\` -> cartella `config/`
- `Autenticazione\\` -> cartella `Autenticazione/`
- `Backend\\` -> root progetto (`Dati.php`, `Servizi.php`, ecc.)

Se aggiungi nuove classi:
1. rispetta namespace e cartella
2. esegui `composer dump-autoload -o`

---

## 4) Flusso completo di una richiesta

### Step A: arrivo richiesta in `router.php`
`router.php`:
1. carica autoload
2. istanzia `Database`, `Session`, `Utils`
3. legge JSON da `php://input`
4. valida che il JSON sia corretto
5. sanitizza tutto con `Utils::sanitizeMixedArray()`
6. valida campi obbligatori con `Validator`:
   - `area` obbligatoria e deve essere tra `dati|gestioni|servizi`
   - `modulo` obbligatorio (non esiste default automatico)
   - `azione` obbligatoria
7. se `modulo != Autenticazione`, richiede sessione attiva
8. instrada verso classe area (`Backend\Dati`, `Backend\Gestioni`, `Backend\Servizi`)
9. chiama `execute()` sull’handler area

In caso errore, usa sempre `Response::error(...)` o `Response::validation(...)`.

### Step B: dispatcher area (`Dati.php`, `Gestioni.php`, `Servizi.php`)
Ogni classe area:
1. prende `$data`, `$db`, `$session`, `$utils`
2. in `execute()` chiama `callMotoreBackend()`
3. imposta `area` nel payload con nome classe modulo corretto:
   - `Dati`
   - `Gestioni`
   - `Servizi`
4. delega a `MotoreBackend`

### Step C: `MotoreBackend.php`
`MotoreBackend::callModulo($data)`:
1. legge `modulo` e `area`
2. costruisce path file modulo:
   - `__DIR__ . '/' . $modulo . '/' . $area . '.php'`
3. include il file
4. costruisce FQCN modulo:
   - `$modulo . '\\' . $area`
5. istanzia classe modulo e chiama `execute()`

Esempio:
- payload: `{"area":"servizi","modulo":"Autenticazione","azione":"login"}`
- area dispatcher imposta `area = 'Servizi'`
- motore cerca: `Autenticazione/Servizi.php`
- classe attesa: `Autenticazione\Servizi`

---

## 5) Contratto payload JSON

Richiesta tipica:

```json
{
  "area": "servizi",
  "modulo": "Autenticazione",
  "azione": "login",
  "username": "demo",
  "password": "secret"
}
```

Campi obbligatori lato router:
- `area`
- `modulo`
- `azione`

Nota importante:
- `Autenticazione` NON è modulo default automatico.
- È un modulo speciale del framework da usare quando serve autenticare utente.

---

## 6) Risposte standard (`config/Response.php`)

### Metodi principali
- `Response::ok($data = [], $message = '...')`
- `Response::error($error, $statusCode = 400)`
- `Response::validation($errors, $message = 'Dati non validi')`
- `Response::json($payload, $statusCode = 200)`

### Comportamento
- imposta `http_response_code`
- imposta header `application/json`
- stampa JSON
- termina la richiesta (`exit`) per default

### Payload helper (senza output immediato)
- `okPayload(...)`
- `errorPayload(...)`
- `validationPayload(...)`
- `encode(...)`

Questi helper sono utili quando vuoi costruire una stringa JSON senza interrompere il flusso (es. `SendEmail`).

---

## 7) Validazioni (`config/Validator.php`)

`Validator` è fluente:

```php
$validator = (new Validator($data))
    ->required('email')
    ->email('email')
    ->min('password', 8);
```

Metodi disponibili:
- `required`
- `email`
- `min`
- `max`
- `numeric`
- `in`
- `regex`
- `fails`, `passes`, `errors`, `first`

Se una validazione fallisce:
- aggiunge errori in array
- non lancia eccezioni
- decide il chiamante se bloccare o no il flusso

---

## 8) Database (`config/Database.php`)

### Responsabilità
- connessione PDO MySQL
- helper query:
  - `selectQuery`
  - `insertQuery`
  - `updateQuery`
  - `deleteQuery`
- logging applicativo:
  - `writeLog(...)`
  - `writeLogNonBlocking(...)`
  - `getLastLogError()`

### Logging su DB
`writeLog(...)` salva:
- `posto`
- `operazione`
- `operatore`
- `file`
- `dati_mandati` (JSON)
- `scritto_il` (datetime)

Se fallisce, ritorna `false` e puoi leggere errore con `getLastLogError()`.

`writeLogNonBlocking(...)` è fire-and-forget:
- non blocca mai il processo principale
- ideale per logging best effort

Esempio:

```php
$db->writeLogNonBlocking(
    'router',
    'login',
    'utente_42',
    __FILE__,
    $dataSanitized
);
```

---

## 9) Sessioni (`config/Session.php`)

`Session` incapsula l’uso di `$_SESSION`:
- `set($key, $value)`
- `get($key)`
- `delete($key)`
- `destroy()`
- `isLoggedIn()` -> controlla `$_SESSION['utente_id']`
- `getUtente()` -> query DB sull’utente in sessione

---

## 10) Utility (`config/Utils.php`)

`Utils::sanitizeMixedArray()`:
- trim stringhe
- rimuove caratteri di controllo
- rimuove tag HTML (default)
- supporta array annidati ricorsivamente

`getUrl()` e `getBackendUrl()` forniscono URL base configurati.

---

## 11) Modulo autenticazione (`Autenticazione/Servizi.php`)

### Azioni supportate
- `login`
- `logout`

### `login`
1. valida `username` e `password` con `Validator`
2. cerca utente in tabella `utenti`
3. verifica password con `password_verify`
4. salva `utente_id` in sessione
5. risponde con `Response::ok`

Se credenziali errate:
- `Response::error('Credenziali non valide', 401)`

### `logout`
1. distrugge sessione
2. risponde `ok`

---

## 12) Email (`config/SendEmail.php`)

`SendEmail` usa PHPMailer e legge SMTP da DB (`smtp_config`):

### Metodo principale
`send(string $to, string $subject, string $body, string $cc = '', string $bcc = '', ?array $files = null): string`

### Funzionalità
- validazione base input (`to`, `subject`, `body`)
- lettura config SMTP attiva da DB
- supporto `CC`/`BCC` separati da `;`
- supporto allegati da `$_FILES['allegati']` o array custom
- ritorna sempre JSON string (non interrompe il flusso)

### Esempio

```php
$mailer = new \Config\SendEmail($db);
$result = $mailer->send(
    'utente@example.com',
    'Oggetto',
    '<p>Messaggio</p>',
    'cc1@example.com;cc2@example.com',
    'bcc@example.com',
    $_FILES
);
```

---

## 13) Tabelle SQL

### `tables/smtp_config.sql`
Tabella configurazione SMTP.
Campi principali:
- `host`, `port`, `secure`, `username`, `password`
- `from_email`, `from_name`
- `is_active`

`SendEmail` prende la configurazione con:

```sql
SELECT host, port, secure, username, password, from_email, from_name
FROM smtp_config
WHERE is_active = 1
ORDER BY id DESC
LIMIT 1
```

### `tables/logs.sql`
Tabella logs applicativi.
Campi:
- `posto`, `operazione`, `operatore`, `file`
- `dati_mandati` JSON
- `scritto_il`
- indici su operatore/operazione/posto/data

---

## 14) Formato risposte JSON

### Successo

```json
{
  "status": "ok",
  "message": "Operazione completata",
  "data": {}
}
```

### Errore

```json
{
  "status": "ko",
  "error": "Descrizione errore"
}
```

### Errore validazione

```json
{
  "status": "ko",
  "error": "Dati non validi",
  "validation_errors": {
    "campo": ["messaggio errore"]
  }
}
```

---

## 15) Checklist per creare un nuovo modulo

1. Crea cartella modulo, es. `Pazienti/`
2. Crea file area che ti serve, es. `Pazienti/Servizi.php`
3. Namespace file: `namespace Pazienti;`
4. Classe: `class Servizi`
5. Implementa `execute()` e switch su `azione`
6. Usa `Validator` per input
7. Usa `Response` per output

Template base modulo:

```php
<?php

declare(strict_types=1);

namespace Pazienti;

use Config\Response;
use Config\Validator;

class Servizi
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
        $azione = $this->data['azione'] ?? '';

        switch ($azione) {
            case 'ping':
                Response::ok(['pong' => true], 'Modulo Pazienti operativo');
                return;
            default:
                Response::error('Azione non valida', 400);
        }
    }
}
```

---

## 16) Convenzioni operative consigliate

- valida sempre input con `Validator`
- rispondi sempre con `Response`
- usa `writeLogNonBlocking` per audit senza bloccare flussi
- non esporre eccezioni raw in produzione
- mantieni nomi moduli/namespace coerenti con cartelle

---

## 17) Note importanti attuali

- Connessione DB in `Database.php` è ancora hardcoded (`localhost`, `my_database`, `root`, password vuota): da spostare in `.env` o config protetta.
- `router.php` attualmente gestisce solo JSON body.
- `Autenticazione` è modulo pubblico per login/logout, ma non è default automatico.

---

## 18) Quick start test API

### Login

```json
{
  "area": "servizi",
  "modulo": "Autenticazione",
  "azione": "login",
  "username": "demo",
  "password": "secret"
}
```

### Logout

```json
{
  "area": "servizi",
  "modulo": "Autenticazione",
  "azione": "logout"
}
```

Se vuoi chiamare altri moduli, imposta:
- `modulo` = nome cartella modulo
- `area` = `dati|gestioni|servizi`
- `azione` = metodo logico da eseguire dentro `execute()`

