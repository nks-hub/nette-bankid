# Changelog

Všechny významné změny v tomto projektu budou dokumentovány v tomto souboru.

Formát vychází z [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
a tento projekt dodržuje [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-10-18

### Added
- Initial stable release
- BankID OAuth2/OpenID Connect provider implementation
- Nette DI Extension for easy configuration and service registration
- Support for Czech BankID (production + sandbox environments)
- Automatic URL configuration based on sandbox mode and country
- Level of Assurance (LOA) support with default LOA3 (highest security)
- Tracy Debug Bar panel for live authentication monitoring
- Debug logging with Tracy file logger (log/bankid.log)
- User data display in Tracy panel after successful authentication
- Official BankID logo in Tracy bar tab
- Comprehensive README with usage examples and troubleshooting
- PHP 8.1, 8.2, 8.3, 8.4 support

### Features
- `BankIdProvider` - OAuth2/OIDC provider with complete authentication flow
- `BankIdExtension` - Nette DI extension with automatic service registration
- `BankIdPanel` - Tracy debug panel with:
  - Live authentication flow logging with timing (ms)
  - Authenticated user data display (sub, email, name, birthdate, etc.)
  - Mode indicator (Sandbox/Production)
  - Event logging with context data
- Configurable debug mode (`debug: true/false`)
- Automatic endpoint resolution based on sandbox/country settings
- Custom endpoint support for advanced configurations
- Helper methods for user data structuring

### Security
- OAuth2 state parameter support (validation must be done in application)
- HTTPS requirement for production callback URLs
- Secure credential handling via Nette DI container
- No sensitive data logging (tokens are not logged in debug mode)

### Documentation
- Complete README with:
  - Installation guide
  - Configuration examples (sandbox & production)
  - Usage examples with Nette presenters
  - Tracy debug panel documentation
  - Security best practices
  - Troubleshooting section
  - GDPR compliance notes

### Technical
- Built on `league/oauth2-client` foundation
- Nette Framework 3.2+ compatible
- PSR-4 autoloading
- Strict types enforcement
- Clean separation: OAuth2 provider only (no database layer)

## [Unreleased]

### Planned
- Slovak BankID endpoints support (SK URLs)
- Token refresh functionality
- Additional debug panel features (request/response raw data)
- Integration tests with BankID sandbox
- PHPStan level 8 compliance
- GitHub Actions CI/CD pipeline
- Multilingual README (EN/SK)
