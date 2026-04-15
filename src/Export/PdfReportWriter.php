<?php

namespace App\Export;

use App\Entity\Validation;
use Knp\Snappy\Pdf;
use Twig\Environment;

class PdfReportWriter
{
    public function __construct(
        private readonly Pdf         $snappy,
        private readonly Environment $twig,
    ) {}

    /**
     * @param  Validation $validation
     * @return string  Raw PDF binary content
     */
    public function generate(Validation $validation): string
    {
        $entries = json_decode($validation->getResults());

        $hasErrors = (bool) array_filter(
            $entries,
            static fn(array $e): bool => strtolower($e['level']) === 'error'
        );

        $order  = ['error', 'warning', 'info'];
        $grouped = [];

        foreach ($entries as $entry) {
            $grouped[$entry['level']][] = $entry;
        }

        $html = $this->twig->render('pdfModel.html.twig', [
            'groupedEntries' => $grouped,
            'hasErrors'      => $hasErrors,
        ]);

        return $this->snappy->getOutputFromHtml($html, [
            'encoding'             => 'UTF-8',
            'enable-local-file-access' => true,
            'margin-top'           => '10mm',
            'margin-bottom'        => '10mm',
            'margin-left'          => '12mm',
            'margin-right'         => '12mm',
        ]);
    }
}