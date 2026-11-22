<?php

declare(strict_types=1);

const GITPULL_MAX_COMMAND_OUTPUT_BYTES = 64 * 1024;

/**
 * Build the deliberately small environment inherited by Git subprocesses.
 *
 * env.php populates the PHP process with OAuth credentials, API keys, and the
 * deployment token. Git does not need those values, so do not pass the parent
 * environment through wholesale.
 *
 * @return array<string, string>
 */
function gitpull_process_environment(): array {
    $environment = [
        'PATH' => '/usr/bin:/bin',
        'LC_CTYPE' => 'en_US.UTF-8',
        'LC_ALL' => 'en_US.UTF-8',
        'LANG' => 'en_US.UTF-8',
        'TMPDIR' => '/tmp',
    ];
    return $environment;
}

/**
 * Run Git without a shell and without inheriting application credentials.
 *
 * @param array<int, string> $arguments
 * @param string $repository
 * @return array{output: string, status: int}
 */
function gitpull_run_git(array $arguments, string $repository): array {
    $command = ['/usr/bin/git', '-C', $repository, ...$arguments];
    $output_pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    if ($output_pair === false) {
        return ['output' => 'Unable to create Git command output stream.', 'status' => 1];
    }
    [$output_stream, $child_output] = $output_pair;
    // A quiet fetch should keep running through PHP's default socket timeout.
    stream_set_timeout($output_stream, -1);
    $pipes = [];

    /** @psalm-suppress ForbiddenCode */
    $process = proc_open( // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found
        $command,
        [
            0 => ['file', '/dev/null', 'r'],
            1 => $child_output,
            2 => $child_output,
        ],
        $pipes,
        null,
        gitpull_process_environment()
    );

    fclose($child_output);
    if (!is_resource($process)) {
        fclose($output_stream);
        return ['output' => 'Unable to start Git command.', 'status' => 1];
    }

    $output = '';
    $remaining = GITPULL_MAX_COMMAND_OUTPUT_BYTES;
    $truncated = false;
    $read_failed = false;
    try {
        // Keep draining after the cap so Git can finish without blocking on
        // a full pipe. Only the retained prefix is allocated as one string.
        while (!feof($output_stream)) {
            $chunk = fread($output_stream, 8192);
            if ($chunk === false) {
                $read_failed = true;
                break;
            }
            $length = mb_strlen($chunk, '8bit');
            if ($length > $remaining) {
                $truncated = true;
            }
            if ($remaining > 0) {
                $output .= mb_substr($chunk, 0, $remaining, '8bit');
                $remaining -= min($remaining, $length);
            }
        }
    } finally {
        fclose($output_stream);
        $status = proc_close($process);
    }

    // Strip ASCII and UTF-8 C1 control characters. Preserve normal Git
    // whitespace and leave HTML escaping to the response renderer.
    $output = mb_scrub($output, 'UTF-8');
    $output = preg_replace('~[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]|\xc2[\x80-\x9f]~', '', $output) ?? '';
    if ($truncated) {
        $output .= "\n[Git output truncated]\n";
    }
    if ($read_failed) {
        $output .= "\n[Unable to read all Git output]\n";
    }

    return [
        'output' => $output,
        'status' => $status,
    ];
}
