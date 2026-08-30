<?php

namespace WgEndpointApi\Classes;

use IPCalc\IP;
use WgEndpointApi\Helpers\WireguardHelper;

/**
 * Summary of WireguardPeer
 */
class WireguardPeer implements \Stringable
{
	/**
	 * Public key of the peer
	 * @var string
	 */
	public private(set) string $publicKey {
		set(string $value) {
			if (! WireguardHelper::keyIsValid($value)) {
				throw new \InvalidArgumentException("The given public key is not a valid Wireguard public key");
			}

			$this->publicKey = $value;
		}
	}

	/**
	 * Optional name of the peer, added as a comment in the output configuration for a better peer overview
	 * @var string|null
	 */
	public string|null $name = null;

	/**
	 * Preshared key of the peer, set to `null` to disable
	 * @var string|null
	 */
	public string|null $presharedKey = null {
		get {
			return $this->presharedKey;
		}
		set(string|null $value) {
			if ($value !== null && ! WireguardHelper::keyIsValid($value)) {
				throw new \InvalidArgumentException("The given preshared key is not a valid Wireguard preshared key");
			}

			$this->presharedKey = $value;
		}
	}

	/**
	 * Address of the remote endpoint, set this and `$endpointPort` to `null` to enable dynamic peer endpoint addressing
	 * @var string|null
	 */
	public string|null $endpointAddress = null {
		set(string|null $value) {
			if ($value === null) {
				$this->endpointAddress = null;
			} else if (
			filter_var($value, FILTER_VALIDATE_DOMAIN) === false ||
			filter_var($value, FILTER_VALIDATE_IP) === false
			) {
				throw new \InvalidArgumentException('Endpoint address needs to be a valid domain name or IP address');
			}

			$this->endpointAddress = $value;
		}
	}

	/**
	 * Listen port of the remote endpoint, set this and `$endpointAddress` to `null` to enable dynamic peer endpoint addressing
	 * @var int|null
	 */
	public int|null $endpointPort = null {
		set(int|null $value) {
			if ($value === null) {
				$this->endpointPort = null;
			} else if (! \is_int($value)) {
				throw new \InvalidArgumentException('Trying to assign non-number to endpoint port property');
			} else if (1 > $value || $value > 65535) {
				throw new \InvalidArgumentException('Trying to assign endpoint port port outside of valid range 1-65535');
			} else {
				$this->endpointPort = $value;
			}
		}
	}

	/**
	 * Persistent keepalive interval of the peer, set to `null` to disable
	 * @var int|null
	 */
	public int|null $persistentKeepalive = null {
		set(int|null $value) {
			if ($value === null || $value === 0) {
				$this->persistentKeepalive = null;
			} else if (! \is_int($value)) {
				throw new \InvalidArgumentException('Trying to assign non-number to persistent keepalive property');
			} else if (1 > $value || $value > 65535) {
				throw new \InvalidArgumentException('Trying to assign persistent keepalive interval outside of valid range 1-65535');
			} else {
				$this->persistentKeepalive = $value;
			}
		}
	}

	/**
	 * List of all allowed IPs in CIDR notation of the peer
	 * @var string[]
	 */
	private array $allowedIps = [];

	/**
	 * Create new Wireguard peer object
	 * @param string $publicKey Public key of the peer
	 * @param string|null $presharedKey Preshared key of the peer, set to `null` to disable
	 * @param string|null $endpointAddress Address of the remote endpoint, set this and `$endpointPort` to `null` to enable dynamic peer endpoint addressing
	 * @param int|null $endpointPort Listen port of the remote endpoint, set this and `$endpointAddress` to `null` to enable dynamic peer endpoint addressing
	 * @param int|null $persistentKeepalive Persistent keepalive interval of the peer, set to `null` to disable
	 * @param string|null $name Optional name of the peer, added as a comment in the output configuration for a better peer overview
	 */
	public function __construct(
		string $publicKey,
		string|null $presharedKey = null,
		string|null $endpointAddress = null,
		int|null $endpointPort = null,
		int|null $persistentKeepalive = null,
		string|null $name = null
	) {
		$this->publicKey = $publicKey;
		$this->presharedKey = $presharedKey;
		$this->endpointAddress = $endpointAddress;
		$this->endpointPort = $endpointPort;
		$this->persistentKeepalive = $persistentKeepalive;
		$this->name = $name;
	}

