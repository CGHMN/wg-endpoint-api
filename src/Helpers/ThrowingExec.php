<?php

namespace WgEndpointApi\Helpers;

class ThrowingExec
{
	/**
	 * Execute commands and throw an exception if they fail with any exit code higher than 0
	 * 
	 * @param string $command Base command to execute
	 * @param array<string> $arguments Arguments to escape and pass to the base command
	 * @param ?string $directory Working directory to spawn the process in
	 * @param ?array<string,string> $environment Environment variables to pass to the process
	 * @param ?string $stdin Optional text to pipe into the command via standard input
	 * @return string Standard output of the command
	 */
	public static function exec(
		string $command,
		array $arguments = [],
		?string $directory = null,
		?array $environment = null,
		?string $stdin = null
	): string {
		$process = proc_open(
			[$command, ...$arguments],
			[
				0 => ['pipe', 'r'],
				1 => ['pipe', 'w'],
				2 => ['pipe', 'w']
			],
			$pipes,
			$directory,
			$environment
		);

		if (!\is_resource($process)) {
			throw new \Exception("Failed to open process as a resource");
		}

		if ($stdin !== null) {
			fwrite($pipes[0], $stdin);
			fclose($pipes[0]);
		}

		// Wait until the process closes
		/**
		 * @var array{
		 *   command: string,
		 *   pid: int,
		 *   running: bool,
		 *   signaled: bool,
		 *   stopped: bool,
		 *   exitcode: int,
		 *   cached: bool,
		 *   termsig: int,
		 *   stopsig: int
		 * } $process_status
		 */
		while (($process_status = proc_get_status($process))['running'] == true) {
			usleep(1000 * 50);
		}

		// Get contents of standard output and standard error streams and close all pipes
		$stdout = stream_get_contents($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);

		// Close process
		proc_close($process);

		// Parse exit code
		if ($process_status['exitcode'] > 0) {
			throw new \Exception("Command {$command} exited with error code {$process_status['exitcode']}: {$stderr}");
		}

		return $stdout;
	}
}