<?php

namespace WgEndpointApi\Controllers;

use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\ServerRequest;
use WgEndpointApi\Classes\WireguardInterface;

class WireguardInterfaceController
{
	public function syncConfig(ServerRequest $request, string $interface): JsonResponse
	{
		$interface = WireguardInterface::fromJson($interface, $request->getBody()->getContents());
		$interface->apply();

		// Write cached configuration file
		if ($_ENV['CONFIGURATION_CACHE_DIR'] && is_dir($_ENV['CONFIGURATION_CACHE_DIR'])) {
			try {
				file_put_contents("{$_ENV['CONFIGURATION_CACHE_DIR']}/{$interface}.conf", $interface->getConfiguration(true));
			} catch (\Exception $exception) {
				error_log("Unable to write new cached configuration file: {$exception->getMessage()}");
			}
		}

		return new JsonResponse(true, 200);
	}
}