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
		return new JsonResponse(true, 200);
	}
}