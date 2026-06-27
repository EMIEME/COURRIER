<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function __construct(string $environment, bool $debug)
    {
        $this->guardProductionConfiguration($environment);

        parent::__construct($environment, $debug);
    }

    public function getCacheDir(): string
    {
        return $this->getWritableVarDir().'/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return $this->getWritableVarDir().'/log';
    }

    private function getWritableVarDir(): string
    {
        $varDir = $_SERVER['APP_VAR_DIR'] ?? $_ENV['APP_VAR_DIR'] ?? null;

        return $varDir ? rtrim($varDir, '/') : $this->getProjectDir().'/var';
    }

    private function guardProductionConfiguration(string $environment): void
    {
        if ('prod' !== $environment) {
            return;
        }

        $appSecret = trim((string) ($_SERVER['APP_SECRET'] ?? $_ENV['APP_SECRET'] ?? ''));
        if ('' === $appSecret || in_array($appSecret, ['change-this-secret-in-env-local', 'replace-with-output-of-php-random-bytes'], true)) {
            throw new \LogicException('APP_SECRET must be set to a real random value in production.');
        }

        $databaseUrl = (string) ($_SERVER['DATABASE_URL'] ?? $_ENV['DATABASE_URL'] ?? '');
        if ('' === trim($databaseUrl) || str_contains($databaseUrl, 'CHANGE_')) {
            throw new \LogicException('DATABASE_URL must be set to real database credentials in production.');
        }
    }
}
