# Progetto Finale Cyber Security

## Cyber Blog: analisi e mitigazione di vulnerabilità web


## Introduzione

Ho realizzato questo progetto come lavoro finale del corso di Cyber Security. Il punto di partenza era un'applicazione volutamente vulnerabile: per ogni challenge ho cercato prima di capire dove fosse il problema, poi l'ho riprodotto in locale e infine ho corretto il codice e rifatto le verifiche.

Il progetto principale è **Cyber Blog**, un blog sviluppato con Laravel. Permette di registrarsi, pubblicare articoli, revisionarli e gestire ruoli differenti. Accanto al blog è presente una piccola **Financial App** che simula un servizio interno contenente dati sensibili. È scritta in PHP nativo e usa un file JSON come sorgente dati.

Per me è stato soprattutto un modo per passare dalla teoria alla pratica e vedere cosa cambia davvero prima e dopo una correzione. Tutte le prove sono state svolte su servizi locali, in un ambiente controllato e autorizzato.

## Architettura

L'ambiente simula una separazione tra area pubblica, zona amministrativa e rete interna:

```text
Utente
  |
  +-- http://cyber.blog:8000
  |     Area pubblica del blog, autenticazione e dashboard dei ruoli
  |
  +-- http://internal.admin:8000
  |     Area amministrativa protetta
  |
  +-- http://internal.finance:8001
        Financial App raggiunta dal backend tramite un flusso dedicato
```

L'applicazione Laravel pubblica e l'area amministrativa sono servite dalla stessa applicazione sulla porta `8000`, ma il middleware `OnlyLocalAdmin` consente le rotte amministrative soltanto quando l'host è `internal.admin:8000`.

La Financial App viene eseguita separatamente sulla porta `8001`. Il servizio Laravel usa l'endpoint interno fisso `http://internal.finance:8001/user-data.php` e lo richiama solo dal flusso amministrativo autorizzato.

Nell'ambiente del progetto tutto gira sulla stessa macchina. La separazione tramite domini e porte serve a simulare componenti che, in un'infrastruttura reale, sarebbero collocati nella parte pubblica, nella DMZ e nella rete interna.

## Ruoli

I ruoli sono salvati nel modello `User` attraverso tre colonne booleane:

- `is_writer`
- `is_revisor`
- `is_admin`

Lo stesso utente può avere più ruoli contemporaneamente. 




### User

È il ruolo base di un account registrato. Può accedere al proprio profilo e inviare una candidatura per un ruolo aggiuntivo.

### Writer

Può accedere alla dashboard writer, creare articoli, modificarli, eliminarli e controllarne lo stato: in revisione, approvato oppure respinto. Durante la scrittura può usare il componente Livewire `LatestNews` per ricevere suggerimenti da NewsAPI.

### Revisor

Può accedere alla coda di revisione e approvare, rifiutare oppure riportare un articolo allo stato da revisionare.

### Admin

Può accedere all'area amministrativa tramite `internal.admin:8000`, gestire categorie e tag, assegnare i ruoli admin, revisor e writer e consultare il flusso autorizzato della Financial App.

### Super Admin

Nel progetto non esiste una quarta colonna o un middleware separato per il super admin. Il seeder crea un utente che possiede contemporaneamente i tre ruoli disponibili, quindi il comportamento da super admin deriva dalla loro combinazione.




## Metodologia

Per ogni challenge ho seguito lo stesso approccio:

1. analisi della vulnerabilità e dei suoi effetti;
2. identificazione di rotte, controller, middleware, viste e servizi coinvolti;
3. riproduzione del comportamento vulnerabile esclusivamente in locale;
4. implementazione della mitigazione lato server;
5. ripetizione della prova dopo la correzione;
6. aggiunta di test automatici per i casi principali;
7. esecuzione della suite completa per controllare eventuali regressioni.


## Challenge 1 — Rate limiter mancante

### Problema iniziale

La ricerca pubblica degli articoli era accessibile senza un limite dedicato. La rotta reale è:

