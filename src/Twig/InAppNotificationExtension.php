<?php

namespace App\Twig;

use App\Service\InAppNotificationProvider;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class InAppNotificationExtension extends AbstractExtension
{
    public function __construct(private readonly InAppNotificationProvider $notificationProvider)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('app_notifications', [$this->notificationProvider, 'forUser']),
        ];
    }
}
