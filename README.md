# Nette BankID

[![Latest Stable Version](https://poser.pugx.org/nks-hub/nette-bankid/v)](https://packagist.org/packages/nks-hub/nette-bankid)
[![Total Downloads](https://poser.pugx.org/nks-hub/nette-bankid/downloads)](https://packagist.org/packages/nks-hub/nette-bankid)
[![PHP Version](https://img.shields.io/badge/php-8.1%20--%208.4-8892BF.svg)](https://php.net/)
[![Nette Version](https://img.shields.io/badge/nette-%5E3.0%20%7C%7C%20%5E4.0-blue.svg)](https://nette.org/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

Nette DI extension for Czech BankID sign-in over OAuth2/OpenID Connect: the user confirms their identity in their own internet banking and your application receives verified name, email, birthdate and phone.

Only the Czech BankID is supported. Slovakia has no BankID service; `country: 'sk'` is accepted in the configuration but needs your own endpoints.

## Features

- 🏦 **BankID OAuth2/OIDC** - Authorization URL, code exchange and userinfo call; requests LOA3 and forces a fresh login (`prompt=login`)
- 🛡️ **State, PKCE and nonce** - Generates a state token and nonce, uses PKCE (S256), and compares both values in constant time
- 🧪 **Sandbox and production** - The `sandbox` flag selects the endpoint set
- 👤 **User data** - `sub`, email, names, birthdate, phone, address and level of assurance
- 🐞 **Tracy panel** - Each authentication step with its time in ms, also written to `log/bankid.log`
- 🌍 **Custom endpoints** - Override the authorize, token and userinfo URLs
- 🎯 **Nette integration** - Registers `BankIdProvider` in the DI container; storing users is up to your application

## Requirements

- PHP 8.1 – 8.4
- Nette 3.0+ / 4.0+ (`nette/di`, `nette/security`, `nette/http`, `nette/utils`)
- league/oauth2-client 2.7+
- BankID developer account ([developer.bankid.cz](https://developer.bankid.cz/))

## Installation

```bash
composer require nks-hub/nette-bankid
```

## Configuration

### 1. Register the extension in `config.neon`

```neon
extensions:
    bankid: NksHub\NetteBankId\DI\BankIdExtension
```

### 2. Configure BankID credentials

```neon
bankid:
    clientId: 'your-client-id'
    clientSecret: 'your-client-secret'
    redirectUri: 'https://your-domain.com/bankid/callback'
    sandbox: false  # true for testing, false for production
    country: 'cz'   # 'cz' only (SK has no default endpoints)
    debug: false    # true = Tracy debug panel + logging
```

### 3. Use it in your application

Inject `BankIdProvider` into a presenter, see [Usage](#usage).

### All parameters

```neon
bankid:
    # Required parameters
    clientId: 'your-client-id'
    clientSecret: 'your-client-secret'
    redirectUri: 'https://your-domain.com/bankid/callback'

    # Optional parameters
    sandbox: false              # true = sandbox, false = production
    country: 'cz'              # 'cz' or 'sk'; 'sk' requires the custom endpoints below
    debug: false               # true = Tracy panel + file logging

    # Custom endpoints instead of the default CZ ones
    authorizeUrl: null         # null = CZ default according to sandbox mode
    tokenUrl: null
    userinfoUrl: null
```

### Getting BankID credentials

#### Sandbox (testing)

1. Visit the [BankID Developer Portal](https://developer.bankid.cz/)
2. Register as a developer
3. Create a new application in the Sandbox
4. Obtain `client_id` and `client_secret`
5. Set `redirect_uri` (your application's callback URL)

#### Production

1. Go through the certification process on the BankID portal
2. Sign a contract with the BankID provider
3. Obtain production credentials
4. Switch `sandbox: false` in the configuration

### Sandbox vs production mode

#### Sandbox (testing)

```neon
bankid:
    sandbox: true
    clientId: 'sandbox-client-id'
    clientSecret: 'sandbox-secret'
    redirectUri: 'http://localhost:8080/bankid/callback'
```

**Test users:** BankID provides test accounts in sandbox mode, see [developer.bankid.cz](https://developer.bankid.cz/docs).

#### Production

```neon
bankid:
    sandbox: false
    clientId: 'production-client-id'
    clientSecret: 'production-secret'
    redirectUri: 'https://your-domain.com/bankid/callback'
```

Production credentials are issued only after BankID certification.

## Usage

### Basic OAuth2 flow

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
     * Step 1: Redirect to BankID login
     */
    public function actionLogin(): void
    {
        $authUrl = $this->bankIdProvider->getAuthorizationUrl();

        // authenticate() compares these with the values returned by BankID
        $session = $this->getSession('bankid');
        $session->state = $this->bankIdProvider->getState();
        $session->nonce = $this->bankIdProvider->getNonce();

        // Redirect to BankID
        $this->redirectUrl($authUrl);
    }

    /**
     * Step 2: Callback from BankID
     */
    public function actionCallback(?string $code = null, ?string $state = null): void
    {
        if (!$code || !$state) {
            $this->flashMessage('Authentication failed - missing parameters', 'error');
            $this->redirect('Homepage:');
        }

        try {
            $session = $this->getSession('bankid');
            $result = $this->bankIdProvider->authenticate($code, $state, $session->state, $session->nonce);

            $userData = $result['user'];
            $accessToken = $result['token'];

            // Store user data in your database or session here
            $this->getSession('user')->userData = $userData;

            $this->flashMessage('Successfully signed in via BankID', 'success');
            $this->redirect('Homepage:');

        } catch (VerificationFailedException $e) {
            $this->flashMessage('Authentication failed: ' . $e->getMessage(), 'error');
            $this->redirect('Homepage:');
        }
    }
}
```

### User data

```php
$result = $this->bankIdProvider->authenticate($code, $state, $expectedState, $expectedNonce);
$userData = $result['user'];
$accessToken = $result['token'];

// Available user data:
$userData['sub'];           // BankID user ID (unique identifier)
$userData['email'];         // Email
$userData['given_name'];    // First name
$userData['family_name'];   // Last name
$userData['name'];          // Full name
$userData['birthdate'];     // Date of birth
$userData['phone_number'];  // Phone
$userData['acr'];           // Level of Assurance (loa1, loa2, loa3)
$userData['address'];       // Address (object)

// Access token info:
$accessToken->getToken();         // Access token string
$accessToken->getExpires();       // Expiration timestamp
$accessToken->getRefreshToken();  // Refresh token (if available)
```

### Example: storing users with Doctrine

The extension has no storage of its own. One way to keep verified users:

**Entity:**

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

    // ... more properties as needed
}
```

**Service:**

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

## Debugging and Tracy panel

### Enabling debug mode

```neon
bankid:
    debug: true  # Tracy panel and logging
```

### Tracy debug panel

With `debug: true` the Tracy bar gets a **BankID** panel with:

1. **Basic info:**
   - Mode: Sandbox / Production
   - Redirect URI
   - Number of events

2. **Authenticated user data** (after a successful authentication):
   - sub (BankID user ID)
   - email
   - name, given_name, family_name
   - birthdate
   - phone_number
   - acr (Level of Assurance)
   - other available fields

3. **Authentication flow**:
   - Timing of each step (ms)
   - Event description (authenticate started, token retrieved, user info retrieved)
   - Context data for each event

### File logging

The same events are written to:

```
log/bankid.log
```

Log format:
```
[timestamp] Message
{
    "context": {...}
}
```

### Example Tracy output

```
BankID (3)
├─ Mode: Sandbox
├─ Redirect URI: https://your-domain.com/bankid/callback
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

### Disabling debug mode in production

The panel and the log contain personal data from BankID, so keep `debug` off in production:

```neon
# Production config
bankid:
    debug: false  # ← Disabled for production
    sandbox: false
```

## Security

### 1. State and nonce

The extension generates the state token and nonce, but your application keeps them in the session between the redirect and the callback. `authenticate()` throws `VerificationFailedException` when either does not match:

```php
// $expectedState and $expectedNonce come from the session saved in actionLogin()
$result = $this->bankIdProvider->authenticate($code, $state, $expectedState, $expectedNonce);
```

Passing `null` as the nonce skips the nonce check.

### 2. Encrypting access tokens in the database

If you store `access_token` and `refresh_token`, encrypt them:

```php
// Use a Doctrine encrypted column type or your own listener
use Doctrine\ORM\Mapping as ORM;

#[ORM\Column(type: 'encrypted_text')]
private string $accessToken;
```

### 3. HTTPS only

BankID requires HTTPS for the callback URL in production.

```neon
bankid:
    redirectUri: 'https://your-domain.com/bankid/callback'  # HTTPS!
```

### 4. Token expiration

Access tokens expire. Check the expiry on your own entity (`isTokenValid()` is your method, not part of the extension):

```php
if (!$verification->isTokenValid()) {
    // Token expired, request a new authentication
}
```

### 5. Data retention

Delete stored verifications you no longer need (again your own repository method):

```php
// For example in a cron job
$repository->deleteExpired(new \DateTimeImmutable('-90 days'));
```

## Level of Assurance (LOA)

BankID has three levels of verification:

- **LOA1** - Low (basic identification)
- **LOA2** - Medium (verification by document)
- **LOA3** - High (banking identity), requested by the extension

```php
$loa = $this->bankIdProvider->getAssuranceLevel($userData); // 'loa3'
```

## Troubleshooting

### Error: "Invalid redirect_uri"

**Cause:** The redirect URI in the configuration does not match the URI registered in the BankID portal.

**Solution:** Check that `redirectUri` in `config.neon` exactly matches the URI in the BankID developer portal (including http/https, port, path).

### Error: "State token mismatch"

**Cause:** The state returned by BankID differs from the one in the session, either a forged request or a lost session.

**Solution:**
- Check that sessions are configured correctly in Nette
- Make sure cookies work (the session ID travels in a cookie)
- Check that the user was not redirected through a different proxy/load balancer

### Sandbox mode does not work

**Cause:** The sandbox has different endpoints than production.

**Solution:** Make sure you have `sandbox: true` in the configuration:

```neon
bankid:
    sandbox: true
```

### Access token expired

**Cause:** Access tokens have limited validity (typically 1 hour).

**Solution:** Use the refresh token or request a new authentication:

```php
if (!$verification->isTokenValid()) {
    // Request a new sign-in
    $this->redirect('Auth:login');
}
```

## Development

```bash
# Install dependencies
composer install

# Run static analysis
vendor/bin/phpstan analyse src
```

## Contributing

Contributions are welcome! For major changes, please open an issue first.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'feat: description'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## Support

- 📧 **Email:** dev@nks-hub.cz
- 🐛 **Bug reports:** [GitHub Issues](https://github.com/nks-hub/nette-bankid/issues)
- 📖 **BankID docs:** [developer.bankid.cz](https://developer.bankid.cz/docs)

## License

MIT License — see [LICENSE](LICENSE) for details.

---

<p align="center">
  Made with ❤️ by <a href="https://github.com/nks-hub">NKS Hub</a>
</p>
