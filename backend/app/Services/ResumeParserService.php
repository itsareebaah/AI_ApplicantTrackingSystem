<?php

namespace App\Services;

use Smalot\PdfParser\Parser as PdfParser;

class ResumeParserService
{
    public function extractText(string $absolutePath, string $extension): string
    {
        $ext = strtolower($extension);

        if ($ext === 'pdf') {
            $parser = new PdfParser();
            $pdf = $parser->parseFile($absolutePath);

            return trim($pdf->getText());
        }

        if (in_array($ext, ['doc', 'docx'], true)) {
            if (!class_exists(\ZipArchive::class)) {
                return '';
            }

            $zip = new \ZipArchive();
            if ($zip->open($absolutePath) !== true) {
                return '';
            }

            $xml = $zip->getFromName('word/document.xml');
            $zip->close();

            if (!$xml) {
                return '';
            }

            $text = strip_tags(str_replace(['</w:p>', '</w:tr>'], "\n", $xml));

            return trim(html_entity_decode($text));
        }

        return '';
    }
}
