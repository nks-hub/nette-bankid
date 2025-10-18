# Nette BankID Extension

Nette DI Extension pro integraci BankID OAuth2/OpenID Connect autentizace.

**Podporované země:** 🇨🇿 Česká republika

**PHP verze:** 8.1, 8.2, 8.3, 8.4

> ℹ️ **Poznámka:** Extension je připraven na budoucí podporu slovenského BankID, pokud bude systém spuštěn. Zatím však Slovensko nemá vlastní BankID implementaci. Kód umožňuje konfiguraci `country: 'sk'` s vlastními endpoints pro případné budoucí použití.

## Co je BankID?

BankID je jednotné přihlašovací řešení poskytované bankami v České republice. Umožňuje uživatelům ověřit svou identitu pomocí internetového bankovnictví s nejvyšší úrovní bezpečnosti (LOA3).

## Instalace

```bash
composer require nks-hub/nette-bankid
```

## Konfigurace

### 1. Registrace extension v `config.neon`

```neon
extensions:
    bankid: NksHub\NetteBankId\DI\BankIdExtension
```

### 2. Konfigurace BankID credentials

```neon
bankid:
    clientId: 'your-client-id'
    clientSecret: 'your-client-secret'
    redirectUri: 'https://your-domain.com/bankid/callback'
    sandbox: false  # true pro testování, false pro production
    country: 'cz'   # pouze 'cz' (SK zatím není implementováno)
    debug: false    # true = Tracy debug panel + logging
```

### 3. Použití v aplikaci

Extension poskytuje OAuth2 provider pro BankID autentizaci. Ukládání dat do databáze je na vaší aplikaci.

## Získání BankID credentials

### Sandbox (testování)