	/**
	 * Represent a peer as string by its public key
	 * @return string
	 */
	public function __tostring(): string
	{
		return $this->publicKey;
	}

	/**
	 * Get a list of all allowed IPs for this peer, in CIDR notation
	 * @return string[]
	 */
	public function getAllowedIps(): array
	{
		return [...$this->allowedIps];
	}

	/**
	 * Set the list of all allowed IPs for this peer, in CIDR notation, overwriting previously allowed IPs
	 * @param string[] $ips Allowed IPs to apply
	 * @return void
	 */
	public function setAllowedIps(array $ips): void
	{
		$this->allowedIps = [];
		foreach ($ips as $ip) {
			$this->addAllowedIp($ip);
		}
	}

	/**
	 * Add an allowed IP to this peer, in CIDR notation
	 * @param string $ip Allowed IP to add
	 * @return void
	 */
	public function addAllowedIp(string $ip): void
	{
		$parsed_ip = new IP($ip);
		$cidr = "{$parsed_ip->getIp()}/{$parsed_ip->getCidr()}";

		if (array_search($cidr, $this->allowedIps) === false) {
			$this->allowedIps[] = $cidr;
		}
	}

	/**
	 * Remove an allowed IP from this peer, in CIDR notation, must match exactly
	 * @param string $ip Allowed IP to remove
	 * @return void
	 */
	public function removeAllowedIp(string $ip): void
	{
		$parsed_ip = new IP($ip);
		$cidr = "{$parsed_ip->getIp()}/{$parsed_ip->getCidr()}";

		if (($index = array_search($cidr, $this->allowedIps)) !== false) {
			array_splice($this->allowedIps, $index, 1);
		}
	}

	/**
	 * Try to find an allowed IP entry that matches the given IP address
	 * @param string $ip IP address in CIDR notation
	 * @return string|false
	 */
	public function findAllowedIp(string $ip): string|false
	{
		$parsed_ip = new IP($ip);
		$cidr = "{$parsed_ip->getIp()}/{$parsed_ip->getCidr()}";

		foreach ($this->allowedIps as $allowed_ip) {
			$parsed_allowed_ip = new IP($allowed_ip);
			$parsed_cidr = "{$parsed_allowed_ip->getIp()}/{$parsed_allowed_ip->getCidr()}";

			if ($parsed_allowed_ip->contains($cidr) || $cidr === $parsed_cidr) {
				return $parsed_cidr;
			}
		}

		return false;
	}

	/**
	 * Render this peer as a Wireguard configuration section
	 * @return string
	 */
	public function getConfigurationSection(): string
	{
		$config_string = <<<WG
		[Peer]
		PublicKey = {$this->publicKey}
		WG."\n";

		if ($this->presharedKey) {
			$config_string .= "PresharedKey = {$this->presharedKey}\n";
		}

		if (\count($this->allowedIps) > 0) {
			$formattedAllowedIps = implode(", ", $this->allowedIps);
			$config_string .= "AllowedIPs = {$formattedAllowedIps}\n";
		}

		if ($this->endpointAddress && $this->endpointPort) {
			try {
				$endpoint_ip = new IP($this->endpointAddress);
				$endpoint_address = $endpoint_ip->getVersion() === 6 ?
					"[{$endpoint_ip->getIp()}]" :
					$endpoint_ip->getIp();
			} catch (\Throwable $_) {
				$endpoint_address = $this->endpointAddress;
			}

			$config_string .= "Endpoint = {$endpoint_address}:{$this->endpointPort}\n";
		}

		if ($this->persistentKeepalive) {
			$config_string .= "PersistentKeepalive = {$this->persistentKeepalive}\n";
		}

		return $config_string;
	}
}