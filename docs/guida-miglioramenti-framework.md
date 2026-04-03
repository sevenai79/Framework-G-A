# Guida Miglioramenti Architetturali del Mini Framework

Questa guida spiega in dettaglio gli 8 punti che hai evidenziato, con focus pratico su:

- problema attuale
- obiettivo tecnico
- strategia di implementazione
- benefici
- rischi e attenzioni

I riferimenti sono all'attuale struttura del progetto (`router.php`, `assets/`, `config/`, `Autenticazione/`).

---

## 1) Allineare autoload e struttura file (`Config\\` vs `assets/`)

### Problema attuale
In `composer.json`, il namespace `Config\\` punta a `config/`, ma varie classi con namespace `Config` sono dentro `assets/`:

- `assets/Response.php`
- `assets/Session.php`
- `assets/Validator.php`
- `assets/Utils.php`
- `assets/SendEmail.php`

Questo puo causare errori di autoload (`Class "Config\\..." not found`) in ambienti dove non ci sono include manuali.

### Obiettivo
Rendere la risoluzione classi coerente con PSR-4 e prevedibile in ogni ambiente.

### Strategia consigliata
Scegli una sola convenzione e applicala ovunque:

1. Opzione consigliata: spostare le classi `Config` dentro `config/`
2. Opzione alternativa: cambiare il mapping `Config\\` verso `assets/`

La prima opzione e piu chiara: `namespace Config;` => cartella `config/`.

### Passi operativi
1. Sposta i file da `assets/` a `config/`.
2. Verifica che namespace e nomi classe siano invariati.
3. Esegui `composer dump-autoload -o`.
4. Fai un test smoke del router (es. login e una rotta autenticata).
5. Aggiorna README con la nuova struttura.

### Benefici
- Meno errori runtime
- Struttura piu leggibile
- Onboarding piu semplice

### Attenzioni
- Se ci sono `require_once` hardcoded su `assets/`, vanno aggiornati.
- Evita di mantenere doppioni (stesso namespace in due cartelle).

---

## 2) Spostare configurazioni sensibili su `.env`

### Problema attuale
In `config/Database.php` host, db, utente e password sono hardcoded:

- host
- db_name
- username
- password

Questo e rischioso per sicurezza e deploy multi-ambiente.

### Obiettivo
Separare codice e configurazione (12-factor style), usando variabili ambiente.

### Strategia consigliata
1. Aggiungi `vlucas/phpdotenv` (o equivalente).
2. Crea `.env` (locale) e `.env.example` (template committato).
3. Carica `.env` all'avvio (es. in `router.php` prima di istanziare `Database`).
4. Leggi i parametri da `$_ENV` con fallback sicuri.

### Variabili suggerite
- `APP_ENV=local|staging|production`
- `APP_DEBUG=true|false`
- `DB_HOST=localhost`
- `DB_PORT=3306`
- `DB_NAME=my_database`
- `DB_USER=root`
- `DB_PASS=...`
- `APP_URL=...`
- `BACKEND_URL=...`

### Benefici
- Deploy piu semplici
- Sicurezza migliore (niente secret nel codice)
- Config per ambiente senza modifiche al sorgente

### Attenzioni
- `.env` non va mai committato.
- Gestisci fallback espliciti e validazione minima dei valori obbligatori.
- In produzione preferisci env di sistema, non file locale.

---

## 3) Gestore errori centralizzato

### Problema attuale
`router.php` restituisce al client il messaggio completo eccezione:

- `Response::error('Errore interno del server: ' . $e->getMessage(), 500);`

Questo puo esporre dettagli interni (query SQL, path, stack hints).

### Obiettivo
Restituire errori sicuri e consistenti al client, loggando i dettagli solo lato server.

### Strategia consigliata
Crea un `ErrorHandler` centrale che:

1. Intercetta eccezioni (`set_exception_handler`)
2. Intercetta errori PHP (`set_error_handler`)
3. Intercetta fatal/shutdown (`register_shutdown_function`)
4. Genera sempre una risposta JSON standard
5. Scrive log tecnico con request-id e contesto

### Comportamento suggerito
- `APP_DEBUG=false`: messaggio generico ("Errore interno del server")
- `APP_DEBUG=true`: include dettaglio eccezione per debugging locale

### Benefici
- Sicurezza migliore
- Formato errore uniforme
- Diagnostica migliore con log strutturato

### Attenzioni
- L'handler non deve lanciare altre eccezioni non gestite.
- Evita loop se fallisce il logger (usa fallback a `error_log`).

---

## 4) Hardening sessione (cookie flags + rigenerazione ID)

### Problema attuale
La sessione viene avviata ma senza hardening completo:

- manca configurazione cookie sicuri
- manca `session_regenerate_id(true)` al login

### Obiettivo
Ridurre session fixation e session hijacking.

### Strategia consigliata
In `Session::__construct()` prima di `session_start()`:

1. `session.use_strict_mode = 1`
2. `session.cookie_httponly = 1`
3. `session.cookie_secure = 1` in HTTPS
4. `session.cookie_samesite = Lax` (o `Strict` se possibile)
5. `session.use_only_cookies = 1`

Nel login:

1. dopo credenziali valide, chiama `session_regenerate_id(true)`
2. poi salva `utente_id`

Nel logout:

1. svuota `$_SESSION`
2. invalida cookie sessione
3. `session_destroy()`

### Benefici
- Token sessione meno riutilizzabile da attaccanti
- Migliore protezione lato browser

### Attenzioni
- `secure=true` richiede HTTPS reale.
- `SameSite=Strict` puo impattare alcuni flussi cross-site.

---

## 5) Protezione login (rate limit + lock temporaneo)

### Problema attuale
Login senza controllo tentativi => rischio brute-force.

### Obiettivo
Limitare tentativi per IP e/o username e introdurre lock temporaneo.

### Strategia consigliata
Aggiungi un componente `LoginThrottle` con policy semplice:

- max 5 tentativi falliti in 10 minuti
- lock 15 minuti al superamento soglia

Possibili storage:

1. Tabella DB (`login_attempts`)
2. Redis (piu performante)

### Flusso consigliato
1. Prima di verificare password: controlla se utente/IP e bloccato.
2. Se bloccato: `429` o `401` con messaggio neutro.
3. Se password errata: registra fallimento.
4. Se login ok: resetta contatore tentativi.

### Benefici
- Forte riduzione brute-force
- Miglior controllo abuso endpoint auth

### Attenzioni
- Non rivelare se username esiste.
- Usa stesso messaggio per user inesistente/password errata.
- Logga lockout per audit sicurezza.

---

## 6) Middleware pipeline riusabile

### Problema attuale
Molta logica e dentro `router.php` in modo monolitico (auth/check/input).

### Obiettivo
Separare responsabilita in middleware componibili.

### Strategia consigliata
Definisci una pipeline stile:

`Request -> M1 -> M2 -> M3 -> Handler finale`

Middleware suggeriti:

1. `RequestIdMiddleware`
2. `CorsMiddleware`
3. `BodyParserMiddleware`
4. `AuthMiddleware`
5. `RateLimitMiddleware`
6. `AuditMiddleware`
7. `ErrorHandlingMiddleware` (o handler globale)

### Ordine importante
Ordine tipico:

1. request-id
2. parser request
3. cors
4. rate-limit
5. auth
6. audit
7. dispatch route/modulo

### Benefici
- Codice testabile e manutenibile
- Riuso su tutte le rotte
- Minor duplicazione

### Attenzioni
- Definisci bene chi termina la risposta e dove.
- Evita side-effect nascosti in middleware generici.

---

## 7) Request object unico

### Problema attuale
I dati arrivano da piu fonti ma il parsing e centralizzato in modo limitato a JSON (`php://input`), con nota aperta per form-data.

### Obiettivo
Offrire un oggetto `Request` unico e coerente a tutto il framework.

### Strategia consigliata
Crea `Config/Request.php` con:

- method
- headers
- query params (`$_GET`)
- body params (json o form-url-encoded)
- files (`$_FILES`)
- ip client
- user-agent
- path

Metodi utili:

- `input($key, $default = null)`
- `query($key, $default = null)`
- `header($key, $default = null)`
- `file($key)`
- `all()`

### Benefici
- API interna uniforme
- Meno codice ripetuto nei moduli
- Migliore compatibilita con JSON/form-data/query

### Attenzioni
- Definisci precedenza chiara tra query/body.
- Sanitizzazione: meglio contestuale, non globale indiscriminata.

---

## 8) DB transaction helper (begin/commit/rollback + wrapper)

### Problema attuale
`Database` espone query helper ma non una API esplicita per transazioni atomiche.

### Obiettivo
Gestire operazioni multi-query in modalita all-or-nothing.

### Strategia consigliata
Aggiungi in `Database`:

- `beginTransaction(): void`
- `commit(): void`
- `rollBack(): void`
- `transaction(callable $callback)` wrapper

### Pattern consigliato
`transaction()`:

1. apre transazione
2. esegue callback passandole il DB
3. commit se tutto ok
4. rollback automatico su eccezione
5. rilancia eccezione per gestione superiore

### Benefici
- Integrita dati garantita
- Meno bug su flussi complessi (es. ordine + log + email state)

### Attenzioni
- Evita chiamate che fanno `exit` dentro transazione.
- In caso di errori applicativi, preferisci lanciare eccezioni e gestire sopra.

---

## Roadmap consigliata (ordine di implementazione)

Per ridurre rischio regressioni, conviene questa sequenza:

1. Allineamento autoload/struttura file
2. `.env` + bootstrap config
3. Error handler centralizzato
4. Session hardening
5. Request object unico
6. Middleware pipeline base
7. Login throttle
8. Transaction helper

---

## Definizione di completato (DoD) per ogni punto

Per considerare ogni punto davvero completato:

1. test manuale + test automatico minimo
2. log verificato in scenario errore
3. README aggiornato
4. nessun warning PHP in avvio
5. endpoint principali ancora funzionanti

---

## Nota finale

Questi 8 interventi non sono solo "refactor estetico": trasformano il framework da base funzionante a base solida per produzione, con vantaggi chiari su sicurezza, stabilita, manutenibilita e scalabilita.
