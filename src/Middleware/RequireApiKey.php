<?php

namespace WgEndpointApi\Middleware;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ServerRequestInterface;

class RequireApiKey
{
	/**
	 * Ensure a valid API key is given to authenticate the client
	 * @param ServerRequestInterface $request
	 * @param \Closure $next
	 * @throws \Exception
	 */
	public function handle(ServerRequestInterface $request, \Closure $next)
	{
		$expectedApiKey = $_ENV['API_KEY'] ?? null;
		$givenApiKey = $request->getHeader('X-API-Key');

		if ($expectedApiKey && \count($givenApiKey) === 1 && $givenApiKey[0] === $expectedApiKey) {
			return $next($request);
		}

		throw new \Exception("Unauthorized", 401);
	}
}