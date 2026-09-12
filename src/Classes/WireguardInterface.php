<?php

namespace WgEndpointApi\Classes;

use IPCalc\IP;
use WgEndpointApi\Helpers\ThrowingExec;
use WgEndpointApi\Helpers\WireguardConfigurationParser;
use WgEndpointApi\Helpers\WireguardHelper;

/**
 * Represents a Wireguard interface and associated configuration
 */
class WireguardInterface implements \Stringable
{
	/**
	 * Private key of the Wireguard interface
	 * @var string
	 */
	private string $privateKey {
		get {
			return $this->privateKey;
		}
		set(string $value) {
			if (!WireguardHelper::keyIsValid($value)) {
				throw new \InvalidArgumentException("The given private key is not a valid Wireguard private key");
			}

			$this->privateKey = $value;
			$this->_cachedPubKey = WireguardHelper::getPublicKey($value);
		}
	}

	/**
	 * Cache the public key of this interface to avoid redundant cryptographic calculations
	 * @var string
	 */
	private string|null $_cachedPubKey = null;

	/**
	 * Listening UDP port of the Wireguard interface, set to `null` to select a random port
	 * @var int|null
	 */
	public int|null $listenPort = null {
		set(int|null $value) {
			if ($value === null) {
				$this->listenPort = null;
			} else if (!\is_int($value)) {
				throw new \InvalidArgumentException('Trying to assign non-number to listen port property');
			} else if (1 > $value || $value > 65535) {
				throw new \InvalidArgumentException('Trying to assign listen port outside of valid range 1-65535');
			} else {
				$this->listenPort = $value;
			}
		}
	}

	/**
	 * Firewall mark for packets leaving the interface, set to `null` to disable
	 * @var int|null
	 */
	public int|null $fwMark = null {
		set(int|null $value) {
			if ($value === null || $value === 0) {
				$this->fwMark = null;
			} else if (!\is_int($value)) {
				throw new \InvalidArgumentException('Trying to assign non-number to fwMark property');
			} else if (0 > $value || $value > 4294967295) {
				throw new \InvalidArgumentException('Trying to assign fwMark outside of valid range 1-4294967295');
			} else {
				$this->fwMark = $value;
			}
		}
	}

	/**
	 * Maximum transmission unit,
	 * wg-quick extension to the regular Wireguard configuration syntax
	 * @var int|null
	 */
	public int|null $mtu = null {
		set(int|null $value) {
			if ($value === null || $value === 0) {
				$this->mtu = null;
			} else if (!\is_int($value)) {
				throw new \InvalidArgumentException('Trying to assign non-number to MTU property');
			} else if (68 > $value || $value > 65535) {
				throw new \InvalidArgumentException('Trying to assign MTU outside of valid range 68-65535');
			} else {
				$this->mtu = $value;
			}
		}
	}

	/**
	 * IP address of the tunnel interface,
	 * wg-quick extension to the regular Wireguard configuration syntax
	 */
	public IP|null $address = null {
		set(string|IP|null $value) {
			if (!$value === null || !$value) {
				$this->address = null;
				return;
			}

			if (\is_string($value)) {
				$value = new IP($value);
			}

			$this->address = $value;
		}
	}

	/**
	 * Array of all peers configured on the interface
	 * @var WireguardPeer[]
	 */
	private array $peers = [];

	/**
	 * Create new Wireguard interface object
	 * @param string $interface Name of the network interface device to create the tunnel under
	 * @param string $privateKey Private key of the new Wireguard interface
	 * @param int|null $listenPort Listening UDP port of the Wireguard interface, set to `null` to select a random port
	 * @param int|null $fwMark Firewall mark for packets leaving the interface, set to `null` to disable
	 */
	public function __construct(
		public readonly string $interface,
		string $privateKey,
		int|null $listenPort = null,
		string|IP|null $address = null,
		int|null $mtu = null,
		int|null $fwMark = null
	) {
		$this->privateKey = $privateKey;
		$this->listenPort = $listenPort;
		$this->address = $address;
		$this->mtu = $mtu;
		$this->fwMark = $fwMark;
	}

