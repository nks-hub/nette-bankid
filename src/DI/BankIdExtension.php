<?php

declare(strict_types=1);

namespace NksHub\NetteBankId\DI;

use Nette\DI\CompilerExtension;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use NksHub\NetteBankId\OAuth2\BankIdProvider;

/**
 * Nette DI Extension pro BankID autentizaci
 *
 * Registruje BankIdProvider službu pro OAuth2/OIDC autentizaci přes BankID.
 *
 * Použití v config.neon:
 * <code>
 * extensions:
 *     bankid: NksHub\NetteBankId\DI\BankIdExtension
 *
 * bankid:
 *     clientId: 'your-client-id'
 *     clientSecret: 'your-client-secret'
 *     redirectUri: 'https://your-domain.com/bankid/callback'
 *     sandbox: false
 * </code>
 */
class BankIdExtension extends CompilerExtension
{
	/**
	 * Definuje schéma konfigurace
	 */
	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'clientId' => Expect::string()->required(),
			'clientSecret' => Expect::string()->required(),
			'redirectUri' => Expect::string()->required(),
			'authorizeUrl' => Expect::string()->nullable(),
			'tokenUrl' => Expect::string()->nullable(),
			'userinfoUrl' => Expect::string()->nullable(),
			'sandbox' => Expect::bool(false),
			'country' => Expect::anyOf('cz', 'sk')->default('cz'),
			'debug' => Expect::bool(false),
		]);
	}

	/**
	 * Načte konfiguraci a zaregistruje služby
	 */
	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();
		$config = $this->getConfig();

		// Automaticky nastav URLs podle sandbox režimu a země
		$urls = $this->resolveUrls(
			$config->sandbox,
			$config->country,
			$config->authorizeUrl,
			$config->tokenUrl,
			$config->userinfoUrl
		);

		// Registruj BankIdProvider jako službu
		$builder->addDefinition($this->prefix('provider'))
			->setFactory(BankIdProvider::class, [
				'clientId' => $config->clientId,
				'clientSecret' => $config->clientSecret,
				'redirectUri' => $config->redirectUri,
				'authorizeUrl' => $urls['authorize'],
				'tokenUrl' => $urls['token'],
				'userinfoUrl' => $urls['userinfo'],
				'sandbox' => $config->sandbox,
				'debug' => $config->debug,
			])
			->setAutowired(true);
	}

	/**
	 * Vyřeš URLs podle sandbox režimu a země
	 *
	 * @return array{authorize: string, token: string, userinfo: string}
	 */
	private function resolveUrls(
		bool $sandbox,
		string $country,
		?string $customAuthorizeUrl,
		?string $customTokenUrl,
		?string $customUserinfoUrl
	): array {
		// Pokud jsou všechny URLs custom, použij je
		if ($customAuthorizeUrl && $customTokenUrl && $customUserinfoUrl) {
			return [
				'authorize' => $customAuthorizeUrl,
				'token' => $customTokenUrl,
				'userinfo' => $customUserinfoUrl,
			];
		}

		// Jinak použij defaultní podle země a sandbox režimu
		if ($country === 'cz') {
			if ($sandbox) {
				return [
					'authorize' => $customAuthorizeUrl ?? BankIdProvider::CZ_SANDBOX_AUTHORIZE_URL,
					'token' => $customTokenUrl ?? BankIdProvider::CZ_SANDBOX_TOKEN_URL,
					'userinfo' => $customUserinfoUrl ?? BankIdProvider::CZ_SANDBOX_USERINFO_URL,
				];
			}

			return [
				'authorize' => $customAuthorizeUrl ?? BankIdProvider::CZ_AUTHORIZE_URL,
				'token' => $customTokenUrl ?? BankIdProvider::CZ_TOKEN_URL,
				'userinfo' => $customUserinfoUrl ?? BankIdProvider::CZ_USERINFO_URL,
			];
		}

		// Pro SK budou v budoucnu jiné endpoints
		// TODO: Přidat SK_AUTHORIZE_URL, SK_TOKEN_URL, SK_USERINFO_URL konstanty
		throw new \InvalidArgumentException(
			'Slovak BankID endpoints are not yet implemented. Please provide custom URLs.'
		);
	}

	/**
	 * Validuje konfiguraci při startu
	 */
	public function afterCompile(\Nette\PhpGenerator\ClassType $class): void
	{
		$initialize = $class->getMethod('initialize');

		// Přidej validaci, že jsou všechny potřebné parametry nastaveny
		$initialize->addBody(
			'// Validate BankID configuration' . "\n" .
			'if (!$this->getService(?)->getClientId()) {' . "\n" .
			'	throw new \RuntimeException(\'BankID clientId is required\');' . "\n" .
			'}',
			[$this->prefix('provider')]
		);
	}
}
