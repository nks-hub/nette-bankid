<?php

declare(strict_types=1);

namespace NksHub\NetteBankId\Tracy;

use Tracy\IBarPanel;

/**
 * Tracy debug bar panel pro BankID autentizaci
 */
class BankIdPanel implements IBarPanel
{
	/** @var array<int, array{time: float, message: string, context: array}> */
	private array $logs = [];

	private bool $sandbox;
	private string $redirectUri;
	private ?array $userData = null;

	public function __construct(bool $sandbox, string $redirectUri)
	{
		$this->sandbox = $sandbox;
		$this->redirectUri = $redirectUri;
	}

	/**
	 * Nastaví user data pro zobrazení v panelu
	 */
	public function setUserData(array $userData): void
	{
		$this->userData = $userData;
	}

	/**
	 * Přidá log záznam
	 */
	public function log(string $message, array $context = []): void
	{
		$this->logs[] = [
			'time' => microtime(true),
			'message' => $message,
			'context' => $context,
		];
	}

	/**
	 * Vrátí HTML kód pro tab v debug baru
	 */
	public function getTab(): string
	{
		$count = count($this->logs);

		return <<<HTML
<span title="BankID Authentication">
	<svg version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="23 24 146 29" style="vertical-align: bottom; width:3.8em; height:1.4em; margin-right:-0.2em">
		<g>
			<path d="M147.9415741,29.4746799h6.5875702c5.190155,0,7.7852936,4.3118839,7.7852936,8.5837765
				c0,4.3118858-2.5951385,8.5837746-7.7852936,8.5837746h-6.5875702v-1.9962158h2.7947083V31.4708958h-2.7947083V29.4746799z
				 M144.1487274,52.0320625h10.5800781c9.4620514,0,14.1731873-6.9868011,14.1731873-13.9736061
				s-4.7111359-13.9736061-14.1731873-13.9736061h-10.5800781V52.0320625z M133.3696289,29.4746799h5.9886475v-5.3898296h-5.9886475
				V29.4746799z M133.3696289,31.4708958h5.9886475v20.5611668h-5.9886475V31.4708958z M108.4572601,52.0320625v-6.8670197h1.9962158
				l5.9886475,6.8670197h5.9487457v-1.9962158l-7.545723-8.4639931l7.3461533-8.1047401v-1.9962177h-5.7092819l-6.0285416,6.8670216
				v2.7548065h-1.9962158V24.0848503h-5.9887466v27.9472122H108.4572601z M91.8885269,52.0320625h5.9887466V39.5755424
				c0-5.1502647-3.7529678-8.5038891-7.9449768-8.5038891c-1.7567444,0-3.593277,0.5989132-5.2301483,1.8764324v2.31567h-1.9962158
				v-3.79286H77.51577v20.5611668h5.9886475v-11.378479c0-2.874588,2.1958847-4.3917694,4.3517838-4.3917694
				c2.0361023,0,4.0323257,1.357502,4.0323257,4.1921005V52.0320625z M61.6262169,36.1820259
				c2.6750145,0,4.9107018,2.1558914,4.9107018,5.5495071c0,3.3935165-2.2356873,5.5495071-4.9107018,5.5495071
				c-2.6749229,0-5.0703812-2.1559906-5.0703812-5.5495071C56.5558357,38.3379173,58.9512939,36.1820259,61.6262169,36.1820259
				 M50.3675194,41.7315331c0,6.6274529,4.7510223,10.6997757,9.4621506,10.6997757
				c1.8764343,0,3.7528648-0.6388092,5.3099403-2.0761032v-2.1160011h1.9962234v3.7928581h5.5894012V31.4708958h-5.5894012v3.79286
				h-1.9962234v-2.1160011c-1.5570755-1.4372921-3.433506-2.0761013-5.3099403-2.0761013
				C55.1185417,31.0716534,50.3675194,35.1439705,50.3675194,41.7315331 M30.0463009,29.0754375h7.266264
				c2.2357788,0,3.3936157,1.5969715,3.3936157,3.1939449s-1.1578369,3.1939468-3.3936157,3.1939468h-5.2699547v5.1902542h5.7889786
				c2.2357826,0,3.3936157,1.5969734,3.3936157,3.1939468s-1.1578331,3.1939468-3.3936157,3.1939468h-7.7852879V29.0754375z
				 M45.0579567,38.8569412h-3.2738304v-1.9962158h2.874588c1.6368675-1.3574066,2.4753456-3.3137245,2.4753456-5.3099422
				c0-3.7129688-2.9144859-7.4659328-8.983017-7.4659328H23.858078v27.9472122h14.8518829
				c6.1084328,0,9.0629082-3.8727455,9.0629082-7.6655045C47.7728691,42.2505569,46.854599,40.2143517,45.0579567,38.8569412"/>
		</g>
	</svg>
	<span class="tracy-label">({$count})</span>
</span>
HTML;
	}

