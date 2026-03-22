<?php

declare(strict_types=1);

namespace NksHub\NetteBankId\OAuth2;

use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use NksHub\NetteBankId\Exceptions\VerificationFailedException;
use NksHub\NetteBankId\Tracy\BankIdPanel;

/**
 * BankID OAuth2/OpenID Connect Provider
 *
 * Wrapper pro league/oauth2-client s BankID-specific konfigurací
 * Podporuje české i slovenské BankID (rozdílné endpoints)
 */
class BankIdProvider
{
	private GenericProvider $provider;
	private ?BankIdPanel $panel = null;

	/**
	 * Default endpoints pro production BankID (CZ)
	 */
	public const CZ_AUTHORIZE_URL = 'https://oidc.bankid.cz/auth';
	public const CZ_TOKEN_URL = 'https://oidc.bankid.cz/token';
	public const CZ_USERINFO_URL = 'https://oidc.bankid.cz/userinfo';

	/**
	 * Sandbox endpoints pro testování (CZ)
	 */
	public const CZ_SANDBOX_AUTHORIZE_URL = 'https://oidc.sandbox.bankid.cz/auth';
	public const CZ_SANDBOX_TOKEN_URL = 'https://oidc.sandbox.bankid.cz/token';
	public const CZ_SANDBOX_USERINFO_URL = 'https://oidc.sandbox.bankid.cz/userinfo';

	/**
	 * Default scopes pro BankID
	 */
	public const DEFAULT_SCOPES = [
		'openid',
		'profile.name',
		'profile.email',
		'profile.birthdate',
		'profile.phonenumber'
	];

	/** Last generated nonce value, available via getNonce() after calling getAuthorizationUrl() */
	private ?string $lastNonce = null;

	public function __construct(
		private readonly string $clientId,
		#[\SensitiveParameter] private readonly string $clientSecret,
		private readonly string $redirectUri,
		private readonly string $authorizeUrl,
		private readonly string $tokenUrl,
		private readonly string $userinfoUrl,
		private readonly bool $sandbox = false,
		private readonly bool $debug = false,
		?BankIdPanel $panel = null,
	) {
		$this->panel = $panel;
		$this->initializeProvider();
	}

	private function initializeProvider(): void
	{
		$this->provider = new GenericProvider([
			'clientId' => $this->clientId,
			'clientSecret' => $this->clientSecret,
			'redirectUri' => $this->redirectUri,
			'urlAuthorize' => $this->authorizeUrl,
			'urlAccessToken' => $this->tokenUrl,
			'urlResourceOwnerDetails' => $this->userinfoUrl,
			'scopes' => implode(' ', self::DEFAULT_SCOPES),
			'pkceMethod' => 'S256',
		]);

		if ($this->debug) {
			$this->log('BankIdProvider initialized', [
				'sandbox' => $this->sandbox,
				'redirectUri' => $this->redirectUri,
				'authorizeUrl' => $this->authorizeUrl,
			]);
		}
	}

	/**
	 * Debug logging pomocí Tracy (pokud je debug zapnutý)
	 */
	private function log(string $message, array $context = []): void
	{
		if (!$this->debug) {
			return;
		}

		// Log do Tracy panelu (živý debug bar)
		if ($this->panel !== null) {
			$this->panel->log($message, $context);
		}

		// Log do souboru (persistentní log)
		$logMessage = $message;
		if (!empty($context)) {
			$logMessage .= "\n" . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}
		\Tracy\Debugger::log($logMessage, 'bankid');
	}

	/**
	 * Returns the authorization URL for redirecting the user to BankID login.
	 *
	 * The nonce is generated internally and stored. Retrieve it via getNonce()
	 * immediately after this call and persist it in the session for later
	 * validation in authenticate().
	 *
	 * @param array<string, mixed> $options Additional parameters (scope, nonce, etc.)
	 * @return string Authorization URL
	 */
	public function getAuthorizationUrl(array $options = []): string
	{
		// Generate a cryptographically random nonce (OIDC replay-attack prevention).
		// The caller MUST store this value in the session and pass it to authenticate().
		$this->lastNonce = bin2hex(random_bytes(32));

		return $this->provider->getAuthorizationUrl(array_merge([
			'scope' => self::DEFAULT_SCOPES,
			'nonce' => $this->lastNonce,
			'prompt' => 'login',
			'display' => 'page',
			'acr_values' => 'loa3', // Level of Assurance 3 (highest)
		], $options));
	}

	/**
	 * Returns the nonce generated during the last getAuthorizationUrl() call.
	 *
	 * Store this value in the session immediately after calling getAuthorizationUrl()
	 * and pass it to authenticate() as $expectedNonce.
	 *
	 * @return string|null Nonce value, or null if getAuthorizationUrl() has not been called yet
	 */
	public function getNonce(): ?string
	{
		return $this->lastNonce;
	}

	/**
	 * Získá state token pro CSRF protection
	 *
	 * @return string State token
	 */
	public function getState(): string
	{
		return $this->provider->getState();
	}


