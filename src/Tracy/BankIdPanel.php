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

	public function __construct(bool $sandbox, string $redirectUri)
	{
		$this->sandbox = $sandbox;
		$this->redirectUri = $redirectUri;
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
		$icon = $this->sandbox ? '🟡' : '🟢';

		return <<<HTML
<span title="BankID Authentication">
	<svg viewBox="0 0 2048 2048" style="vertical-align: bottom; width:1.23em; height:1.4em">
		<path fill="#4285f4" d="M1024 256c424 0 768 344 768 768s-344 768-768 768-768-344-768-768 344-768 768-768zm0 128c353 0 640 287 640 640s-287 640-640 640-640-287-640-640 287-640 640-640zm-64 256v512l384 0v-128h-256v-384h-128z"/>
	</svg>
	<span class="tracy-label">{$icon} BankID ({$count})</span>
</span>
HTML;
	}

	/**
	 * Vrátí HTML kód pro panel v debug baru
	 */
	public function getPanel(): string
	{
		$mode = $this->sandbox ? '<span style="color: orange">🟡 Sandbox</span>' : '<span style="color: green">🟢 Production</span>';
		$logsHtml = $this->renderLogs();

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