	/**
	 * Vrátí HTML kód pro panel v debug baru
	 */
	public function getPanel(): string
	{
		$mode = $this->sandbox ? '<span style="color: orange">Sandbox</span>' : '<span style="color: green">Production</span>';
		$logsHtml = $this->renderLogs();
		$userDataHtml = $this->renderUserData();

		return <<<HTML
<h1>BankID Authentication</h1>
<div class="tracy-inner">
	<div class="tracy-inner-container">
		<table>
			<tr>
				<th>Mode:</th>
				<td>{$mode}</td>
			</tr>
			<tr>
				<th>Redirect URI:</th>
				<td><code>{$this->redirectUri}</code></td>
			</tr>
			<tr>
				<th>Logs:</th>
				<td>{$this->getLogsCount()}</td>
			</tr>
		</table>

		{$userDataHtml}
		{$logsHtml}
	</div>
</div>
HTML;
	}

	private function getLogsCount(): string
	{
		$count = count($this->logs);
		if ($count === 0) {
			return '<span style="color: gray">No activity</span>';
		}
		return "<strong>{$count} events</strong>";
	}

	private function renderUserData(): string
	{
		if ($this->userData === null) {
			return '';
		}

		$html = '<h2>Authenticated User Data</h2>';
		$html .= '<table style="width: 100%">';
		$html .= '<tr><th style="width: 200px">Field</th><th>Value</th></tr>';

		// Důležité pole zvýrazněné
		$importantFields = ['sub', 'email', 'name', 'given_name', 'family_name', 'birthdate', 'phone_number', 'acr'];

		foreach ($importantFields as $field) {
			if (isset($this->userData[$field])) {
				$label = htmlspecialchars($field);
				$value = $this->formatValue($this->userData[$field]);
				$html .= "<tr><td><strong>{$label}:</strong></td><td>{$value}</td></tr>";
			}
		}

		// Ostatní pole
		$otherFields = array_diff(array_keys($this->userData), $importantFields);
		if (!empty($otherFields)) {
			foreach ($otherFields as $field) {
				$label = htmlspecialchars($field);
				$value = $this->formatValue($this->userData[$field]);
				$html .= "<tr><td>{$label}:</td><td>{$value}</td></tr>";
			}
		}

		$html .= '</table>';
		return $html;
	}

	private function renderLogs(): string
	{
		if (empty($this->logs)) {
			return '<p style="color: gray; font-style: italic;">No BankID activity in this request.</p>';
		}

		$html = '<h2>Authentication Flow</h2><table style="width: 100%">';
		$html .= '<tr><th>Time</th><th>Event</th><th>Details</th></tr>';

		$startTime = $this->logs[0]['time'];
		foreach ($this->logs as $log) {
			$elapsed = number_format(($log['time'] - $startTime) * 1000, 2);
			$message = htmlspecialchars($log['message']);
			$context = $this->renderContext($log['context']);

			$html .= <<<HTML
<tr>
	<td style="white-space: nowrap; color: gray;">+{$elapsed}ms</td>
	<td><strong>{$message}</strong></td>
	<td>{$context}</td>
</tr>
HTML;
		}

		$html .= '</table>';
		return $html;
	}

	private function renderContext(array $context): string
	{
		if (empty($context)) {
			return '<span style="color: gray;">—</span>';
		}

		$html = '<dl style="margin: 0; padding: 0;">';
		foreach ($context as $key => $value) {
			$key = htmlspecialchars($key);
			$value = $this->formatValue($value);
			$html .= "<dt style=\"display: inline; font-weight: bold;\">{$key}:</dt> ";
			$html .= "<dd style=\"display: inline; margin: 0;\">{$value}</dd><br>";
		}
		$html .= '</dl>';
		return $html;
	}

	private function formatValue(mixed $value): string
	{
		if (is_bool($value)) {
			return $value ? '<span style="color: green;">✓ true</span>' : '<span style="color: red;">✗ false</span>';
		}

		if (is_null($value)) {
			return '<span style="color: gray;">null</span>';
		}

		if (is_array($value)) {
			return '<code>' . htmlspecialchars(json_encode($value, JSON_UNESCAPED_UNICODE)) . '</code>';
		}

		if (is_string($value) && strlen($value) > 50) {
			return '<code>' . htmlspecialchars(substr($value, 0, 50)) . '...</code>';
		}

		return '<code>' . htmlspecialchars((string) $value) . '</code>';
	}
}
