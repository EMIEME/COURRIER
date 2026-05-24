<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:database:backup', description: 'Genere une sauvegarde SQL de la base de donnees.')]
class DatabaseBackupCommand extends Command
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dir', null, InputOption::VALUE_REQUIRED, 'Dossier de destination de la sauvegarde')
            ->addOption('filename', null, InputOption::VALUE_REQUIRED, 'Nom du fichier SQL a produire')
            ->addOption('keep-days', null, InputOption::VALUE_REQUIRED, 'Nombre de jours de conservation des anciennes sauvegardes')
            ->addOption('include-routines', null, InputOption::VALUE_NONE, 'Inclure les routines MySQL/MariaDB')
            ->addOption('include-events', null, InputOption::VALUE_NONE, 'Inclure les events MySQL/MariaDB');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $databaseUrl = $this->env('DATABASE_URL');
        if ('' === $databaseUrl) {
            $output->writeln('<error>DATABASE_URL est introuvable.</error>');

            return Command::FAILURE;
        }

        $connection = $this->parseDatabaseUrl($databaseUrl);
        if (null === $connection) {
            $output->writeln('<error>La sauvegarde automatique supporte uniquement les bases MySQL/MariaDB.</error>');

            return Command::FAILURE;
        }

        $mysqldump = $this->resolveMysqlDumpPath();
        if (null === $mysqldump) {
            $output->writeln('<error>mysqldump est introuvable. Configurez MYSQLDUMP_PATH dans .env.local.</error>');

            return Command::FAILURE;
        }

        $backupDir = $this->resolveBackupDir((string) ($input->getOption('dir') ?: $this->env('DATABASE_BACKUP_DIR', 'var/backups/database')));
        $this->ensureBackupDir($backupDir);

        $filename = (string) ($input->getOption('filename') ?: sprintf('%s-%s.sql', $connection['database'], (new \DateTimeImmutable())->format('Ymd-His')));
        $filename = basename($filename);
        if (!str_ends_with($filename, '.sql')) {
            $filename .= '.sql';
        }

        $backupPath = $backupDir.DIRECTORY_SEPARATOR.$filename;
        $command = [
            $mysqldump,
            '--single-transaction',
            '--triggers',
            '--host='.$connection['host'],
            '--port='.$connection['port'],
            '--user='.$connection['user'],
        ];

        if ($input->getOption('include-routines')) {
            $command[] = '--routines';
        }

        if ($input->getOption('include-events')) {
            $command[] = '--events';
        }

        $command[] = $connection['database'];

        $env = $this->buildProcessEnv();
        if ('' !== $connection['password']) {
            $env['MYSQL_PWD'] = $connection['password'];
        }

        $descriptorSpec = [
            1 => ['file', $backupPath, 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($this->escapeCommand($command), $descriptorSpec, $pipes, $this->projectDir, $env);

        if (!is_resource($process)) {
            $output->writeln('<error>Impossible de lancer mysqldump.</error>');

            return Command::FAILURE;
        }

        $errorOutput = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if (0 !== $exitCode) {
            if (is_file($backupPath)) {
                @unlink($backupPath);
            }

            $output->writeln('<error>La sauvegarde a echoue.</error>');
            if ($errorOutput) {
                $output->writeln(trim($errorOutput));
            }

            return Command::FAILURE;
        }

        @chmod($backupPath, 0600);
        $deleted = $this->deleteExpiredBackups($backupDir, (int) ($input->getOption('keep-days') ?: $this->env('DATABASE_BACKUP_RETENTION_DAYS', '30')));

        $output->writeln(sprintf('<info>Sauvegarde creee : %s</info>', $backupPath));
        $output->writeln(sprintf('<info>Taille : %s</info>', $this->formatBytes((int) filesize($backupPath))));
        if ($deleted > 0) {
            $output->writeln(sprintf('<comment>%d ancienne(s) sauvegarde(s) supprimee(s).</comment>', $deleted));
        }

        return Command::SUCCESS;
    }

    /**
     * @return array{host: string, port: int, user: string, password: string, database: string}|null
     */
    private function parseDatabaseUrl(string $databaseUrl): ?array
    {
        $parts = parse_url($databaseUrl);
        if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['mysql', 'mariadb'], true)) {
            return null;
        }

        $database = ltrim((string) ($parts['path'] ?? ''), '/');
        if ('' === $database || empty($parts['user'])) {
            return null;
        }

        return [
            'host' => (string) ($parts['host'] ?? '127.0.0.1'),
            'port' => (int) ($parts['port'] ?? 3306),
            'user' => rawurldecode((string) $parts['user']),
            'password' => rawurldecode((string) ($parts['pass'] ?? '')),
            'database' => $database,
        ];
    }

    private function resolveMysqlDumpPath(): ?string
    {
        $configuredPath = $this->env('MYSQLDUMP_PATH');
        $candidates = array_filter([
            $configuredPath ?: null,
            'mysqldump',
            '/Applications/XAMPP/xamppfiles/bin/mysqldump',
            '/Applications/MAMP/Library/bin/mysqldump',
            '/usr/local/mysql/bin/mysqldump',
            '/opt/homebrew/bin/mysqldump',
        ]);

        foreach ($candidates as $candidate) {
            if (str_contains($candidate, DIRECTORY_SEPARATOR) && is_executable($candidate)) {
                return $candidate;
            }

            if (!str_contains($candidate, DIRECTORY_SEPARATOR)) {
                $resolved = trim((string) shell_exec('command -v '.escapeshellarg($candidate).' 2>/dev/null'));
                if ('' !== $resolved) {
                    return $resolved;
                }
            }
        }

        return null;
    }

    /**
     * @param list<string> $command
     */
    private function escapeCommand(array $command): string
    {
        return implode(' ', array_map('escapeshellarg', $command));
    }

    /**
     * @return array<string, string>
     */
    private function buildProcessEnv(): array
    {
        $env = [];

        foreach ($_ENV + $_SERVER as $name => $value) {
            if (is_scalar($value)) {
                $env[(string) $name] = (string) $value;
            }
        }

        return $env;
    }

    private function resolveBackupDir(string $dir): string
    {
        if (str_starts_with($dir, DIRECTORY_SEPARATOR)) {
            return rtrim($dir, DIRECTORY_SEPARATOR);
        }

        return $this->projectDir.DIRECTORY_SEPARATOR.trim($dir, DIRECTORY_SEPARATOR);
    }

    private function ensureBackupDir(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        @chmod($dir, 0700);
    }

    private function deleteExpiredBackups(string $backupDir, int $keepDays): int
    {
        if ($keepDays <= 0) {
            return 0;
        }

        $deleted = 0;
        $limit = (new \DateTimeImmutable(sprintf('-%d days', $keepDays)))->getTimestamp();

        foreach (glob($backupDir.DIRECTORY_SEPARATOR.'*.sql') ?: [] as $path) {
            if (is_file($path) && filemtime($path) < $limit) {
                @unlink($path);
                ++$deleted;
            }
        }

        return $deleted;
    }

    private function env(string $name, string $default = ''): string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);

        return false === $value || null === $value ? $default : (string) $value;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return sprintf('%.2f Mo', $bytes / 1048576);
        }

        if ($bytes >= 1024) {
            return sprintf('%.2f Ko', $bytes / 1024);
        }

        return sprintf('%d o', $bytes);
    }
}