```text
GET /articles/search
```

Richieste ripetute potevano continuare a eseguire ricerche sul server e aumentare il consumo di risorse. Il rischio analizzato era un abuso della funzionalità e una simulazione controllata di DoS.

### Attacco simulato

Nel repository è presente lo script:

```text
XXX-AttackTools/dos/search.sh
```

Lo script usa `curl`, misura codice HTTP e tempo totale, quindi invia un burst controllato. Può essere eseguito solo contro l'istanza locale autorizzata:

```bash
bash XXX-AttackTools/dos/search.sh http://cyber.blog:8000/articles/search
```

Il numero di richieste e la concorrenza possono essere configurati tramite le variabili `NUM_REQUESTS` e `PARALLEL_REQUESTS` definite dallo script.

### Mitigazione

Ho creato il middleware `app/Http/Middleware/BlockSuspiciousIPs.php`, registrato con alias `block.suspicious` in `bootstrap/app.php` e applicato alla rotta `articles.search`.

La configurazione reale è:

```php
protected int $maxAttempts = 5;
protected int $decayMinutes = 1;
protected int $blockMinutes = 1;
```

Il middleware:

- recupera il client con `$request->ip()`;
- genera una chiave Cache con `sha1($ip)`, senza lasciare l'IP in chiaro nella chiave;
- conserva il numero dei tentativi per un minuto;
- crea una chiave `blocked` al superamento del quinto tentativo;
- blocca temporaneamente l'IP per un minuto;
- restituisce una risposta JSON con stato HTTP `429 Too Many Requests`;
- registra il blocco con `Log::warning()`.

Il rate limiter già fornito da Fortify per il login è rimasto attivo e separato. Usa una soglia di cinque tentativi al minuto per combinazione di email e IP.

### Verifica finale

`tests/Feature/BlockSuspiciousIPsTest.php` verifica che:

- le prime cinque richieste dello stesso IP siano consentite;
- la sesta restituisca 429;
- un secondo IP non venga bloccato;
- il primo IP possa riprovare dopo la scadenza;
- il middleware sia associato alla rotta reale;
- il blocco venga registrato come warning anche nei test di logging.

### Risultato

Con questa modifica ho lasciato pubblica la ricerca, ma ho impedito che un singolo IP possa richiamarla senza limite. Il limiter di Fortify per il login è rimasto separato e non è stato sostituito.

## Challenge 2 — Operazioni critiche in GET

### Problema iniziale

Le operazioni che assegnavano ruoli modificavano lo stato dell'applicazione tramite richieste GET. Questo permetteva di preparare un collegamento che, aperto da un amministratore autenticato, poteva provocare una CSRF e un'assegnazione di privilegi non voluta.

La pagina dimostrativa usata per il comportamento iniziale si trova in:

```text
XXX-AttackTools/csrf/index.html
```

### Attacco simulato

La pagina contiene un vecchio collegamento verso un endpoint amministrativo GET e prova ad attivarlo dopo il caricamento. È una dimostrazione locale del fatto che una semplice navigazione non dovrebbe mai effettuare un'operazione critica.

### Mitigazione

Le rotte finali in `routes/web.php` sono:

```text
PATCH /admin/{user}/set-admin
PATCH /admin/{user}/set-revisor
PATCH /admin/{user}/set-writer
```

I relativi nomi sono `admin.setAdmin`, `admin.setRevisor` e `admin.setWriter`.

Le rotte sono dentro un gruppo protetto da:

- `auth`;
- middleware `admin`, collegato a `UserIsAdmin`;
- middleware `admin.local`, collegato a `OnlyLocalAdmin`.

La vista `resources/views/components/requests-table.blade.php` non usa collegamenti GET. Ogni operazione è un form con:

```blade
@csrf
@method('PATCH')
```

`AdminController` riceve il modello `User` tramite route model binding e richiama `ensureCanChangeRole()` prima della modifica. Se l'utente autenticato non è admin, registra un warning e restituisce HTTP 403.

