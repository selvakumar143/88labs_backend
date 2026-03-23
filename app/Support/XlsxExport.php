<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

final class XlsxExport
{
    /**
     * Build an in-memory XLSX file from the provided rows.
     *
     * @param array<int, array<int, string>> $rows
     * @return string
     */
    public static function fromRows(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary file for XLSX export.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to write XLSX export to temporary archive.');
        }

        try {
            $zip->addFromString('[Content_Types].xml', self::contentTypesXml());
            $zip->addFromString('_rels/.rels', self::rootRelationshipsXml());
            $zip->addFromString('xl/workbook.xml', self::workbookXml());
            $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRelationshipsXml());
            $zip->addFromString('xl/worksheets/sheet1.xml', self::worksheetXml($rows));
            $zip->addFromString('xl/styles.xml', self::stylesXml());
            $zip->addFromString('docProps/core.xml', self::corePropertiesXml());
            $zip->addFromString('docProps/app.xml', self::appPropertiesXml());
        } finally {
            $zip->close();
        }

        $content = file_get_contents($path);
        unlink($path);

        if ($content === false) {
            throw new RuntimeException('Failed to read generated XLSX content.');
        }

        return $content;
    }

    private static function worksheetXml(array $rows): string
    {
        $lines = [];
        $rowNumber = 1;

        foreach ($rows as $row) {
            $cells = [];
            $rowValues = array_values($row);

            foreach ($rowValues as $columnIndex => $value) {
                $cellRef = self::columnLetter($columnIndex) . $rowNumber;
                $cells[] = sprintf(
                    '<c r="%s" t="inlineStr"><is><t xml:space="preserve">%s</t></is></c>',
                    $cellRef,
                    self::escapeXml((string) $value)
                );
            }

            $lines[] = '<row r="' . $rowNumber . '">' . implode('', $cells) . '</row>';
            $rowNumber++;
        }

        $sheetData = implode("\n", $lines);

        return sprintf(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>
    %s
  </sheetData>
</worksheet>
XML,
            $sheetData
        );
    }

    private static function contentTypesXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
</Types>
XML;
    }

    private static function rootRelationshipsXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML;
    }

    private static function workbookXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <bookViews>
    <workbookView xWindow="0" yWindow="0"/>
  </bookViews>
  <sheets>
    <sheet name="Sheet1" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>
XML;
    }

    private static function workbookRelationshipsXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML;
    }

    private static function stylesXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="1">
    <font>
      <sz val="11"/>
      <color theme="1"/>
      <name val="Calibri"/>
      <family val="2"/>
    </font>
  </fonts>
  <fills count="1">
    <fill>
      <patternFill patternType="none"/>
    </fill>
  </fills>
  <borders count="1">
    <border>
      <left/>
      <right/>
      <top/>
      <bottom/>
      <diagonal/>
    </border>
  </borders>
  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>
  <cellXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
  </cellXfs>
</styleSheet>
XML;
    }

    private static function corePropertiesXml(): string
    {
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <dc:creator>88laps</dc:creator>
  <cp:lastModifiedBy>88laps</cp:lastModifiedBy>
  <dcterms:created xsi:type="dcterms:W3CDTF">{$timestamp}</dcterms:created>
  <dcterms:modified xsi:type="dcterms:W3CDTF">{$timestamp}</dcterms:modified>
</cp:coreProperties>
XML;
    }

    private static function appPropertiesXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
  <Application>88laps</Application>
</Properties>
XML;
    }

    private static function columnLetter(int $index): string
    {
        $index++; // convert zero-based index to 1-based

        $letters = '';
        while ($index > 0) {
            $remainder = ($index - 1) % 26;
            $letters = chr(65 + $remainder) . $letters;
            $index = (int) (($index - 1) / 26);
        }

        return $letters;
    }

    private static function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
