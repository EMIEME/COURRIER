#!/usr/bin/env php
<?php

declare(strict_types=1);

if (2 !== $argc) {
    fwrite(STDERR, "Usage: php deploy/import-sql-dump.php /path/to/dump.sql\n");
    exit(1);
}

$dumpPath = $argv[1];
if (!is_file($dumpPath) || !is_readable($dumpPath)) {
    fwrite(STDERR, "Dump file is not readable: {$dumpPath}\n");
    exit(1);
}

$projectDir = dirname(__DIR__);
$envLocalPath = $projectDir.'/.env.local';
if (!is_file($envLocalPath) || !is_readable($envLocalPath)) {
    fwrite(STDERR, ".env.local is not readable at {$envLocalPath}\n");
    exit(1);
}

$databaseUrl = null;
foreach (file($envLocalPath, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
    $line = trim($line);
    if ('' === $line || str_starts_with($line, '#')) {
        continue;
    }

    if (str_starts_with($line, 'DATABASE_URL=')) {
        $value = trim(substr($line, strlen('DATABASE_URL=')));
        if ('' !== $value && in_array($value[0], ['"', "'"], true)) {
            $value = substr($value, 1, -1);
        }

        $databaseUrl = $value;
        break;
    }
}

if (null === $databaseUrl) {
    fwrite(STDERR, "DATABASE_URL is missing from .env.local\n");
    exit(1);
}

$parts = parse_url($databaseUrl);
if (false === $parts) {
    fwrite(STDERR, "DATABASE_URL is invalid\n");
    exit(1);
}

$database = ltrim($parts['path'] ?? '', '/');
if ('' === $database) {
    fwrite(STDERR, "DATABASE_URL does not include a database name\n");
    exit(1);
}

$client = trim((string) shell_exec('command -v mariadb 2>/dev/null || command -v mysql 2>/dev/null'));
if ('' === $client) {
    fwrite(STDERR, "Neither mariadb nor mysql client was found in PATH\n");
    exit(1);
}

$command = [
    $client,
    '--host',
    $parts['host'] ?? '127.0.0.1',
    '--port',
    (string) ($parts['port'] ?? 3306),
    '--user',
    rawurldecode($parts['user'] ?? ''),
    '--binary-mode=1',
    $database,
];

$env = $_ENV;
$env['MYSQL_PWD'] = rawurldecode($parts['pass'] ?? '');

$descriptorSpec = [
    0 => ['file', $dumpPath, 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$process = proc_open($command, $descriptorSpec, $pipes, $projectDir, $env);
if (!is_resource($process)) {
    fwrite(STDERR, "Failed to start database client\n");
    exit(1);
}

$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);

$exitCode = proc_close($process);
if (0 !== $exitCode) {
    if ('' !== $stdout) {
        fwrite(STDOUT, $stdout);
    }
    if ('' !== $stderr) {
        fwrite(STDERR, $stderr);
    }
    fwrite(STDERR, "Import failed with exit code {$exitCode}\n");
    exit($exitCode);
}

echo "Imported {$dumpPath} into {$database}\n";