	/**
	 * Exchanges the authorization code for an access token.
	 *
	 * @param string $code Authorization code from the callback URL
	 * @return AccessToken Access token for fetching user info
	 * @throws VerificationFailedException if the exchange fails
	 */
	public function getAccessToken(#[\SensitiveParameter] string $code): AccessToken
	{
		try {
			return $this->provider->getAccessToken('authorization_code', [
				'code' => $code,
			]);
		} catch (IdentityProviderException $e) {
			throw new VerificationFailedException(
				'Failed to exchange authorization code for access token: ' . $e->getMessage(),
				$e->getCode(),
				$e
			);
		}
	}

	/**
	 * Získá user info z access tokenu
	 *
	 * @param AccessToken $token Access token
	 * @return array{
	 *   sub: string,
	 *   email?: string,
	 *   name?: string,
	 *   given_name?: string,
	 *   family_name?: string,
	 *   birthdate?: string,
	 *   phone_number?: string,
	 *   address?: array,
	 *   acr?: string
	 * } User data z BankID
	 * @throws VerificationFailedException pokud získání dat selže
	 */
	public function getUserInfo(AccessToken $token): array
	{
		try {
			$user = $this->provider->getResourceOwner($token);
			return $user->toArray();
		} catch (IdentityProviderException $e) {
			throw new VerificationFailedException(
				'Failed to retrieve user info: ' . $e->getMessage(),
				$e->getCode(),
				$e
			);
		}
	}

	/**
	 * Completes the OAuth2/OIDC flow: validates state + nonce, exchanges the
	 * authorization code for tokens, and returns verified user data.
	 *
	 * State and nonce MUST have been stored in the session when getAuthorizationUrl()
	 * was called. Pass them here so they can be verified before any token exchange.
	 *
	 * @param string      $code          Authorization code from the callback URL
	 * @param string      $receivedState State value received in the callback query string
	 * @param string      $expectedState State value stored in the session (CSRF token)
	 * @param string|null $expectedNonce Nonce stored in the session; pass null to skip
	 *                                   nonce validation (not recommended)
	 * @return array{user: array, token: AccessToken} Verified user data and access token
	 * @throws VerificationFailedException if state/nonce validation fails or auth errors occur
	 */
	public function authenticate(
		#[\SensitiveParameter] string $code,
		#[\SensitiveParameter] string $receivedState,
		#[\SensitiveParameter] string $expectedState,
		#[\SensitiveParameter] ?string $expectedNonce = null,
	): array {
		// Validate CSRF state token using constant-time comparison to prevent
		// timing-based state oracle attacks.
		if (!hash_equals($expectedState, $receivedState)) {
			throw new VerificationFailedException(
				'State token mismatch. Possible CSRF attack.'
			);
		}

		$this->log('BankID authenticate started', [
			'code_length' => strlen($code),
		]);

		$token = $this->getAccessToken($code);
		$this->log('Access token retrieved', [
			'expires' => $token->getExpires(),
			'has_refresh_token' => $token->getRefreshToken() !== null,
		]);

		$user = $this->getUserInfo($token);
		$this->log('User info retrieved', [
			'sub' => $user['sub'] ?? null,
			'acr' => $user['acr'] ?? null,
		]);

		// Validate nonce from the ID token against the session-stored value.
		// This prevents ID token replay attacks across different authorization flows.
		if ($expectedNonce !== null) {
			$idTokenNonce = $user['nonce'] ?? null;
			if ($idTokenNonce === null) {
				throw new VerificationFailedException(
					'Nonce claim missing from ID token response.'
				);
			}
			if (!hash_equals($expectedNonce, (string) $idTokenNonce)) {
				throw new VerificationFailedException(
					'Nonce mismatch. Possible ID token replay attack.'
				);
			}
		}

		// Store user data in Tracy panel for debugging
		if ($this->panel !== null) {
			$this->panel->setUserData($user);
		}

		return [
			'user' => $user,
			'token' => $token,
		];
	}

	/**
	 * Vytvoří strukturované user data pro použití v aplikaci
	 *
	 * @param array<string, mixed> $userData Raw data z getUserInfo()
	 * @return array{
	 *   verified: bool,
	 *   full_name: string|null,
	 *   email: string|null,
	 *   phone: string|null,
	 *   birthdate: string|null,
	 *   bankid_sub: string,
	 *   verified_at: \DateTimeImmutable,
	 *   assurance_level: string
	 * } Strukturovaná data
	 */
	public function createReporterData(array $userData): array
	{
		return [
			'verified' => true,
			'full_name' => $this->getFullName($userData),
			'email' => $userData['email'] ?? null,
			'phone' => $userData['phone_number'] ?? null,
			'birthdate' => $userData['birthdate'] ?? null,
			'bankid_sub' => $userData['sub'],
			'verified_at' => new \DateTimeImmutable(),
			'assurance_level' => $this->getAssuranceLevel($userData),
		];
	}

	/**
	 * Poskládá celé jméno z BankID dat
	 *
	 * @param array<string, mixed> $userData User data
	 * @return string|null Celé jméno nebo null
	 */
	private function getFullName(array $userData): ?string
	{
		$givenName = $userData['given_name'] ?? '';
		$familyName = $userData['family_name'] ?? '';

		if ($givenName && $familyName) {
			return trim($givenName . ' ' . $familyName);
		}

		return $userData['name'] ?? null;
	}

	/**
	 * Zkontroluje, jestli je BankID v sandbox nebo production režimu
	 *
	 * @return bool True pokud sandbox, false pokud production
	 */
	public function isSandbox(): bool
	{
		return $this->sandbox;
	}

	/**
	 * Získá level of assurance (LOA) z user dat
	 * BankID poskytuje LOA3 (vysoká úroveň ověření)
	 *
	 * @param array<string, mixed> $userData User data
	 * @return string LOA level (loa1, loa2, loa3)
	 */
	public function getAssuranceLevel(array $userData): string
	{
		return $userData['acr'] ?? 'loa3';
	}

	/**
	 * Získá client ID (pro debugging)
	 *
	 * @return string Client ID
	 */
	public function getClientId(): string
	{
		return $this->clientId;
	}

	/**
	 * Získá redirect URI (pro debugging)
	 *
	 * @return string Redirect URI
	 */
	public function getRedirectUri(): string
	{
		return $this->redirectUri;
	}
}
