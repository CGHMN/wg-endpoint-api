<?php

use MiladRahimi\PhpRouter\Router;
use WgEndpointApi\Controllers\WireguardInterfaceController;
use WgEndpointApi\Middleware\RequireApiKey;

/** @var Router $router Instance of PHP router, creted by sourcing index.php script */

// Prefix all API routes with /api/v1
$router->group([
	'prefix' => '/api/v1',
	'middleware' => [RequireApiKey::class]
], function (Router $router) {
	// Synchronize a Wireguard configuration against this host
	$router->post('/interfaces/{interface}/sync', [WireguardInterfaceController::class, 'syncConfig']);

	// TODO: If there's ever a big wave of boredom, implement the below endpoints
	// // Index and create new interfaces
	// $router->get('/interfaces', fn () => '');
	// $router->post('/interfaces', fn () => '');

	// // Get, modify and delete existing interfaces
	// $router->get('/interfaces/{interfaceName}', fn ($interfaceName) => '');
	// $router->put('/interfaces/{interfaceName}', fn ($interfaceName) => '');
	// $router->delete('/interfaces/{interfaceName}', fn ($interfaceName) => '');

	// // Get the Wireguard configuration from an existing interface
	// $router->get('/interfaces/{interfaceName}/configuration', fn ($interfaceName) => '');

	// // Set the Wireguard configuration of an existing interface and apply it
	// $router->post('/interfaces/{interfaceName}/configuration', fn ($interfaceName) => '');

	// // Get statistics from the interface
	// $router->get('/interfaces/{interfaceName}/stats', fn ($interfaceName) => '');

	// // Index and create new peers on an interface
	// $router->get('/interfaces/{interfaceName}/peers', fn ($interfaceName) => '');
	// $router->get('/interfaces/{interfaceName}/peers', fn ($interfaceName) => '');

	// // Get, modify and delete existing peers on an interface
	// $router->get('/interfaces/{interfaceName}/peers/{peerPublicKey}', fn ($interfaceName, $peerPublicKey) => '');
	// $router->patch('/interfaces/{interfaceName}/peers/{peerPublicKey}', fn ($interfaceName, $peerPublicKey) => '');
	// $router->delete('/interfaces/{interfaceName}/peers/{peerPublicKey}', fn ($interfaceName, $peerPublicKey) => '');

	// // Get statistics for the given peer
	// $router->get('/interfaces/{interfaceName}/peers/{peerPublicKey}/stats', fn ($interfaceName) => '');
});