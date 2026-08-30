<?php

namespace WgEndpointApi\Helpers;

use deemru\Curve25519;
use Random\Engine\Secure;

class WireguardHelper
{
	/**
	 * Tests if a base64 encoded Wireguard private key is valid
	 * 
	 * @param string $privateKey Private key to check
	 * 
	 * @return bool True if the input is a valid Wireguard private key, false if not
	 */
	public static function keyIsValid(string $privateKey): bool
	{
		$decoded = base64_decode($privateKey);

		if ($decoded === false || \strlen($decoded) !== 32) {
			return false;
		}

		return true;
	}

	/**
	 * Derive the public key from the given private key
	 * 
	 * @param string $privateKey Private key to derive public key from
	 * 
	 * @return string Public key
	 * @throws \InvalidArgumentException when the given key is not a valid Wireguard private key
	 */
	public static function getPublicKey(string $privateKey): string
	{
		if (! self::keyIsValid($privateKey)) {
			throw new \InvalidArgumentException("The given private key is not a valid Wireguard private key");
		}

		return base64_encode(
			(new Curve25519())->getPublicKeyFromPrivateKey(base64_decode($privateKey))
		);
	}

	/**
	 * Generate a new Wireguard private or preshared key
	 * @return string
	 */
	public static function generateKey(): string
	{
		// Use the Secure Randomness engine PHP offers
		$secure_engine = new Secure();

		/**
		 * It only generates a fixed amount of randomness with each call, however, 
		 * depending on the value of PHP_INT_SIZE. SO generate enough randomness to get
		 * at least 32 bytes required for the key and trim it to 32 bytes exactly later
		 */
		$secure_generate_amount = ceil(32 / PHP_INT_SIZE);

		$randomness = '';
		for ($i = 0; $i <= $secure_generate_amount; $i++) {
			$randomness .= $secure_engine->generate();
		}

		$randomness = substr($randomness, 0, 32);

		return base64_encode($randomness);
	}
}