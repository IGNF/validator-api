<?php

namespace App\Export;

use App\Entity\Validation;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

/**
 * Generates the PDF report of a validation (HTML rendered by twig, converted by dompdf).
 */
class PdfReportWriter
{
    public function __construct(
        private readonly Environment $twig,
        #[Autowire('%kernel.cache_dir%/dompdf')]
        private readonly string $cacheDir,
    ) {}

    /**
     * @param  Validation $validation
     * @return string  Raw PDF binary content
     */
    public function generate(Validation $validation): string
    {
        $entries = $validation->getResults();

        $hasErrors = (bool) array_filter(
            $entries,
            static fn(array $e): bool => 'error' === strtolower($e['level'] ?? 'error')
        );

        $order  = ['error', 'warning', 'info'];
        $grouped = [];

        foreach ($entries as $entry) {
            // zip pre-validation errors (file, code, message) have no level
            $grouped[$entry['level'] ?? 'ERROR'][] = $entry;
        }

        uksort($grouped, static fn(string $a, string $b): int => array_search(strtolower($a), $order) <=> array_search(strtolower($b), $order));

        $html = $this->twig->render('pdfModel.html.twig', [
            'groupedEntries' => $grouped,
            'hasErrors'      => $hasErrors,
        ]);

        $dompdf = new Dompdf($this->createOptions());
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /**
     * The report only contains inline CSS : no remote resource, no local file, no script.
     */
    private function createOptions(): Options
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0o775, true);
        }

        return (new Options())
            ->setIsRemoteEnabled(false)
            ->setIsPhpEnabled(false)
            ->setIsJavascriptEnabled(false)
            ->setChroot([$this->cacheDir])
            ->setTempDir($this->cacheDir)
            ->setFontCache($this->cacheDir)
            ->setDefaultFont('DejaVu Sans');
    }
}