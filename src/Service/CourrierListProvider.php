<?php

namespace App\Service;

use App\Entity\Courrier;
use App\Entity\ListOption;
use App\Repository\ListOptionRepository;

class CourrierListProvider
{
    public function __construct(private readonly ListOptionRepository $listOptionRepository)
    {
    }

    /**
     * @return array<string, string>
     */
    public function natureChoices(?string $currentValue = null): array
    {
        return $this->choices(ListOption::CATEGORY_NATURE, Courrier::DIRECTIONS, $currentValue);
    }

    /**
     * @return array<string, string>
     */
    public function statusChoices(?string $currentValue = null): array
    {
        return $this->choices(ListOption::CATEGORY_STATUS, Courrier::STATUSES, $currentValue);
    }

    /**
     * @return array<string, string>
     */
    public function localisationChoices(?string $currentValue = null): array
    {
        return $this->choices(ListOption::CATEGORY_LOCALISATION, [], $currentValue);
    }

    /**
     * @return array<string, string>
     */
    public function natureLabels(): array
    {
        return $this->labels(ListOption::CATEGORY_NATURE, array_flip(Courrier::DIRECTIONS));
    }

    /**
     * @return array<string, string>
     */
    public function statusLabels(): array
    {
        return $this->labels(ListOption::CATEGORY_STATUS, array_flip(Courrier::STATUSES));
    }

    public function statusLabel(string $status): string
    {
        $labels = $this->statusLabels();

        return $labels[$status] ?? $status;
    }

    /**
     * @param array<string, string> $fallbackChoices
     *
     * @return array<string, string>
     */
    private function choices(string $category, array $fallbackChoices, ?string $currentValue = null): array
    {
        $choices = [];

        foreach ($this->listOptionRepository->findForCategory($category, true) as $option) {
            $choices[(string) $option->getLabel()] = (string) $option->getValue();
        }

        if (!$choices) {
            $choices = $fallbackChoices;
        }

        if ($currentValue && !in_array($currentValue, $choices, true)) {
            $choices[$currentValue] = $currentValue;
        }

        return $choices;
    }

    /**
     * @param array<string, string> $fallbackLabels value => label
     *
     * @return array<string, string>
     */
    private function labels(string $category, array $fallbackLabels): array
    {
        $labels = $fallbackLabels;

        foreach ($this->listOptionRepository->findForCategory($category) as $option) {
            $labels[(string) $option->getValue()] = (string) $option->getLabel();
        }

        return $labels;
    }
}
