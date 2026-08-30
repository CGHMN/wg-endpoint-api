<?php
// Entrypoint for requests to /api endpoints

use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\JsonResponse;
use MiladRahimi\PhpRouter\Exceptions\RouteNotFoundException;
use MiladRahimi\PhpRouter\Router;
use MiladRahimi\PhpRouter\View\View;

require_once(__DIR__ . '/../vendor/autoload.php');

$router = Router::create();

// Set up SSR views
$router->setupView(__DIR__ . '/../views');

// Load routes from route definition files
require_once(__DIR__ . '/../routes/api.php');

// Dispatch router and handle errors
try {
	$router->dispatch();
} catch (\Throwable $exception) {
	/** @var bool $asJson Is true when the API should respond with a JSON body */
	$asJson = preg_match('/^\/api\//', $_SERVER['REQUEST_URI']);

	// Get message and HTTP code from exception
	$exceptionString = $exception->getMessage();
	$exceptionCode = $exception->getCode();

	if ($exception instanceof RouteNotFoundException) {
		// Set static exception string and HTTP code on Route Not Found exceptions
		$exceptionString = 'Route not found';
		$exceptionCode = 404;
	} else if (100 > $exceptionCode || $exceptionCode > 599) {
		// Otherwise, ensure the HTTP code is valid and set it to generic 500 if not
		$exceptionCode = 500;
	}

	// Render the exception as JSON when the above asJson check is true
	if ($asJson) {
		$router->getPublisher()->publish(new JsonResponse(['message' => $exceptionString], $exceptionCode));
		throw $exception;
	}

	// Try to render the exception with a view named after the exceptions HTTP code,
	// otherwise just return a simple HTML error message
	try {
		$router->getContainer()->get(View::class)->make("errors/{$exceptionCode}");
	} catch (\Throwable $_) {
		$router->getPublisher()->publish(new HtmlResponse($exceptionString, $exceptionCode));
	}

	throw $exception;
}