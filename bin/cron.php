<?php

use GuzzleHttp\Client;

// This script should be called by cron periodically to keep this Wireguard node in sync with the control server.
// It can also be called at boot to bring up the Wireguard server itself.
// If the control server is unreachable, a cached version of the last Wireguard configuration is attempted to be used,
// if one exists.

require_once(__DIR__ . '/../vendor/autoload.php');

use WgEndpointApi\Classes\WireguardInterface;

/**
 * Write log line
 * @param string $text Text to write to stdout or stderr
 * @return void
 */
function logstr(string $text, bool $toStderr = false): void
{
	$now = date("Y-m-d H:i:s");
	$formatted = "[{$now}] {$text}\n";

	fputs($toStderr ? STDERR : STDOUT, $formatted);
}

if (!$_ENV['MANAGED_INTERFACE']) {
	logstr('Managed interface not configured in .env', true);
	exit(1);
}

$interface_name = $_ENV['MANAGED_INTERFACE'];

if (!($interface = WireguardInterface::getWireguardInterface($interface_name))) {
	// Try to data on the managed Wireguard interface, try to bring one up from the cached configuration file otherwise
	logstr("Managed interface {$interface_name} does not exist");

	if (
		($cached_config_path = realpath("{$_ENV['CONFIGURATION_CACHE_DIR']}/{$interface_name}.conf")) &&
		is_readable($cached_config_path)
	) {
		logstr("Bringing up interface from cached configuration file {$cached_config_path}");
		$interface = WireguardInterface::fromConfFile($cached_config_path, $interface_name);
		$interface->apply();
	} else {
		logstr("Unable to find cached configuration file", true);
	}
}

logstr("Trying to synchronize with upstream control server");
$guzzle = new Client([
	'base_uri' => $_ENV['CONTROL_SERVER_URI'],
	'timeout' => intval($_ENV['CONTROL_SERVER_TIMEOUT'] ?? '5'),
	'headers' => [
		'X-API-Key' => $_ENV['CONTROL_SERVER_AUTHKEY']
	]
]);

$request = $guzzle->get("wg-endpoint-api/{$interface_name}");
$interface = WireguardInterface::fromJson($interface_name, $request->getBody()->getContents());
$interface->apply();

// Write cached configuration file
if ($_ENV['CONFIGURATION_CACHE_DIR'] && is_dir($_ENV['CONFIGURATION_CACHE_DIR'])) {
	try {
		file_put_contents("{$_ENV['CONFIGURATION_CACHE_DIR']}/{$interface_name}.conf", $interface->getConfiguration(true));
	} catch (\Exception $exception) {
		logstr("Unable to write new cached configuration file: {$exception->getMessage()}");
	}
}