### Verifica finale

`tests/Feature/CriticalRoleOperationsTest.php` verifica:

- che le vecchie richieste GET non modifichino i ruoli;
- che un PATCH senza autenticazione venga rifiutato;
- che un utente non admin venga rifiutato;
- che il middleware web blocchi una richiesta senza CSRF;
- che un admin possa assegnare tutti e tre i ruoli;
- che le modifiche siano salvate nel database;
- che le rotte abbiano metodo e middleware corretti;
- che il componente Blade generi form PATCH con CSRF.

### Risultato

Il controllo finale è abbastanza semplice: aprire il vecchio URL con una GET non cambia più nessun ruolo. Per completare l'operazione servono invece una richiesta PATCH valida, il token CSRF, una sessione autenticata, il ruolo admin e l'host amministrativo previsto.

## Challenge 3 — Log mancanti

### Problema iniziale

Le operazioni importanti non lasciavano abbastanza informazioni per ricostruire cosa fosse successo. Senza log è difficile capire chi ha eseguito un'azione, su quale risorsa e da quale indirizzo. Questo riduce accountability e non-repudiation.

### Mitigazione

Ho usato `Illuminate\Support\Facades\Log` con contesto strutturato e livelli differenti:

- `Log::info()` per operazioni normali riuscite;
- `Log::notice()` per registrazione, cambio ruolo e aggiornamento profilo;
- `Log::warning()` per attività bloccate o sospette;
- `Log::error()` se il recupero dei dati dalla Financial App fallisce.

Per autenticazione e registrazione sono presenti tre listener:

- `app/Listeners/LogSuccessfulLogin.php`
- `app/Listeners/LogSuccessfulLogout.php`
- `app/Listeners/LogUserRegistered.php`

### Eventi registrati

Il codice registra realmente:

| Evento | Livello principale | Punto di registrazione |
|---|---|---|
| Registrazione | notice | `LogUserRegistered` |
| Login | info | `LogSuccessfulLogin` |
| Logout | info | `LogSuccessfulLogout` |
| Creazione articolo | info | `ArticleController::store` |
| Modifica articolo | info | `ArticleController::update` |
| Eliminazione articolo | info | `ArticleController::destroy` |
| Assegnazione ruolo | notice | `AdminController` |
| Tentativo di cambio ruolo non autorizzato | warning | `AdminController` |
| Accesso admin non autorizzato | warning | `UserIsAdmin` e `OnlyLocalAdmin` |
| Blocco temporaneo IP | warning | `BlockSuspiciousIPs` |
| Tentativo SSRF | warning | `LatestNews` e `HttpService` |
| Contenuto XSS sanificato | warning | `ArticleController` |
| Tentativo di Mass Assignment | warning | `UpdateProfileRequest` |
| Aggiornamento profilo | notice | `ProfileController` |

Un esempio ridotto del contesto usato per la creazione di un articolo è:

```php
[
    'event' => 'article_created',
    'actor_user_id' => $request->user()->id,
    'article_id' => $article->id,
    'ip_address' => $request->ip(),
    'result' => 'success',
]
```

### Dati esclusi dai log

I log non includono password, hash delle password, token CSRF, cookie, session ID, API key, header di autorizzazione, contenuto completo degli articoli o dati della Financial App. Per XSS e Mass Assignment vengono registrati metadati e nomi dei campi, non il payload completo o i valori sensibili.

Con il canale standard `single`, configurato in `config/logging.php`, il percorso è:

```text
storage/logs/laravel.log
```

### Verifica finale

`tests/Feature/CriticalOperationsLoggingTest.php` verifica registrazione, login, logout, CRUD degli articoli, cambio ruolo, tentativo non autorizzato e blocco del rate limiter. Altri test controllano i warning relativi a SSRF, XSS e Mass Assignment e verificano che i dati sensibili non siano presenti nel contesto.

### Risultato