	/**
	 * Parse JSON string into a Wireguard interface object
	 * @param string $json
	 * @return WireguardInterface
	 */
	public static function fromJson(string $interfaceName, string $json): WireguardInterface
	{
		$parsed = json_decode($json);

		if (!$privateKey = $parsed->private_key ?? null) {
			throw new \Exception("Error parsing WireguardInterface from JSON string, missing property: private_key", 400);
		}

		$wgInterface = new self(
			$interfaceName,
			$privateKey,
			$parsed->listen_port ?? null,
			$parsed->address ?? null,
			$parsed->mtu ?? null,
			$parsed->fwmark ?? null
		);

		foreach ($parsed->peers ?? [] as $peerData) {
			if (!$peerPublicKey = $peerData->public_key ?? null) {
				throw new \Exception("Error parsing WireguardInterface from JSON string, missing property on peer: public_key", 400);
			}

			$peer = new WireguardPeer(
				$peerPublicKey,
				$peerData->preshared_key ?? null,
				$peerData->endpoint_address ?? null,
				$peerData->endpoint_port ?? null,
				$peerData->persistent_keepalive ?? null,
				$peerData->name ?? null
			);

			foreach ($peerData->allowed_ips ?? [] as $allowedIp) {
				$peer->addAllowedIp($allowedIp);
			}

			$wgInterface->addPeer($peer);
		}

		return $wgInterface;
	}

	/**
	 * Parse a Wireguard/wg-quick .conf file into a Wireguard interface object
	 * @param string $filename Name of the file to parse
	 * @param ?string $interfaceName Optional name of the target Wireguard interface, otherwise parses from filename
	 * @return WireguardInterface
	 */
	public static function fromConfFile(string $filename, ?string $interfaceName = null): WireguardInterface
	{
		if (!$interfaceName) {
			if (preg_match('/^wg-([A-Za-z0-9_-]+)/', strtolower(basename($filename)), $matches)) {
				$interfaceName = $matches[1];
			} else {
				throw new \Exception("Could not guess Wireguard interface name from file " . basename($filename));
			}
		}

		return (new WireguardConfigurationParser(file_get_contents($filename), $interfaceName))->parse();
	}

	/**
	 * Represent an interface as string by its interface name
	 * @return string
	 */
	public function __tostring(): string
	{
		return $this->interface;
	}

	/**
	 * Get the public key of this interface
	 * @return string Public key of the interface
	 */
	public function getPublicKey(): string
	{
		// Return existing cached public key of this interface or derive, set and return the public key on the fly
		return $this->_cachedPubKey ?: $this->_cachedPubKey = WireguardHelper::getPublicKey($this->privateKey);
	}

	/**
	 * Get a list of all peers for this interface
	 * @return WireguardPeer[]
	 */
	public function getPeers(): array
	{
		return [...$this->peers];
	}

	/**
	 * Set the list of all peers for this interface, overwriting previous peers
	 * @param WireguardPeer[] $peers Peers to apply
	 * @return void
	 */
	public function setPeers(array $peers): void
	{
		$this->peers = [];
		foreach ($peers as $peer) {
			$this->addPeer($peer);
		}
	}

	/**
	 * Add a peer to this interface
	 * @param WireguardPeer $peer Peer to add
	 * @return void
	 * @throws \Exception when any of the allowed IPs on the peer already exists on another peer
	 */
	public function addPeer(WireguardPeer $peer): void
	{
		if (!array_find($this->peers, fn(WireguardPeer $p) => $p->publicKey === $peer->publicKey)) {
			$this->peers[] = $peer;
		}
	}

	/**
	 * Remove a peer from this interface
	 * @param string|WireguardPeer $peer Peer to remove, can also be specified by its public key
	 * @return void
	 */
	public function removePeer(string|WireguardPeer $peer): void
	{
		$public_key = \is_string($peer) ? $peer : $peer->publicKey;

		if (($index = array_find_key($this->peers, fn(WireguardPeer $p) => $p->publicKey == $public_key)) !== null) {
			array_splice($this->peers, $index, 1);
		}
	}

	/**
	 * Get the interface IP address formatted in CIDR if set
	 * @return ?string
	 */
	public function getIpAddressCidr(): ?string
	{
		if (!$this->address) {
			return null;
		}

		return "{$this->address->getIp()}/{$this->address->getCidr()}";
	}

