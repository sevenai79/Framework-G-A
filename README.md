# Mini Framework Backend PHP

Backend PHP modulare con:

- entrypoint unico (`router.php`)
- dispatch per area (`dati`, `gestioni`, `servizi`)
- moduli dinamici (`Autenticazione`, ecc.)
- validazione input e response JSON standard
- configurazione tramite `.env`
- helper DB/logging e invio email SMTP

## Requisiti

- PHP >= 7.4
- Estensioni PHP: `pdo`, `pdo_mysql`, `json`, `session`
- Composer
- MySQL/MariaDB

## Installazione

1. Installa dipendenze:

```bash
composer install
```

2. Crea il file ambiente:

```bash
cp .env.example .env
```

3. Compila i valori in `.env` (DB e URL).
4. Importa le tabelle SQL:
   - `tables/logs.sql`
   - `tables/smtp_config.sql`
5. Rigenera autoload se aggiungi/sposti classi:

```bash
composer dump-autoload -o
```

## Variabili ambiente (`.env`)

Template in `.env.example`:

```dotenv
APP_ENV=local
APP_DEBUG=true

DB_HOST=localhost
DB_PORT=3306
DB_NAME=my_database
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4

APP_URL=http://localhost/
BACKEND_URL=http://localhost/backend/
```

Note:

- `.env` non va versionato (già ignorato in `.gitignore`).
- In produzione preferisci variabili di sistema.

## Struttura progetto

```text
.
├─ router.php
├─ Dati.php
├─ Gestioni.php
├─ Servizi.php
├─ MotoreBackend.php
├─ composer.json
├─ .env.example
├─ assets/
│  ├─ Response.php
│  ├─ Session.php
│  ├─ Utils.php
│  ├─ Validator.php
│  └─ SendEmail.php
├─ config/
│  ├─ Database.php
│  └─ Env.php
├─ Autenticazione/
│  └─ Servizi.php
└─ tables/
   ├─ logs.sql
   └─ smtp_config.sql
```

## Namespace e autoload

Da `composer.json` (PSR-4):

- `Config\\` -> `config/`
- `Assets\\` -> `assets/`
- `Autenticazione\\` -> `Autenticazione/`
- `Backend\\` -> root progetto

## Flusso richiesta

1. `router.php` carica autoload e variabili ambiente (`Config\Env::load(__DIR__)`).
2. Istanzia `Database`, `Session`, `Utils`.
3. Legge `php://input` (JSON).
4. Sanitizza input (`Utils::sanitizeMixedArray`).
5. Valida campi obbligatori:
   - `area` in `dati|gestioni|servizi`
   - `modulo` (`/^[A-Za-z][A-Za-z0-9_]*$/`)
   - `azione`
6. Se `modulo !== "Autenticazione"` richiede sessione attiva.
7. Esegue dispatcher area (`Backend\Dati|Gestioni|Servizi`).
8. `MotoreBackend` risolve e richiama il modulo finale (`<Modulo>\<Area>`).

## Contratto richiesta JSON

Payload minimo:

```json
{
  "area": "servizi",
  "modulo": "Autenticazione",
  "azione": "login"
}
```

Esempio login:

```json
{
  "area": "servizi",
  "modulo": "Autenticazione",
  "azione": "login",
  "username": "demo",
  "password": "secret"
}
```

## Formato response JSON

Successo:

```json
{
  "status": "ok",
  "message": "Operazione completata",
  "data": {}
}
```

Errore:

```json
{
  "status": "ko",
  "error": "Descrizione errore"
}
```

Errore validazione:

```json
{
  "status": "ko",
  "error": "Dati non validi",
  "validation_errors": {
    "campo": [
      "messaggio errore"
    ]
  }
}
```

## Componenti principali

### `Config\Env`

- carica `.env` tramite `vlucas/phpdotenv`
- getter tipizzati: `getString`, `getInt`, `getBool`

### `Config\Database`

- legge connessione DB da env (`DB_*`)
- valida configurazione minima
- espone helper query:
  - `selectQuery`
  - `insertQuery`
  - `updateQuery`
  - `deleteQuery`
- logging applicativo:
  - `writeLog`
  - `writeLogNonBlocking`
  - `getLastLogError`

### `Assets\Validator`

Regole disponibili:

- `required`
- `email`
- `min`
- `max`
- `numeric`
- `in`
- `regex`

### `Assets\Response`

- helper payload (`okPayload`, `errorPayload`, `validationPayload`)
- invio JSON (`ok`, `error`, `validation`, `json`)

### `Assets\Session`

- wrapper sessione PHP (`set`, `get`, `delete`, `destroy`)
- `isLoggedIn` su `$_SESSION['utente_id']`
- `getUtente` con query sulla tabella `utenti`

### `Assets\Utils`

- sanitizzazione ricorsiva array misti
- URL base da ambiente:
  - `APP_URL`
  - `BACKEND_URL`

### `Assets\SendEmail`

- invio mail via PHPMailer
- config SMTP letta da tabella `smtp_config` (`is_active = 1`)
- supporto CC/BCC separati da `;`
- supporto allegati da chiave `allegati`

## Modulo autenticazione

File: `Autenticazione/Servizi.php`

Azioni:

- `login`
- `logout`

`login`:

1. valida `username` e `password`
2. legge utente da `utenti`
3. verifica hash con `password_verify`
4. salva `utente_id` in sessione

`logout`:

1. distrugge sessione
2. ritorna risposta `ok`

## Tabelle SQL

### `tables/logs.sql`

Tabella log applicativi con:

- metadati richiesta (`posto`, `operazione`, `operatore`, `file`)
- payload JSON (`dati_mandati`)
- timestamp e indici utili

### `tables/smtp_config.sql`

Config SMTP con campi:

- `host`, `port`, `secure`
- `username`, `password`
- `from_email`, `from_name`
- `is_active`

## Esempio chiamate API

### Login

```bash
curl -X POST http://localhost/router.php \
  -H "Content-Type: application/json" \
  -d "{\"area\":\"servizi\",\"modulo\":\"Autenticazione\",\"azione\":\"login\",\"username\":\"demo\",\"password\":\"secret\"}"
```

### Logout

```bash
curl -X POST http://localhost/router.php \
  -H "Content-Type: application/json" \
  -d "{\"area\":\"servizi\",\"modulo\":\"Autenticazione\",\"azione\":\"logout\"}"
```

## Come creare un nuovo modulo

1. Crea cartella modulo, esempio `Pazienti/`.
2. Crea file area, esempio `Pazienti/Servizi.php`.
3. Namespace coerente: `namespace Pazienti;`
4. Classe area: `class Servizi`.
5. Costruttore con firma:

```php
public function __construct(array $data, $db, $session, $utils)
```

6. Implementa `execute()` e gestisci `azione`.

Template base:

```php
<?php

declare(strict_types=1);

namespace Pazienti;

use Assets\Response;

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

## Note operative

- `router.php` accetta al momento solo body JSON.
- Il modulo `Autenticazione` è pubblico (senza sessione), gli altri richiedono login.
- Su filesystem case-sensitive (Linux) nomi cartelle/classi devono combaciare esattamente.
- Il `vendor/` non è versionato: dopo clone va eseguito `composer install`.

## Documentazione extra

- Miglioramenti architetturali proposti: `docs/guida-miglioramenti-framework.md`

