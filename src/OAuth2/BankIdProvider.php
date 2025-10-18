<?php

declare(strict_types=1);

namespace NksHub\NetteBankId\OAuth2;

use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use NksHub\NetteBankId\Exceptions\VerificationFailedException;

/**
 * BankID OAuth2/OpenID Connect Provider
 *
 * Wrapper pro league/oauth2-client s BankID-specific konfigurací
 * Podporuje české i slovenské BankID (rozdílné endpoints)
 */
class BankIdProvider
{
	private GenericProvider $provider;

	/**
	 * Default endpoints pro production BankID (CZ)
	 */
	public const CZ_AUTHORIZE_URL = 'https://oidc.bankid.cz/auth/authorize';
	public const CZ_TOKEN_URL = 'https://oidc.bankid.cz/auth/token';
	public const CZ_USERINFO_URL = 'https://oidc.bankid.cz/auth/userinfo';

	/**
	 * Sandbox endpoints pro testování (CZ)
	 */
	public const CZ_SANDBOX_AUTHORIZE_URL = 'https://oidc.sandbox.bankid.cz/auth/authorize';
	public const CZ_SANDBOX_TOKEN_URL = 'https://oidc.sandbox.bankid.cz/auth/token';
	public const CZ_SANDBOX_USERINFO_URL = 'https://oidc.sandbox.bankid.cz/auth/userinfo';

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

	public function __construct(
		private readonly string $clientId,
		private readonly string $clientSecret,
		private readonly string $redirectUri,
		private readonly string $authorizeUrl,
		private readonly string $tokenUrl,
		private readonly string $userinfoUrl,
		private readonly bool $sandbox = false,
	) {
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
		]);
	}

	/**
	 * Získá authorization URL pro redirect na BankID login
	 *
	 * @param array<string, mixed> $options Dodatečné parametry (scope, nonce, atd.)
	 * @return string Authorization URL
	 */
	public function getAuthorizationUrl(array $options = []): string
	{
		// Generate nonce for additional security (OIDC requirement)
		$nonce = bin2hex(random_bytes(16));

		return $this->provider->getAuthorizationUrl(array_merge([
			'scope' => self::DEFAULT_SCOPES,
			'nonce' => $nonce,
			'prompt' => 'login',
			'display' => 'page',
			'acr_values' => 'loa3' // Level of Assurance 3 (highest)
		], $options));
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
	 * Validuje state token proti původnímu
	 *
	 * @param string $state State token z callback URL
	 * @throws VerificationFailedException pokud state neodpovídá
	 */
	public function validateState(string $state): void
	{
		$expectedState = $this->provider->getState();

		if ($state !== $expectedState) {
			throw new VerificationFailedException(
				'State token mismatch - možný CSRF útok'
			);
		}
	}

	/**
	 * Vymění authorization code za access token
	 *
	 * @param string $code Authorization code z callback URL
	 * @return AccessToken Access token pro získání user info
	 * @throws VerificationFailedException pokud výměna selže
	 */
	public function getAccessToken(string $code): AccessToken
	{
		try {
			return $this->provider->getAccessToken('authorization_code', [
				'code' => $code
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
	 * Kompletní OAuth2 flow - získá user data z authorization code
	 *
	 * @param string $code Authorization code z callback URL
	 * @param string $state State token pro CSRF validaci
	 * @return array{
	 *   user: array,
	 *   token: AccessToken
	 * } User data a access token
	 * @throws VerificationFailedException pokud autentizace selže
	 */
	public function authenticate(string $code, string $state): array
	{
		$this->validateState($state);
		$token = $this->getAccessToken($code);
		$user = $this->getUserInfo($token);

		return [
			'user' => $user,
			'token' => $token
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
