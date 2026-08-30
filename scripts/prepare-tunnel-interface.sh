#!/usr/bin/env bash
# This script is called by the PHP backend through sudo to bring up and configure a basic Wireguard tunnel
set -euo pipefail

# Ensure the tunnel interface name starts with wg-
tunnel_name="wg-${1##wg-}"

# Get the tunnel IP address and force it to be /32
tunnel_ip="${2%%/*}/32"

# Set the tunnel MTU or default to a safe 1300 byte MTU
tunnel_mtu="${3:-1300}"

if ! ip link show "${tunnel_name}" &>/dev/null; then
	# Create blank Wireguard interface if it does not already exist
	ip link add "${tunnel_name}" type wireguard
fi

if ! ip address show "${tunnel_name}" | grep -q "${tunnel_ip}"; then
	# Flush existing IPs and assign tunnel IP address to tunnel interface
	ip address flush "${tunnel_name}"
	ip address add "${tunnel_ip}" dev "${tunnel_name}"
fi

# Set MTU and interface up
ip link set dev "${tunnel_name}" mtu "${tunnel_mtu}" up