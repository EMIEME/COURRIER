<?php

namespace App\Service;

use App\Entity\Courrier;

class CourrierExportService
{
    /**
     * @param list<Courrier> $courriers
     * @param array<string, string> $directionLabels
     * @param array<string, string> $statusLabels
     */
    public function buildExcel(array $courriers, array $directionLabels, array $statusLabels): string
    {
        $columns = $this->columns();
        $rows = $this->buildRows($courriers, $directionLabels, $statusLabels);

        $xml = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<?mso-application progid="Excel.Sheet"?>',
            '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">',
            '<Styles>',
            '<Style ss:ID="header"><Font ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#115F6C" ss:Pattern="Solid"/></Style>',
            '<Style ss:ID="date"><NumberFormat ss:Format="dd/mm/yyyy"/></Style>',
            '</Styles>',
            '<Worksheet ss:Name="Registre">',
            '<Table>',
        ];

        foreach ($columns as $width) {
            $xml[] = sprintf('<Column ss:Width="%d"/>', $width);
        }

        $xml[] = '<Row>';
        foreach (array_keys($columns) as $label) {
            $xml[] = sprintf('<Cell ss:StyleID="header"><Data ss:Type="String">%s</Data></Cell>', $this->xml($label));
        }
        $xml[] = '</Row>';

        foreach ($rows as $row) {
            $xml[] = '<Row>';
            foreach ($row as $value) {
                $xml[] = sprintf('<Cell><Data ss:Type="String">%s</Data></Cell>', $this->xml($value));
            }
            $xml[] = '</Row>';
        }

        $xml[] = '</Table>';
        $xml[] = '</Worksheet>';
        $xml[] = '</Workbook>';

