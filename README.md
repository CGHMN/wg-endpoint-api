# CGHMN Wireguard Endpoint API

An API to configure Wireguard tunnel interfaces on remote nodes with a simple HTTP API call

# Currently supported endpoints

Currently, only `POST /api/v1/interfaces/wg-{interface}/sync` exists an a valid API endpoint.
It is used to synchronize a given Wireguard interface configuration including peers to either
a new or an existing Wireguard tunnel interface on the host the API runs on to add or remove
peers and set interface properties.

Authorization is done with the `X-API-Key` HTTP header, which needs to match the value given to
the environment variable `API_KEY`.

The `wg-{interface}` path component of the API endpoint needs to be replaced by the name of the
Linux network interface, e.g. `wg-server`.

The body is a JSON object with the following structure:

```json
{
	"private_key": "OP79Cnk50B9tEzOrjDcyVq4beQmsLCvCqlrALuWskU0=",
	"listen_port": 51820,
	"address": "172.16.0.1/24",
	"mtu": 1300,
	"fwmark": 1234,
	"peers": [
		{
			"public_key": "BuLiWRk6x5pdnxgozDPOFEt+MLwIW085XJu0Y5/eR08=",
			"endpoint_address": "peer01.example.org",
			"endpoint_port": 51820,
			"persistent_keepalive": 15,
			"name": "Example Peer",
			"allowed_ips": ["172.15.0.2/32", "192.168.2.0/24"]
		}
	]
}
```

However, only the `private_key` attribute is required to be set on the interface itself.  
If peers are specified, for each peer, only the `public_key` property is required.  
All other properties can be `null`ed if not required.

The `Content-Type` header needs to be set to `application/json` by the requesting client.

Below is an example cURL call:

```shell
curl -v \
	-d @data.json \
	-H 'Content-Type: application/json' \
	-H 'X-API-Key: changeme' \
	https://endpoint.local/api/v1/interfaces/wg-server/sync
```

# Server Configuration

To run this API on a given host, you need to have a web server with PHP 8.4 or higher installed that also supports
request rewriting to forward all requests towards `/api/v1` to the `web/index.php` PHP script.

Minimal NGINX vhost configuration example, assuming you cloned this repository to `/opt/wg-endpoint-api`:

```
server {
	listen 80 default_server;
	server_name _;

	index index.html index.php;
	root /opt/wg-endpoint-api/web;

	location / {
		try_files $uri $uri/ /index.php;
	}

	location ~ \.php$ {
		include snippets/fastcgi-php.conf;
		fastcgi_pass unix:/run/php/php8.4-fpm.sock;
	}
}
```

In addition, the user handling the PHP scripts (usually `www-data`) needs to be able to modify network interfaces.  
This is done using `sudo`, so said user needs to be able to call a few commands through `sudo` without a password.

The commands are:

- `/usr/bin/wg *` to configure Wireguard tunnels
- `$PROJECT_ROOT/scripts/prepare-tunnel-interface.sh` to bring up a new tunnel interface and configure IP address and MTU on that interface

This is an example sudoers configuration for user `www-data` which can be dropped into `/etc/sudoers.d/wg-endpoint-api`,
assuming you cloned this repository to `/opt/wg-endpoint-api`:

```
www-data ALL=NOPASSWD: /usr/bin/wg *
www-data ALL=NOPASSWD: /opt/wg-endpoint-api/scripts/prepare-tunnel-interface.sh *
```

If the `wg` binary can be found at a different path than the default `/usr/bin/wg`, adjust the path above in the sudoers file and set the
`WG_BINARY` environment variable in `.env` to the absolute path of your `wg` binary.

# Interfacing with the existing Wireguard Controller as control server

The `bin/cron.php` file can be used to pull configuration changes from the central Wireguard Controller automatically
through cron or systemd timers. It can also be used to bring up the interface at boot to the most up-to-date configuration
as long as the control server is reachable. If not, a cached version of the configuration is attempted to be used, by default
located at `cache/<interface-name>.conf`. To disable writing cache configuraton files, simply set the environment variable
`CONFIGURATION_CACHE_DIR` to an empty string in `.env`.

To enable this behavior, first configure the `CONTROL_SERVER_URI` and `CONTROL_SERVER_AUTHKEY` environment variables in `.env`.  
`CONTROL_SERVER_URI` takes the form of `http://<wg-controller-host>/api/v1`, where `<wg-controller-host>` is replaced by the host
and optionally port of the Wireguard Controller. An example for the current infrastructure as of 2026-09-12 would be
`CONTROL_SERVER_URI=http://100.64.12.2/api/v1`. The `CONTROL_SERVER_AUTHKEY` holds the same API auth key as configured on the
Wireguard Controller in its environment (`.env` file) as the environment variable `API_KEY`.
