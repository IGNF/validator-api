<?php

namespace App\Export;

use App\Entity\Validation;
use Twig\Environment;

/**
 * Generates the printable HTML report of a validation (printed to PDF by the browser).
 */
class HtmlReportWriter
{
    private const LEVEL_ORDER = ['error', 'warning', 'info'];

    public function __construct(
        private readonly Environment $twig,
    ) {}

    /**
     * @return string HTML page
     */
    public function render(Validation $validation): string
    {
        $entries = $validation->getResults() ?? [];

        $hasErrors = (bool) array_filter(
            $entries,
            static fn (array $e): bool => 'error' === strtolower($e['level'] ?? 'error')
        );

        $grouped = [];
        foreach ($entries as $entry) {
            // zip pre-validation errors (file, code, message) have no level
            $grouped[$entry['level'] ?? 'ERROR'][] = $entry;
        }

        uksort($grouped, static fn (string $a, string $b): int => array_search(strtolower($a), self::LEVEL_ORDER) <=> array_search(strtolower($b), self::LEVEL_ORDER));

        return $this->twig->render('report.html.twig', [
            'validation' => $validation,
            'groupedEntries' => $grouped,
            'hasErrors' => $hasErrors,
        ]);
    }
}
