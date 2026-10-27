<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use ZipArchive;

class DocxConverterService
{
    /**
     * Convert a .docx file to clean HTML formatted for letter templates
     */
    public static function convertToHtml(string $filePath): string
    {
        if (! file_exists($filePath)) {
            return '';
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return '';
        }

        $xmlIndex = $zip->locateName('word/document.xml');
        if ($xmlIndex === false) {
            $zip->close();
            return '';
        }

        $xmlContent = $zip->getFromIndex($xmlIndex);
        $zip->close();

        if (empty($xmlContent)) {
            return '';
        }

        return static::parseDocumentXml($xmlContent);
    }

    /**
     * Parse word/document.xml content into semantic HTML
     */
    public static function parseDocumentXml(string $xmlContent): string
    {
        $dom = new DOMDocument();
        // Suppress libxml warnings for Word-specific namespace attributes
        libxml_use_internal_errors(true);
        $dom->loadXML($xmlContent, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $bodyList = $xpath->query('//w:body');
        if ($bodyList->length === 0) {
            return '';
        }

        $body = $bodyList->item(0);
        $htmlOutput = '';

        foreach ($body->childNodes as $node) {
            if ($node->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            if ($node->localName === 'p') {
                $htmlOutput .= static::renderParagraph($node, $xpath);
            } elseif ($node->localName === 'tbl') {
                $htmlOutput .= static::renderTable($node, $xpath);
            }
        }

        // Post-process: Stitch any split tags like { nama } or { + nama + }
        $htmlOutput = preg_replace_callback('/\{([^{}]+)\}/u', function ($matches) {
            $inner = trim(strip_tags($matches[1]));
            return '{' . $inner . '}';
        }, $htmlOutput);

        return trim($htmlOutput);
    }

    /**
     * Render a <w:p> paragraph node into HTML <p>
     */
    protected static function renderParagraph(DOMNode $pNode, DOMXPath $xpath): string
    {
        // 1. Check Alignment
        $align = 'left';
        $jcNodes = $xpath->query('w:pPr/w:jc/@w:val', $pNode);
        if ($jcNodes->length > 0) {
            $val = strtolower($jcNodes->item(0)->nodeValue);
            if ($val === 'center') {
                $align = 'center';
            } elseif ($val === 'right') {
                $align = 'right';
            } elseif ($val === 'both') {
                $align = 'justify';
            }
        }

        // 2. Check heading / style
        $styleVal = '';
        $styleNodes = $xpath->query('w:pPr/w:pStyle/@w:val', $pNode);
        if ($styleNodes->length > 0) {
            $styleVal = strtolower($styleNodes->item(0)->nodeValue);
        }

        // 3. Render Run children (<w:r>)
        $paragraphHtml = '';
        foreach ($pNode->childNodes as $child) {
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            if ($child->localName === 'r') {
                $paragraphHtml .= static::renderRun($child, $xpath);
            } elseif ($child->localName === 'hyperlink') {
                $linkText = '';
                foreach ($child->childNodes as $sub) {
                    if ($sub->localName === 'r') {
                        $linkText .= static::renderRun($sub, $xpath);
                    }
                }
                $paragraphHtml .= $linkText;
            }
        }

        if (trim(strip_tags($paragraphHtml)) === '') {
            return "<p><br></p>\n";
        }

        $styleAttr = '';
        if ($align !== 'left') {
            $styleAttr .= "text-align: {$align}; ";
        }

        if (str_contains($styleVal, 'heading 1') || str_contains($styleVal, 'judul 1')) {
            return "<h1" . ($styleAttr ? " style=\"{$styleAttr}\"" : '') . ">{$paragraphHtml}</h1>\n";
        } elseif (str_contains($styleVal, 'heading 2') || str_contains($styleVal, 'judul 2')) {
            return "<h2" . ($styleAttr ? " style=\"{$styleAttr}\"" : '') . ">{$paragraphHtml}</h2>\n";
        }

        return "<p" . ($styleAttr ? " style=\"{$styleAttr}\"" : '') . ">{$paragraphHtml}</p>\n";
    }

    /**
     * Render a <w:r> run node into formatted text
     */
    protected static function renderRun(DOMNode $rNode, DOMXPath $xpath): string
    {
        $text = '';
        $isBold = false;
        $isItalic = false;
        $isUnderline = false;
        $isStrike = false;

        // Check formatting in <w:rPr>
        $rPr = $xpath->query('w:rPr', $rNode)->item(0);
        if ($rPr) {
            if ($xpath->query('w:b', $rPr)->length > 0) {
                $isBold = true;
            }
            if ($xpath->query('w:i', $rPr)->length > 0) {
                $isItalic = true;
            }
            if ($xpath->query('w:u', $rPr)->length > 0) {
                $isUnderline = true;
            }
            if ($xpath->query('w:strike', $rPr)->length > 0) {
                $isStrike = true;
            }
        }

        // Extract text elements: <w:t>, <w:br>, <w:tab>
        foreach ($rNode->childNodes as $child) {
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            if ($child->localName === 't') {
                $text .= htmlspecialchars($child->nodeValue, ENT_QUOTES, 'UTF-8');
            } elseif ($child->localName === 'br') {
                $text .= '<br>';
            } elseif ($child->localName === 'tab') {
                $text .= '&nbsp;&nbsp;&nbsp;&nbsp;';
            }
        }

        if ($text === '') {
            return '';
        }

        if ($isBold) {
            $text = "<strong>{$text}</strong>";
        }
        if ($isItalic) {
            $text = "<em>{$text}</em>";
        }
        if ($isUnderline) {
            $text = "<u>{$text}</u>";
        }
        if ($isStrike) {
            $text = "<s>{$text}</s>";
        }

        return $text;
    }

    /**
     * Render a <w:tbl> table node into an HTML table
     */
    protected static function renderTable(DOMNode $tblNode, DOMXPath $xpath): string
    {
        $rowsHtml = '';
        $rows = $xpath->query('w:tr', $tblNode);

        foreach ($rows as $row) {
            $cellsHtml = '';
            $cells = $xpath->query('w:tc', $row);

            foreach ($cells as $cell) {
                $cellInner = '';
                $pNodes = $xpath->query('w:p', $cell);
                foreach ($pNodes as $p) {
                    $renderedP = static::renderParagraph($p, $xpath);
                    // If single line inside table cell, strip wrapping <p> for cleaner table layout
                    $renderedP = preg_replace('/^<p[^>]*>(.*?)<\/p>\n?$/s', '$1', $renderedP);
                    $cellInner .= $renderedP;
                }

                $cellsHtml .= "<td style=\"padding: 5px 8px; vertical-align: top;\">{$cellInner}</td>\n";
            }

            $rowsHtml .= "<tr>\n{$cellsHtml}</tr>\n";
        }

        return "<table style=\"width: 100%; border-collapse: collapse; margin-bottom: 15px;\">\n{$rowsHtml}</table>\n";
    }
}