        return implode("\n", $xml);
    }

    /**
     * @param list<Courrier> $courriers
     * @param array<string, string> $directionLabels
     * @param array<string, string> $statusLabels
     */
    public function buildPdf(array $courriers, array $directionLabels, array $statusLabels): string
    {
        $columns = [
            'Date' => 52,
            'Référence' => 78,
            'Nature' => 66,
            'Objet' => 182,
            'Interlocuteur' => 112,
            'Statut' => 62,
            'Imputation' => 124,
            'Localisation' => 80,
        ];

        $rows = $this->buildPdfRows($courriers, $directionLabels, $statusLabels);
        $pages = [];
        $content = $this->newPdfPageContent();
        $y = 542;

        $this->drawText($content, 28, 562, 'Registre des courriers', 'F2', 17);
        $this->drawText($content, 28, 546, sprintf('Export généré le %s - %d courrier(s)', (new \DateTimeImmutable())->format('d/m/Y H:i'), count($courriers)), 'F1', 8);
        $this->drawTableHeader($content, $columns, $y);
        $y -= 24;

        foreach ($rows as $row) {
            $wrapped = [];
            $height = 20;
            $index = 0;

            foreach ($columns as $width) {
                $lines = $this->wrapPdfText((string) ($row[$index] ?? ''), $width, 7, 3);
                $wrapped[] = $lines;
                $height = max($height, 10 + (count($lines) * 9));
                ++$index;
            }

            if ($y - $height < 28) {
                $pages[] = $this->finishPdfPageContent($content);
                $content = $this->newPdfPageContent();
                $y = 552;
                $this->drawTableHeader($content, $columns, $y);
                $y -= 24;
            }

            $this->drawPdfRow($content, $columns, $wrapped, $y, $height);
            $y -= $height;
        }

        if (!$rows) {
            $this->drawText($content, 36, $y - 2, 'Aucun résultat pour les filtres appliqués.', 'F1', 9);
        }

        $pages[] = $this->finishPdfPageContent($content);

        return $this->assemblePdf($pages);
    }

    /**
     * @return array<string, int>
     */
    private function columns(): array
    {
        return [
            'Date' => 70,
            'Référence' => 110,
            'Nature' => 90,
            'Objet' => 260,
            'Interlocuteur' => 170,
            'Statut' => 90,
            'Imputation' => 170,
            'Localisation' => 110,
            'Réponse au courrier' => 150,
            'Échéance réponse' => 110,
            'Fichier joint' => 90,
        ];
    }

    /**
     * @param list<Courrier> $courriers
     * @param array<string, string> $directionLabels
     * @param array<string, string> $statusLabels
     *
     * @return list<list<string>>
     */
    private function buildRows(array $courriers, array $directionLabels, array $statusLabels): array
    {
        return array_map(
            fn (Courrier $courrier): array => [
                $courrier->getMailDate()?->format('d/m/Y') ?? '',
                (string) $courrier->getReference(),
                $directionLabels[$courrier->getDirection()] ?? $courrier->getDirectionLabel(),
                (string) $courrier->getSubject(),
                $courrier->getInterlocuteurLabel(),
                $statusLabels[$courrier->getStatus()] ?? $courrier->getStatusLabel(),
                $courrier->getAssignedToLabel(),
                (string) $courrier->getLocalisation(),
                $courrier->getReplyTo()?->getReference() ?? '',
                $courrier->getResponseDueAt()?->format('d/m/Y') ?? '',
                $courrier->getAttachmentFilename() ? 'Oui' : 'Non',
            ],
            $courriers
        );
    }

    /**
     * @param list<Courrier> $courriers
     * @param array<string, string> $directionLabels
     * @param array<string, string> $statusLabels
     *
     * @return list<list<string>>
     */
    private function buildPdfRows(array $courriers, array $directionLabels, array $statusLabels): array
    {
        return array_map(
            fn (Courrier $courrier): array => [
                    $courrier->getMailDate()?->format('d/m/Y') ?? '',
                    (string) $courrier->getReference(),
                    $directionLabels[$courrier->getDirection()] ?? $courrier->getDirectionLabel(),
                    (string) $courrier->getSubject(),
                    $courrier->getInterlocuteurLabel(),
                    $statusLabels[$courrier->getStatus()] ?? $courrier->getStatusLabel(),
                    $courrier->getAssignedToLabel(),
                    (string) $courrier->getLocalisation(),
                ],
            $courriers
        );
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    /**
     * @return list<string>
     */
    private function wrapPdfText(string $text, int $width, int $fontSize, int $maxLines): array
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');

        if ('' === $text) {
            return [''];
        }

        $maxChars = max(8, (int) floor($width / ($fontSize * 0.5)));
        $words = explode(' ', $text);
        $lines = [];
        $line = '';

        foreach ($words as $word) {
            $candidate = '' === $line ? $word : $line.' '.$word;

            if (mb_strlen($candidate) <= $maxChars) {
                $line = $candidate;
                continue;
            }

            if ('' !== $line) {
                $lines[] = $line;
            }

            $line = mb_strlen($word) > $maxChars ? mb_substr($word, 0, $maxChars - 1).'…' : $word;

            if (count($lines) >= $maxLines) {
                break;
            }
        }

        if (count($lines) < $maxLines && '' !== $line) {
            $lines[] = $line;
        }

        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
        }

        if ($lines && mb_strlen($text) > mb_strlen(implode(' ', $lines))) {
            $lastIndex = count($lines) - 1;
            $lines[$lastIndex] = rtrim(mb_substr($lines[$lastIndex], 0, max(1, $maxChars - 1))).'…';
        }

        return $lines ?: [''];
    }

    /**
     * @return list<string>
     */
    private function newPdfPageContent(): array
    {
        return [
            'q',
            '0.08 0.14 0.18 rg',
        ];
    }

    /**
     * @param list<string> $content
     */
    private function finishPdfPageContent(array $content): string
    {
        $content[] = 'Q';

        return implode("\n", $content);
    }

    /**
     * @param list<string> $content
     */
    private function drawTableHeader(array &$content, array $columns, float $y): void
    {
        $x = 28;
        $height = 21;
        $totalWidth = array_sum($columns);

        $content[] = sprintf('0.07 0.37 0.42 rg %.2F %.2F %.2F %.2F re f', $x, $y - 6, $totalWidth, $height);
        $content[] = '1 1 1 rg';

        foreach ($columns as $label => $width) {
            $this->drawText($content, $x + 4, $y + 2, $label, 'F2', 7);
            $x += $width;
        }

        $content[] = '0.08 0.14 0.18 rg';
    }

    /**
     * @param list<string> $content
     * @param array<string, int> $columns
     * @param list<list<string>> $wrapped
     */
    private function drawPdfRow(array &$content, array $columns, array $wrapped, float $y, float $height): void
    {
        $x = 28;
        $totalWidth = array_sum($columns);

        $content[] = sprintf('0.83 0.88 0.90 RG 0.35 w %.2F %.2F m %.2F %.2F l S', $x, $y - $height + 2, $x + $totalWidth, $y - $height + 2);
        $content[] = '0.08 0.14 0.18 rg';

        $columnIndex = 0;
        foreach ($columns as $width) {
            $lineY = $y - 8;
            foreach ($wrapped[$columnIndex] as $line) {
                $this->drawText($content, $x + 4, $lineY, $line, 'F1', 7);
                $lineY -= 9;
            }
            $x += $width;
            ++$columnIndex;
        }
    }

    /**
     * @param list<string> $content
     */
    private function drawText(array &$content, float $x, float $y, string $text, string $font, int $size): void
    {
        $content[] = sprintf('BT /%s %d Tf %.2F %.2F Td (%s) Tj ET', $font, $size, $x, $y, $this->pdfText($text));
    }

    private function pdfText(string $text): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        $encoded = false === $encoded ? $text : $encoded;
        $encoded = str_replace(["\\", '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $encoded);

        return $encoded;
    }

    /**
     * @param list<string> $pageStreams
     */
    private function assemblePdf(array $pageStreams): string
    {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];

        $pageIds = [];
        $nextId = 5;

        foreach ($pageStreams as $stream) {
            $pageId = $nextId++;
            $contentId = $nextId++;
            $pageIds[] = $pageId.' 0 R';
            $objects[$pageId] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>', $contentId);
            $objects[$contentId] = sprintf("<< /Length %d >>\nstream\n%s\nendstream", strlen($stream), $stream);
        }

        $objects[2] = sprintf('<< /Type /Pages /Kids [%s] /Count %d >>', implode(' ', $pageIds), count($pageIds));
        ksort($objects);

        $pdf = "%PDF-1.4\n%".chr(226).chr(227).chr(207).chr(211)."\n";
        $offsets = [0 => 0];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= sprintf("%d 0 obj\n%s\nendobj\n", $id, $body);
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($id = 1; $id <= count($objects); ++$id) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }

        $pdf .= "trailer\n";
        $pdf .= sprintf("<< /Size %d /Root 1 0 R >>\n", count($objects) + 1);
        $pdf .= "startxref\n".$xrefOffset."\n%%EOF";

        return $pdf;
    }
}
