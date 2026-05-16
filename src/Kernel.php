<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

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
}
