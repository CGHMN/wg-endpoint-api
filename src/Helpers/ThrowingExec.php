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

		// Destructure pipe array into individual variables
		[$pipe_stdin, $pipe_stdout, $pipe_stderr] = $pipes;

		// Write data to stdin and close pipe
		if ($stdin !== null) {
			fwrite($pipe_stdin, $stdin);
		}
		fclose($pipe_stdin);

		// Disable pipe blocking for the read-loop below
		stream_set_blocking($pipe_stdout, false);
		stream_set_blocking($pipe_stderr, false);

		// Collect stdout and stderr text in variables as their read during the process' lifecycle
		[$stdout, $stderr] = ['', ''];

		// Read pipes to keep them from blocking the underlying process until the process closes
		[$stdout_eof, $stderr_eof] = [false, false];
		while (!$stdout_eof || !$stderr_eof) {
			[$read, $write, $except] = [
				[$pipe_stdout, $pipe_stderr],
				null,
				null
			];

			// Wait for data to become available on stdout or stderr for at most one second
			stream_select($read, $write, $except, 1);

			// Read EOF status of both pipes
			$stdout_eof = feof($pipe_stdout);
			$stderr_eof = feof($pipe_stderr);

			// Read data from both pipes if available
			if (!$stdout_eof) {
				$stdout .= fgets($pipe_stdout);
			}

			if (!$stderr_eof) {
				$stderr .= fgets($pipe_stderr);
			}
		}

		// Close all remaining pipes
		fclose($pipe_stdout);
		fclose($pipe_stderr);

		// Get final process status and close process
		$process_status = proc_get_status($process);
		proc_close($process);

		// Parse exit code
		if ($process_status['exitcode'] > 0) {
			throw new \Exception("Command {$command} exited with error code {$process_status['exitcode']}: {$stderr}");
		}

		return $stdout;
	}
}