1. Navštivte [BankID Developer Portal](https://www.bankid.cz/vyvojar)
2. Zaregistrujte se jako vývojář
3. Vytvořte novou aplikaci v Sandbox
4. Získejte `client_id` a `client_secret`
5. Nastavte `redirect_uri` (callback URL vaší aplikace)

### Production

1. Projděte certifikačním procesem na BankID portálu
2. Podepište smlouvu s poskytovatelem BankID
3. Získejte produkční credentials
4. Přepněte `sandbox: false` v konfiguraci

## Použití

### Základní OAuth2 flow

```php
<?php

namespace App\Presenters;

use Nette\Application\UI\Presenter;
use NksHub\NetteBankId\OAuth2\BankIdProvider;
use NksHub\NetteBankId\Exceptions\VerificationFailedException;

class AuthPresenter extends Presenter
{
    public function __construct(
        private BankIdProvider $bankIdProvider,
    ) {
        parent::__construct();
    }

    /**
     * Krok 1: Redirect na BankID login
     */
    public function actionLogin(): void
    {
        // Získej authorization URL
        $authUrl = $this->bankIdProvider->getAuthorizationUrl();

        // Ulož state do session pro CSRF ochranu
        $this->getSession('bankid')->state = $this->bankIdProvider->getState();

        // Redirect na BankID
        $this->redirectUrl($authUrl);
    }

    /**
     * Krok 2: Callback z BankID
     */
    public function actionCallback(?string $code = null, ?string $state = null): void
    {
        if (!$code || !$state) {
            $this->flashMessage('Autentizace selhala - chybí parametry', 'error');
            $this->redirect('Homepage:');
        }

        try {
            // Ověř, že se uživatel vrátil z BankID
            $result = $this->bankIdProvider->authenticate($code, $state);

            $userData = $result['user'];
            $accessToken = $result['token'];

            // Zde si můžete uložit user data do databáze nebo session
            $this->getSession('user')->userData = $userData;

            $this->flashMessage('Úspěšně přihlášeno přes BankID', 'success');
            $this->redirect('Homepage:');

        } catch (VerificationFailedException $e) {
            $this->flashMessage('Autentizace selhala: ' . $e->getMessage(), 'error');
            $this->redirect('Homepage:');
        }
    }
}
```

### Získání user dat po autentizaci

Po úspěšné autentizaci získáte user data z BankID:

```php
$result = $this->bankIdProvider->authenticate($code, $state);
$userData = $result['user'];
$accessToken = $result['token'];

// Dostupné user data:
$userData['sub'];           // BankID user ID (unique identifier)
$userData['email'];         // Email
$userData['given_name'];    // Křestní jméno
$userData['family_name'];   // Příjmení
$userData['name'];          // Celé jméno
$userData['birthdate'];     // Datum narození
$userData['phone_number'];  // Telefon
$userData['acr'];           // Level of Assurance (loa1, loa2, loa3)
$userData['address'];       // Adresa (object)

// Access token info:
$accessToken->getToken();         // Access token string
$accessToken->getExpires();       // Expiration timestamp
$accessToken->getRefreshToken();  // Refresh token (if available)
```

### Příklad: Ukládání do vlastní databáze

Extension poskytuje pouze OAuth2 autentizaci. Ukládání dat je na vaší aplikaci.

**Příklad Doctrine entity:**

```php
<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'bankid_verifications')]
class BankIdVerification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', unique: true)]
    private string $userId;  // BankID sub

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $fullName = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $verifiedAt;

    // ... další properties podle potřeby
}
```

**Příklad service pro uložení:**

```php
<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\BankIdVerification;

class BankIdService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function saveVerification(array $userData): BankIdVerification
    {
        $verification = new BankIdVerification();
        $verification->setUserId($userData['sub']);
        $verification->setEmail($userData['email'] ?? null);
        $verification->setFullName($userData['name'] ?? null);
        $verification->setVerifiedAt(new \DateTimeImmutable());

        $this->em->persist($verification);
        $this->em->flush();

        return $verification;
    }
}
```

## Debugging a Tracy Panel

Extension podporuje Tracy debug panel pro živé sledování BankID autentizace.

### Aktivace debug režimu

```neon
bankid:
    debug: true  # Aktivuje Tracy panel a logging
```

### Tracy Debug Panel

Když je `debug: true`, uvidíte v Tracy baru panel **BankID** s následujícími informacemi:

1. **Základní info:**
   - Mode: Sandbox / Production
   - Redirect URI
   - Počet events

2. **Authenticated User Data** (po úspěšné autentizaci):
   - sub (BankID user ID)
   - email
   - name, given_name, family_name
   - birthdate
   - phone_number
   - acr (Level of Assurance)
   - další dostupná pole

3. **Authentication Flow** (živý log):
   - Timing každého kroku (ms)
   - Event popis (authenticate started, token retrieved, user info retrieved)
   - Context data pro každý event

### File Logging

Kromě Tracy panelu se všechny debug info logují do souboru:

```
log/bankid.log
```

Formát logu:
```
[timestamp] Message
{
    "context": {...}
}
```

### Příklad Tracy output

```
BankID (3)
├─ Mode: Sandbox
├─ Redirect URI: https://prekupnici.loc/bankid/callback
└─ Logs: 3 events

Authenticated User Data:
├─ sub: 1234567890
├─ email: user@example.com
├─ name: Jan Novák
├─ given_name: Jan
├─ family_name: Novák
├─ birthdate: 1990-01-01
└─ acr: loa3

Authentication Flow:
├─ +0.00ms   BankID authenticate started
│            code_length: 24, state: abc123...
├─ +125.50ms Access token retrieved
│            expires: 1234567890, has_refresh_token: false
└─ +234.12ms User info retrieved
             sub: 1234567890, email: user@example.com, acr: loa3
```

### Vypnutí debug režimu v production

⚠️ **DŮLEŽITÉ:** Vždy vypněte debug režim v production!

```neon
# Production config
bankid:
    debug: false  # ← Vypnuto pro production
    sandbox: false
```

## Konfigurace - všechny parametry

```neon
bankid:
    # Povinné parametry
    clientId: 'your-client-id'
    clientSecret: 'your-client-secret'
    redirectUri: 'https://your-domain.com/bankid/callback'

    # Volitelné parametry
    sandbox: false              # true = sandbox, false = production
    country: 'cz'              # pouze 'cz' podporováno (SK v budoucnu)
    debug: false               # true = Tracy panel + file logging

    # Custom endpoints (pokud nechcete použít defaultní CZ endpoints)
    # Pro budoucí SK BankID můžete použít custom URLs, až budou dostupné
    authorizeUrl: null         # null = použije se CZ default podle sandbox režimu
    tokenUrl: null
    userinfoUrl: null
```

## Sandbox vs Production režim

### Sandbox (testování)

```neon
bankid:
    sandbox: true
    clientId: 'sandbox-client-id'
    clientSecret: 'sandbox-secret'
    redirectUri: 'http://localhost:8080/bankid/callback'
```

**Testovací uživatelé:** BankID poskytuje testovací účty v sandbox režimu - viz dokumentace na [bankid.cz/vyvojar](https://www.bankid.cz/vyvojar)

### Production

```neon
bankid:
    sandbox: false
    clientId: 'production-client-id'
    clientSecret: 'production-secret'
    redirectUri: 'https://your-domain.com/bankid/callback'
```

⚠️ **DŮLEŽITÉ:** Pro production musíte projít certifikací a získat produkční credentials.

## Security best practices

### 1. CSRF ochrana

Extension automaticky používá state token pro CSRF ochranu:

```php
// Extension generuje a validuje state automaticky
$result = $this->bankIdProvider->authenticate($code, $state);
```

### 2. Šifrování access tokenů v databázi

Pro maximální bezpečnost doporučujeme šifrovat `access_token` a `refresh_token` v databázi:

```php
// Použijte Doctrine encrypted column type nebo vlastní listener
use Doctrine\ORM\Mapping as ORM;

#[ORM\Column(type: 'encrypted_text')]
private string $accessToken;
```

### 3. HTTPS only

BankID **VYŽADUJE** HTTPS pro callback URL v production režimu.

```neon
bankid:
    redirectUri: 'https://your-domain.com/bankid/callback'  # HTTPS!
```

### 4. Token expiration

Access tokeny mají omezenou platnost. Zkontrolujte platnost:

```php
if (!$verification->isTokenValid()) {
    // Token vypršel, vyžádejte novou autentizaci
}
```

### 5. GDPR compliance

Pravidelně odstraňujte staré verifikace:

```php
// Například v cron jobu
$repository->deleteExpired(new \DateTimeImmutable('-90 days'));
```

## Level of Assurance (LOA)

BankID podporuje různé úrovně ověření:

- **LOA1** - Nízká (základní identifikace)
- **LOA2** - Střední (ověření dokumentem)
- **LOA3** - Vysoká (bankovní identita) ← **DEFAULT**

Extension defaultně požaduje LOA3 (nejvyšší úroveň).

```php
// Získej LOA z user dat
$loa = $verification->getAssuranceLevel(); // 'loa3'
```

## Troubleshooting

### Chyba: "Invalid redirect_uri"

**Příčina:** Redirect URI v konfiguraci neodpovídá URI registrované v BankID portálu.

**Řešení:** Zkontrolujte, že `redirectUri` v `config.neon` přesně odpovídá URI v BankID developer portálu (včetně http/https, portu, path).

### Chyba: "State token mismatch"

**Příčina:** CSRF token neodpovídá (možný útok nebo problém se session).

**Řešení:**
- Zkontrolujte, že máte správně nakonfigurované sessions v Nette
- Ujistěte se, že cookies fungují (session se ukládá do cookie)
- Zkontrolujte, že uživatel nebyl přesměrován přes jiný proxy/load balancer

### Sandbox mode nefunguje

**Příčina:** Sandbox má jiné endpoints než production.

**Řešení:** Ujistěte se, že máte `sandbox: true` v konfiguraci:

```neon
bankid:
    sandbox: true  # ← DŮLEŽITÉ
```

### Doctrine entity nejsou namapované

**Příčina:** Extension automaticky registruje mapping pouze pokud je Doctrine ORM dostupná.

**Řešení:** Ujistěte se, že máte nainstalovanou a nakonfigurovanou `nettrine/orm`:

```bash
composer require nettrine/orm
composer require nettrine/dbal
```

### Access token vypršel

**Příčina:** Access tokeny mají omezenou platnost (typicky 1 hodina).

**Řešení:** Použijte refresh token nebo vyžádejte novou autentizaci:

```php
if (!$verification->isTokenValid()) {
    // Vyžádejte nové přihlášení
    $this->redirect('Auth:login');
}
```

## Changelog

Viz [CHANGELOG.md](CHANGELOG.md)

## Licence

MIT License - viz [LICENSE](LICENSE)

## Podpora

- **Issues:** [GitHub Issues](https://github.com/nks-hub/nette-bankid/issues)
- **Dokumentace:** [https://www.bankid.cz/vyvojar](https://www.bankid.cz/vyvojar)
- **Email:** info@nks-hub.com

## Contributing

Pull requesty jsou vítány! Pro větší změny prosím nejprve otevřete issue.

## Autoři

- **NKS Hub** - *Initial work*

## Related Links

- [BankID Developer Portal](https://www.bankid.cz/vyvojar)
- [Nette Framework](https://nette.org)
- [Doctrine ORM](https://www.doctrine-project.org/projects/orm.html)
- [OAuth2 Client Library](https://github.com/thephpleague/oauth2-client)