Alla fine ho preferito log brevi ma utili, con gli identificativi e il contesto necessario a ricostruire un'azione. Password, token e contenuti completi restano fuori: registrare più dati non significa automaticamente avere log migliori.

## Challenge 4 — Manomissione input e SSRF

### Problema iniziale

Il componente delle ultime notizie effettuava una richiesta HTTP lato server. Se il valore inviato dalla select fosse stato interpretato come URL, un writer avrebbe potuto modificarlo dal browser e tentare di raggiungere un servizio interno, ad esempio la Financial App.

Questa vulnerabilità è una Server-Side Request Forgery: il client non contatta direttamente il servizio interno, ma prova a convincere il server a farlo per suo conto.

### Attacco simulato

La prova prevista consiste nel modificare il valore della select del componente Livewire con un URL interno o locale. I test usano valori come l'endpoint della Financial App, localhost, loopback, schemi non consentiti, domini esterni e porte arbitrarie. Durante i test le richieste HTTP sono simulate e non viene contattato alcun servizio reale.

### Mitigazione

I file principali sono:

- `app/Livewire/LatestNews.php`
- `app/Services/HttpService.php`
- `resources/views/livewire/latest-news.blade.php`
- `config/services.php`

La select invia soltanto chiavi logiche:

```text
it
gb
us
```

`LatestNews` valida `selectedSource` con `Rule::in(HttpService::newsSourceKeys())`, richiede un writer autenticato e non conserva dati finanziari nel proprio stato. Se riceve un valore manipolato, registra l'evento `ssrf_attempt_blocked`, mostra un errore di validazione e non effettua richieste HTTP.

`HttpService` espone due metodi specifici:

- `fetchLatestNews(string $sourceKey)`
- `fetchFinancialDataForAdmin(User $user)`

Per NewsAPI il servizio:

- usa un endpoint HTTPS fisso;
- controlla schema, host, porta e path;
- rifiuta credenziali o frammenti nell'URL;
- risolve i record DNS A e AAAA;
- rifiuta indirizzi privati e riservati;
- associa la connessione all'indirizzo già controllato tramite `CURLOPT_RESOLVE`;
- disabilita i redirect automatici;
- applica timeout di connessione e risposta;
- richiede JSON;
- limita il corpo a 1.000.000 di byte;
- non restituisce corpi grezzi in caso di errore.

La chiave NewsAPI viene recuperata con `config('services.newsapi.api_key')` e non è costruita dal client.

Per la Financial App esiste un endpoint interno fisso separato. Prima della richiesta, `fetchFinancialDataForAdmin()` verifica che l'utente sia autenticato, coincida con quello passato al metodo e abbia `is_admin = true`. Il componente `LatestNews` non richiama questo metodo.

### Verifica finale

`tests/Feature/SsrfProtectionTest.php` usa `Http::fake()` e i test Livewire per controllare:

- sorgenti valide;
- URL interni, loopback e localhost;
- schemi non HTTP consentiti;
- domini e porte non autorizzati;
- redirect verso servizi interni;
- risoluzione DNS verso IP privati;
- assenza di richieste con input non valido;
- blocco della Financial App per un writer;
- accesso al flusso finanziario per un admin;
- assenza di dati finanziari nello stato di `LatestNews`;
- warning per i tentativi bloccati.

### Risultato

La differenza principale rispetto a prima è che il browser non invia più una destinazione completa. Può scegliere soltanto una delle chiavi previste; URL e parametri vengono costruiti dal server. La Financial App è rimasta fuori da questo flusso ed è accessibile solo dalla funzione amministrativa dedicata.

## Challenge 5 — Stored XSS

### Problema iniziale

Il campo reale del contenuto degli articoli è `body`. Un writer poteva intercettare la richiesta di creazione o modifica e provare a memorizzare HTML attivo nel database. Il rischio era che quel contenuto venisse poi eseguito nel browser di chi apriva l'articolo.

Questo è uno Stored XSS perché il payload non rimane nella singola richiesta: viene salvato e può essere riproposto in visualizzazioni successive.