	/**
	 * Render this interface as a Wireguard configuration
	 * @param bool $wgQuickExtensions If true, includes non-standard wg-quick extensions in the configurations
	 * @return string
	 */
	public function getConfiguration(bool $wgQuickExtensions = false): string
	{
		$config_string = <<<WG
		[Interface]
		PrivateKey = {$this->privateKey}
		WG . "\n";

		if ($this->listenPort) {
			$config_string .= "ListenPort = {$this->listenPort}\n";
		}

		if ($this->fwMark) {
			$config_string .= "FwMark = {$this->fwMark}\n";
		}

		if ($this->address && $wgQuickExtensions) {
			$config_string .= ($wgQuickExtensions ? '' : '# ') . "Address = {$this->getIpAddressCidr()}\n";
		}

		if ($this->mtu) {
			$config_string .= ($wgQuickExtensions ? '' : '# ') . "MTU = {$this->mtu}\n";
		}

		if (\count($this->peers) > 0) {
			$config_string .= "\n";

			foreach ($this->peers as $peer) {
				$config_string .= "{$peer->getConfigurationSection()}\n";
			}
		}

		return trim($config_string) . "\n";
	}

	/**
	 * Apply this configuration to the system
	 * @return void
	 */
	public function apply(): void
	{
		// Ensure this interface has an IP address set before applying it to the system
		if (!$this->address) {
			throw new \Exception("The Wireguard interface configuration about to be applied to the system does not have an IP address configured");
		}

		// Do not run any configuration commands if dry-run is enabled
		if ($_ENV['DRY_RUN'] === 'true') {
			error_log("Debug: Dry-run enabled, not configuring interface {$this->interface}");
			return;
		}

		// Prepare Wireguard tunnel interface
		ThrowingExec::exec(
			"sudo",
			[
				realpath(__DIR__ . "/../../scripts/prepare-tunnel-interface.sh"),
				$this->interface,
				"{$this->getIpAddressCidr()}",
				$this->mtu ?? "",
			]
		);

		// Apply rendered Wireguard configuration
		ThrowingExec::exec(
			"sudo",
			[$_ENV['WG_BINARY'], 'syncconf', $this->interface, '/proc/self/fd/0'],
			stdin: $this->getConfiguration(false)
		);
	}

	/**
	 * Try to get data about a specific Wireguard interface
	 * @param string $interfaceName Linux device name of the Wireguard interface
	 * @return WireguardInterface|null Returns a Wireguard interface object of the specified interface
	 *   or null if no matching interface was found
	 */
	public static function getWireguardInterface(string $interfaceName): self|null
	{
		// Only allow interface names which start with `wg-`
		if (!preg_match('/^wg(\d+|\-[a-z0-9_-]+)/i', $interfaceName)) {
			throw new \Exception("Invalid interface name {$interfaceName} specified");
		}

		// Ensure interface exists
		if (!is_dir("/sys/class/net/{$interfaceName}")) {
			return null;
		}

		// Try and parse IP address and MTU from interface as they are not included in the wg showconf output
		$ip_address = null;
		$mtu = null;
		if ($ip_output = shell_exec("{$_ENV['IP_BINARY']} --json address show " . escapeshellarg($interfaceName))) {
			$interface_ip_detail = json_decode($ip_output)[0];
			$mtu = $interface_ip_detail->mtu ?? null;

			$addr_info = array_filter(
				$interface_ip_detail->addr_info ?? [],
				fn($v) => $v->family == 'inet'
			);

			if (\count($addr_info) === 1 && $addr_info[0]->local ?? null && $addr_info[0]->prefixlen ?? null) {
				$ip_address = "{$addr_info[0]->local}/{$addr_info[0]->prefixlen}";
			}
		}

		$parser = new WireguardConfigurationParser(
			ThrowingExec::exec("sudo", [$_ENV['WG_BINARY'], 'showconf', $interfaceName]),
			$interfaceName
		);

		$interface = $parser->parse();
		$interface->address = $ip_address;
		$interface->mtu = $mtu;

		return $interface;
	}

	/**
	 * Enumerate all active Wireguard interfaces on the system and create Wireguard Interface objects for each
	 * @return WireguardInterface[]
	 */
	public static function getWireguardInterfaces(): array
	{
		$interfaces = [];

		foreach (glob('/sys/class/net/wg*') as $device_dir) {
			$device_name = basename($device_dir);

			// Try to get data about the Wireguard interface and skip over any errors
			try {
				if ($interface = self::getWireguardInterface($device_name)) {
					$interfaces[] = $interface;
				}
			} catch (\Exception $_) {
			}
		}

		return $interfaces;
	}
}