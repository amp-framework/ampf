<?php

declare(strict_types=1);

namespace ampf\Tests\Support;

use RuntimeException;

/**
 * PHP's built-in web server on the loopback over a document root, as a browser meets it: its output and its error log
 * (where error_log() writes) go to a file of the test's own in the system's temporary directory, and php.ini settings
 * can be given on its command line. stop() ends the server and removes the file.
 */
final class BuiltInServer
{
    /**
     * @var resource
     */
    private $process;

    private string $address;

    private string $log;

    /**
     * @param array<string, string> $settings php.ini settings of the server's PHP (`session.save_path` => …)
     *
     * @throws RuntimeException when the server does not start
     */
    public function __construct(string $documentRoot, array $settings = [])
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0')
            ?: throw new RuntimeException('There is no free port on the loopback.');
        $this->address = (string)stream_socket_get_name($socket, false);
        fclose($socket);
        $this->log = (string)tempnam(sys_get_temp_dir(), 'ampf-server-');

        $command = [PHP_BINARY];

        foreach ($settings as $name => $value) {
            $command[] = '-d';
            $command[] = $name . '=' . $value;
        }

        $process = proc_open(
            [...$command, '-S', $this->address, '-t', $documentRoot],
            [1 => ['file', $this->log, 'a'], 2 => ['file', $this->log, 'a']],
            $pipes,
        );

        if (!is_resource($process)) {
            unlink($this->log);

            throw new RuntimeException('The built-in server did not start.');
        }

        $this->process = $process;

        // The server listens once it is up
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $connection = @stream_socket_client('tcp://' . $this->address, $errorCode, $errorMessage, 0.1);

            if ($connection !== false) {
                fclose($connection);

                return;
            }

            usleep(50_000);
        }

        $log = $this->log();
        $this->stop();

        throw new RuntimeException('The built-in server did not start: ' . $log);
    }

    /**
     * What the server printed so far: its request lines and everything written to the error log.
     */
    public function log(): string
    {
        return (string)file_get_contents($this->log);
    }

    /**
     * A request as a browser sends it; the redirects are not followed.
     *
     * @param list<string> $headers
     *
     * @return array{status: int, headers: list<string>, body: string}
     */
    public function request(string $method, string $path, array $headers = [], ?string $body = null): array
    {
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => $headers,
            'content' => $body ?? '',
            'follow_location' => 0,
            'ignore_errors' => true,
        ]]);
        $content = file_get_contents('http://' . $this->address . $path, false, $context);
        $lines = http_get_last_response_headers() ?? [];

        if (!is_string($content) || preg_match('/^HTTP\/1\.[01] (\d{3})/', $lines[0] ?? '', $status) !== 1) {
            throw new RuntimeException('The built-in server gave no response: ' . $this->log());
        }

        return ['status' => (int)$status[1], 'headers' => array_slice($lines, 1), 'body' => $content];
    }

    /**
     * The values of the response's headers of the name, in their order.
     *
     * @param array{status: int, headers: list<string>, body: string} $response
     *
     * @return list<string>
     */
    public function headers(array $response, string $name): array
    {
        $values = [];

        foreach ($response['headers'] as $line) {
            [$lineName, $lineValue] = explode(':', $line, 2) + ['', ''];

            if (strcasecmp($lineName, $name) === 0) {
                $values[] = trim($lineValue);
            }
        }

        return $values;
    }

    /** Ends the server and removes its log. */
    public function stop(): void
    {
        proc_terminate($this->process);
        proc_close($this->process);
        unlink($this->log);
    }
}
