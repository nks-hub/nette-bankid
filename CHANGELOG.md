# Changelog

Všechny významné změny v tomto projektu budou dokumentovány v tomto souboru.

Formát vychází z [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
a tento projekt dodržuje [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-01-XX

### Added
- Initial release
- BankID OAuth2/OpenID Connect provider
- Nette DI Extension for easy configuration
- Doctrine entity `BankIdVerification` for storing verification data
- Repository with useful query methods
- Support for Czech BankID (production + sandbox)
- Automatic URL configuration based on sandbox mode
- CSRF protection with state token validation
- Level of Assurance (LOA) support
- Comprehensive README with examples
- PHP 8.1, 8.2, 8.3, 8.4 support

### Security
- State token validation for CSRF protection
- Secure token storage in database
- Support for access token and refresh token

## [Unreleased]

### Planned
- Slovak BankID endpoints support
- Token refresh functionality
- Automatic token cleanup command (GDPR compliance)
- Integration tests
- PHPStan level 8 compliance
- GitHub Actions CI/CD pipeline
