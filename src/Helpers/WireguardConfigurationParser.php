<?php

namespace WgEndpointApi\Helpers;

use WgEndpointApi\Classes\WireguardInterface;
use WgEndpointApi\Classes\WireguardPeer;

class WireguardConfigurationParser
{
	/**
	 * Keeps track of which section the parser is currently parsing, or null if none
	 * @var 'interface'|'peer'|null
	 */
	private string|null $currentSection = null;

	/**
	 * Holds details about the Wireguard interface as it is being parsed
	 * @var array<string, mixed>
	 */
	private array $interfaceData = [];

	/**
	 * Holds details about the Wireguard peer being parsed
	 * @var array<string, mixed>
	 */
	private array $peerData = [];

	/**
	 * Stores the fully parsed Wireguard interface
	 * @var WireguardInterface|null
	 */
	private WireguardInterface|null $interface = null;

	/**
	 * Stores all fully parsed Wireguard peers
	 * @var array<WireguardPeer>
	 */
	private array $peers = [];

	/**
	 * Prepares a new Wireguard configuration parser for parsing
	 * @param string $configurationContent String containing the Wireguard configuration
	 * @param string $targetInterface Name of the network interface device the parsed Wireguard interface will belong to
	 */
	public function __construct(
		public readonly string $configurationContent,
		public readonly string $targetInterface
	) {
	}

	/**
	 * Parse a given line as part of an `[Interface]` section
	 * @param string $line Line to parse
	 * @return void
	 */
	private function parseInterfaceLine(string $line): void
	{
		// Split line into key-value pairs or skip it if no valid key-value pair is found
		if (preg_match('/^(PrivateKey|ListenPort|FwMark|MTU|Address)\s*=\s*(.+)$/i', $line, $matches)) {
			$this->interfaceData[strtolower($matches[1])] = $matches[2];
		}
	}

	/**
	 * Parse a given line as part of a `[Peer]` section
	 * @param string $line Line to parse
	 * @return void
	 */
	private function parsePeerLine(string $line): void
	{
		// Either parse the line as the name comment to give a peer a name, or parse it as a key-value pair
		if (stripos($line, '# Name: ') === 0) {
			$this->peerData['name'] = substr($line, 8);
		} else if (preg_match('/^(PublicKey|PresharedKey|AllowedIPs|Endpoint|PersistentKeepalive)\s*=\s*(.+)$/i', $line, $matches)) {
			// Ignore lines with blank values
			if (!$value = $matches[2]) {
				return;
			}

			switch ($key = strtolower($matches[1])) {
				case 'publickey':
				case 'presharedkey':
				case 'persistentkeepalive':
					$this->peerData[$key] = $value;
					break;
				case 'allowedips':
					// Split allowed IPs into array and add each allowed IP entry individually
					$this->peerData['allowedips'] = array_merge(
						$this->peerData['allowedips'] ?? [],
						preg_split('/\s*,\s*/', $value)
					);

					break;
				case 'endpoint':
					// Endpoint is a combination of either an IPv4 address, an IPv6 address in square brackets or a hostname and the port separated by a colon

					// Remove square brackets around IPv6 address
					$value_no_brackets = str_replace(['[', ']'], '', $value);

					// Find split between host and port
					if (($host_port_split_index = strrpos($value_no_brackets, ':')) === false) {
						throw new \Exception('Endpoint without port found in [Peer] section');
					}

					$host = substr($value_no_brackets, 0, $host_port_split_index);
					$port = substr($value_no_brackets, $host_port_split_index + 1);

					if (!$port = filter_var($port, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]])) {
						throw new \Exception("Invalid endpoint port in {$value} in [Peer] section");
					}

					$this->peerData['endpointaddress'] = $host;
					$this->peerData['endpointport'] = $port;

					break;
			}
		}
	}

	/**
	 * Finish the section that is currently being parsed, if any
	 * @throws \Exception if any required parameters are missing or invalid
	 * @return void
	 */
	private function finishSection(): void
	{
		if ($this->currentSection === 'interface') {
			$this->interface = new WireguardInterface(
				$this->targetInterface,
				($this->interfaceData['privatekey'] ?? null) ?: throw new \Exception("Missing 'PrivateKey' property in [Interface] section"),
				filter_var($this->interfaceData['listenport'] ?? null, FILTER_VALIDATE_INT, ['options' => ['default' => null, 'min_range' => 1, 'max_range' => 65535]]),
				filter_var($this->interfaceData['fwmark'] ?? null, FILTER_VALIDATE_INT, ['options' => ['default' => null, 'min_range' => 1, 'max_range' => 4294967295]])
			);
			$this->interfaceData = [];
		} else if ($this->currentSection === 'peer') {
			$peer = new WireguardPeer(
				($this->peerData['publickey'] ?? null) ?: throw new \Exception("Missing 'PublicKey' property in [Peer] section"),
				($this->peerData['presharedkey'] ?? null) ?: null,
				($this->peerData['endpointaddress'] ?? null) ?: null,
				filter_var($this->peerData['endpointport'] ?? null, FILTER_VALIDATE_INT, ['options' => ['default' => null, 'min_range' => 1, 'max_range' => 65535]]),
				filter_var($this->peerData['persistentkeepalive'] ?? null, FILTER_VALIDATE_INT, ['options' => ['default' => null, 'min_range' => 1]]),
				($this->peerData['name'] ?? null) ?: null
			);
			$peer->setAllowedIps($this->peerData['allowedips'] ?? []);

			$this->peers[] = $peer;
			$this->peerData = [];
		}

		$this->currentSection = null;
	}

	/**
	 * Begin parsing the configuration file to return a Wireguard Interface object
	 * @return WireguardInterface
	 */
	public function parse(): WireguardInterface
	{
		// Split configuration file into non-empty strings
		preg_match_all('/^.+$/m', $this->configurationContent, $lines);

		// Iterate over each line and try to parse it
		foreach ($lines[0] as $line) {
			$line = trim($line);

			if (str_starts_with($line, '[')) {
				// Start a new section

				switch ($lower_line = strtolower($line)) {
					case '[interface]':
					case '[peer]':
						$this->finishSection();
						$this->currentSection = str_replace(['[', ']'], '', $lower_line);
						continue 2;
					default:
						throw new \Exception("Unknown section {$line} in configuration file");
				}
			}

			switch ($this->currentSection) {
				case 'interface':
					$this->parseInterfaceLine($line);
					continue 2;
				case 'peer':
					$this->parsePeerLine($line);
					continue 2;
			}
		}

		// Finish any dangling section
		$this->finishSection();

		// Ensure we have a parsed interface
		if (!$this->interface) {
			throw new \Exception('Did not find a valid [Interface] section in the given configuration');
		}

		// Attach parsed peers to interface and return it
		$this->interface->setPeers($this->peers);
		return $this->interface;
	}
}