### Attacco simulato

Le prove automatiche includono payload dimostrativi semplici:

```html
<script>alert('hacked')</script>
<img src="x" onerror="alert('hacked')">
```

Sono verificati anche `onclick`, URL `javascript:`, iframe, object, embed, form, SVG e HTML malformato. Le prove non includono furto di cookie o esfiltrazione di dati.

### Mitigazione

Ho aggiunto `mews/purifier`, basato su HTMLPurifier, e centralizzato il filtro in:

```text
app/Services/ArticleContentSanitizer.php
```

L'allowlist mantiene la formattazione necessaria per gli articoli:

```text
p, br, strong, b, em, i, u,
h1, h2, h3, h4,
ul, ol, li, blockquote,
a[href|title], img[src|alt|title|width|height],
code, pre, hr, table, thead, tbody, tr, th, td
```

Sono consentiti solo gli schemi `http`, `https` e `mailto`. Gli stili CSS inline e gli ID non sono ammessi. Script, iframe, object, embed, applet, form, input, button, meta, base, style, SVG e MathML non fanno parte dell'allowlist. Anche gli attributi evento e gli schemi URI non consentiti vengono rilevati.

`ArticleController::store()` e `ArticleController::update()`:

1. validano `body` come stringa obbligatoria, con lunghezza massima di 50.000 caratteri;
2. controllano la presenza di elementi chiaramente pericolosi;
3. sanificano l'HTML;
4. rifiutano un risultato senza testo significativo;
5. salvano esclusivamente il contenuto filtrato;
6. registrano un warning senza includere il payload completo.

In lettura, `ArticleController::show()` sanifica nuovamente il contenuto storico e passa alla vista `$safeBody`. Solo questo valore viene renderizzato come HTML:

```blade
{!! $safeBody !!}
```

La configurazione TinyMCE nel layout è allineata alla allowlist del server, ma rimane una difesa aggiuntiva: il controllo principale avviene sempre in PHP.

### Verifica finale

`tests/Feature/StoredXssProtectionTest.php` controlla:

- mantenimento del rich text consentito;
- rimozione di tag, attributi e protocolli pericolosi;
- sanitizzazione durante creazione e aggiornamento;
- protezione in lettura per contenuti storici;
- sicurezza della visualizzazione pubblica e del revisore;
- rifiuto di contenuto senza testo valido;
- coerenza tra editor e allowlist;
- warning senza payload completo;
- assenza di richieste HTTP esterne nei test.

### Risultato

In questo modo non devo fidarmi di TinyMCE o dei controlli JavaScript. Il contenuto viene ripulito prima del salvataggio e controllato di nuovo quando viene mostrato, senza perdere la normale formattazione degli articoli.

## Challenge 6 — Mass Assignment

### Problema iniziale

Una pagina profilo deve permettere a un utente di cambiare nome, email e password, ma non i propri privilegi. Usare `$request->all()`, `fill()` con input non filtrato oppure `protected $guarded = []` potrebbe permettere di aggiungere manualmente campi relativi ai ruoli e ottenere una privilege escalation.

I campi sensibili realmente presenti nel modello sono `is_admin`, `is_writer` e `is_revisor`. Il sistema considera sospetti anche nomi alternativi come `role`, `roles` e `permissions`.

### Attacco simulato

La prova modifica la richiesta PATCH del profilo aggiungendo campi che non compaiono nel form, ad esempio:

```text
is_admin=1
role=admin
```

Il test controlla che la risposta sia `422 Unprocessable Entity`, che i privilegi restino invariati e che venga prodotto un warning senza registrare la password.

### Mitigazione

La pagina profilo usa queste rotte autenticate:

```text
GET   /profile
PATCH /profile
```

I file principali sono:

- `app/Models/User.php`
- `app/Http/Controllers/ProfileController.php`
- `app/Http/Requests/UpdateProfileRequest.php`
- `resources/views/profile/edit.blade.php`

Il `$fillable` finale del modello `User` è:

```php
protected $fillable = [
    'name',
    'email',
    'password',
];
```

I campi dei ruoli non sono mass assignable e il modello non usa `$guarded = []`.

`UpdateProfileRequest`:

- richiede un utente autenticato;
- valida `name` come stringa obbligatoria fino a 255 caratteri;
- valida `email`, applicando l'unicità e ignorando l'utente corrente;
- rende la password opzionale;
- richiede conferma e applica le regole `Password::default()`;
- intercetta i nomi dei campi sensibili;
- aggiunge un errore di validazione e registra `mass_assignment_attempt_blocked`.

`ProfileController` recupera sempre l'utente da `$request->user()`, senza accettare un ID dal browser. Costruisce esplicitamente l'array con `name` ed `email`; aggiunge `password` solo quando presente e la salva con `Hash::make()`.

I ruoli vengono modificati separatamente dai metodi protetti di `AdminController`, con assegnazioni esplicite come `$user->is_admin = true`.

### Verifica finale

`tests/Feature/ProfileMassAssignmentTest.php` verifica:

- accesso al profilo per utente autenticato e blocco dell'ospite;
- modifica di nome ed email;
- unicità dell'email;
- password opzionale, confermata e hashata;
- conservazione della password se il campo è vuoto;
- impossibilità di scegliere un altro profilo tramite ID;
- rifiuto dei campi admin, writer, revisor e role;
- contenuto sicuro di `$fillable`;
- compatibilità con registrazione Fortify e seeder;
- compatibilità con l'assegnazione amministrativa dei ruoli;
- log sicuro dell'aggiornamento e del tentativo bloccato.

### Risultato

Ho tenuto la modifica del profilo separata dalla gestione amministrativa dei ruoli. Anche aggiungendo a mano un campo alla richiesta, vengono usati soltanto nome, email e password e l'utente non può assegnarsi privilegi.

## Riepilogo

| Challenge | Vulnerabilità | Mitigazione principale | Stato |
|---|---|---|---|
| 1 | Assenza di rate limiting sulla ricerca | Middleware per IP, Cache, soglia 5/minuto e blocco temporaneo | Completata |
| 2 | Cambio ruolo tramite GET e CSRF | PATCH, CSRF, autenticazione e autorizzazione admin | Completata |
| 3 | Mancanza di tracciamento delle operazioni critiche | Log strutturati, listener e livelli coerenti | Completata |
| 4 | SSRF tramite URL manipolabile | Chiavi logiche, endpoint fissi, controlli rete e autorizzazione admin | Completata |
| 5 | Stored XSS nel corpo degli articoli | HTMLPurifier in scrittura e lettura con allowlist | Completata |
| 6 | Mass Assignment sul profilo | `$fillable` ristretto, Form Request e whitelist esplicita | Completata |





## Considerazioni finali

La parte più utile del progetto è stata vedere gli stessi problemi prima nel loro comportamento vulnerabile e poi nel codice corretto. Lavorandoci ho capito meglio che validazione, autorizzazione e sanitizzazione non sono la stessa cosa e che spesso servono tutte e tre.

Un errore che tornava in più challenge era fidarsi troppo di quello che si vede nel browser. Un pulsante nascosto, una select con opzioni fisse o l'editor di testo non impediscono di modificare una richiesta: il controllo importante deve stare sul server.

Mi è servito anche lavorare su più livelli. Negli articoli, per esempio, il contenuto è sanificato sia in scrittura sia in lettura. Per la SSRF la whitelist è accompagnata dai controlli su DNS, indirizzi e redirect. Nel profilo, invece, `$fillable` è affiancato dalla validazione e dalla costruzione esplicita dei dati da aggiornare.

I log mi hanno fatto capire un altro aspetto pratico: bisogna poter ricostruire cosa è successo, ma senza salvare password, token o informazioni inutilmente sensibili. Rifare i test dopo ogni modifica è stato altrettanto importante, perché una correzione di sicurezza non dovrebbe rompere ciò che funzionava già.


