# Nette BankID

[![Latest Stable Version](https://poser.pugx.org/nks-hub/nette-bankid/v)](https://packagist.org/packages/nks-hub/nette-bankid)
[![Total Downloads](https://poser.pugx.org/nks-hub/nette-bankid/downloads)](https://packagist.org/packages/nks-hub/nette-bankid)
[![PHP Version](https://img.shields.io/badge/php-8.1%20--%208.4-8892BF.svg)](https://php.net/)
[![Nette Version](https://img.shields.io/badge/nette-%5E3.0%20%7C%7C%20%5E4.0-blue.svg)](https://nette.org/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

Nette DI extension for BankID OAuth2/OpenID Connect authentication.

**Supported countries:** 🇨🇿 Czech Republic

**PHP versions:** 8.1, 8.2, 8.3, 8.4

> ℹ️ **Note:** The extension is prepared for future support of a Slovak BankID, should such a system launch. Slovakia does not have its own BankID implementation yet. The code allows configuring `country: 'sk'` with custom endpoints for possible future use.

**What is BankID?** BankID is a unified sign-in solution provided by banks in the Czech Republic. It lets users verify their identity through their internet banking with the highest level of assurance (LOA3).

## Features

- 🏦 **BankID OAuth2/OIDC** - Identity verification via internet banking, LOA3 requested by default
- 🛡️ **Security built in** - State token (CSRF), PKCE (S256) and nonce for replay protection
- 🧪 **Sandbox + production** - Switch with a single `sandbox` flag
- 👤 **User data** - `sub`, email, names, birthdate, phone, address and level of assurance
- 🐞 **Tracy panel** - Live authentication flow with per-step timing, plus file logging
- 🌍 **Custom endpoints** - Override authorize/token/userinfo URLs (ready for a future SK BankID)
- 🎯 **Nette integration** - DI extension, storage left fully to your application

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
    country: 'cz'   # 'cz' only (SK is not implemented yet)
    debug: false    # true = Tracy debug panel + logging
```

### 3. Use it in your application

The extension provides an OAuth2 provider for BankID authentication. Storing data in a database is up to your application.

### All parameters

```neon
bankid:
    # Required parameters
    clientId: 'your-client-id'
    clientSecret: 'your-client-secret'
    redirectUri: 'https://your-domain.com/bankid/callback'

    # Optional parameters
    sandbox: false              # true = sandbox, false = production
    country: 'cz'              # only 'cz' supported (SK in the future)
    debug: false               # true = Tracy panel + file logging

    # Custom endpoints (if you don't want the default CZ endpoints)
    # For a future SK BankID you can use custom URLs once they are available
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

**Test users:** BankID provides test accounts in sandbox mode — see the documentation at [developer.bankid.cz](https://developer.bankid.cz/docs)

#### Production

```neon
bankid:
    sandbox: false
    clientId: 'production-client-id'
    clientSecret: 'production-secret'
    redirectUri: 'https://your-domain.com/bankid/callback'
```

⚠️ **IMPORTANT:** For production you must pass certification and obtain production credentials.

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
        // Get the authorization URL
        $authUrl = $this->bankIdProvider->getAuthorizationUrl();

        // Store state in the session for CSRF protection
        $this->getSession('bankid')->state = $this->bankIdProvider->getState();

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
            // Verify that the user returned from BankID
            $result = $this->bankIdProvider->authenticate($code, $state);

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

### Getting user data after authentication

After a successful authentication you get user data from BankID:

```php
$result = $this->bankIdProvider->authenticate($code, $state);
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

### Example: storing data in your own database

The extension only provides OAuth2 authentication. Storing data is up to your application.

**Example Doctrine entity:**

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

**Example service for saving:**

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

## Debugging and Tracy Panel

The extension supports a Tracy debug panel for live monitoring of BankID authentication.

### Enabling debug mode

```neon
bankid:
    debug: true  # Enables the Tracy panel and logging
```

### Tracy debug panel

With `debug: true` you will see a **BankID** panel in the Tracy bar with the following information:

1. **Basic info:**
   - Mode: Sandbox / Production
   - Redirect URI
   - Number of events

2. **Authenticated User Data** (after a successful authentication):
   - sub (BankID user ID)
   - email
   - name, given_name, family_name
   - birthdate
   - phone_number
   - acr (Level of Assurance)
   - other available fields

3. **Authentication Flow** (live log):
   - Timing of each step (ms)
   - Event description (authenticate started, token retrieved, user info retrieved)
   - Context data for each event

### File logging

Besides the Tracy panel, all debug info is logged to a file:

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

⚠️ **IMPORTANT:** Always disable debug mode in production!

```neon
# Production config
bankid:
    debug: false  # ← Disabled for production
    sandbox: false
```

## Security Best Practices

### 1. CSRF protection

The extension automatically uses a state token for CSRF protection:

```php
// The extension generates and validates state automatically
$result = $this->bankIdProvider->authenticate($code, $state);
```

### 2. Encrypting access tokens in the database

For maximum security we recommend encrypting `access_token` and `refresh_token` in the database:

```php
// Use a Doctrine encrypted column type or your own listener
use Doctrine\ORM\Mapping as ORM;

#[ORM\Column(type: 'encrypted_text')]
private string $accessToken;
```

### 3. HTTPS only

BankID **REQUIRES** HTTPS for the callback URL in production mode.

```neon
bankid:
    redirectUri: 'https://your-domain.com/bankid/callback'  # HTTPS!
```

### 4. Token expiration

Access tokens have limited validity. Check it:

```php
if (!$verification->isTokenValid()) {
    // Token expired, request a new authentication
}
```

### 5. GDPR compliance

Remove old verifications regularly:

```php
// For example in a cron job
$repository->deleteExpired(new \DateTimeImmutable('-90 days'));
```

## Level of Assurance (LOA)

BankID supports several levels of verification:

- **LOA1** - Low (basic identification)
- **LOA2** - Medium (verification by document)
- **LOA3** - High (banking identity) ← **DEFAULT**

The extension requests LOA3 (the highest level) by default.

```php
// Get LOA from user data
$loa = $verification->getAssuranceLevel(); // 'loa3'
```

## Troubleshooting

### Error: "Invalid redirect_uri"

**Cause:** The redirect URI in the configuration does not match the URI registered in the BankID portal.

**Solution:** Check that `redirectUri` in `config.neon` exactly matches the URI in the BankID developer portal (including http/https, port, path).

### Error: "State token mismatch"

**Cause:** The CSRF token does not match (possible attack or a session problem).

**Solution:**
- Check that sessions are configured correctly in Nette
- Make sure cookies work (the session is stored in a cookie)
- Check that the user was not redirected through a different proxy/load balancer

### Sandbox mode does not work

**Cause:** The sandbox has different endpoints than production.

**Solution:** Make sure you have `sandbox: true` in the configuration:

```neon
bankid:
    sandbox: true  # ← IMPORTANT
```

### Doctrine entities are not mapped

**Cause:** The extension registers the mapping automatically only when Doctrine ORM is available.

**Solution:** Make sure `nettrine/orm` is installed and configured:

```bash
composer require nettrine/orm
composer require nettrine/dbal
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
