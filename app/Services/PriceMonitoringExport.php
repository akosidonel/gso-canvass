<?php

namespace App\Services;

use App\Models\PriceRecord;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;
use XMLWriter;
use ZipArchive;

class PriceMonitoringExport
{
    private const SPREADSHEET_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    public function create(): string
    {
        $workbook = tempnam(sys_get_temp_dir(), 'price-xlsx-');
        $worksheet = tempnam(sys_get_temp_dir(), 'price-sheet-');
        if ($workbook === false || $worksheet === false) {
            if ($workbook !== false) {
                unlink($workbook);
            }
            if ($worksheet !== false) {
                unlink($worksheet);
            }
            throw new RuntimeException('Could not create export temporary files.');
        }

        try {
            $this->writeWorksheet($worksheet);
            $zip = new ZipArchive;
            if ($zip->open($workbook, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Could not create Excel archive.');
            }
            try {
                foreach ($this->workbookParts() as $name => $xml) {
                    if (! $zip->addFromString($name, $xml)) {
                        throw new RuntimeException('Could not write Excel archive.');
                    }
                }
                if (! $zip->addFile($worksheet, 'xl/worksheets/sheet1.xml')) {
                    throw new RuntimeException('Could not add Excel worksheet.');
                }
            } finally {
                if (! $zip->close()) {
                    throw new RuntimeException('Could not finish Excel archive.');
                }
            }
        } catch (Throwable $exception) {
            unlink($workbook);
            throw $exception;
        } finally {
            unlink($worksheet);
        }

        return $workbook;
    }

    private function writeWorksheet(string $path): void
    {
        $xml = new XMLWriter;
        if (! $xml->openUri($path)) {
            throw new RuntimeException('Could not write Excel worksheet.');
        }
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElementNS(null, 'worksheet', self::SPREADSHEET_NS);
        $xml->startElement('sheetViews');
        $xml->startElement('sheetView');
        $xml->writeAttribute('workbookViewId', '0');
        $xml->startElement('pane');
        $xml->writeAttribute('ySplit', '1');
        $xml->writeAttribute('topLeftCell', 'A2');
        $xml->writeAttribute('activePane', 'bottomLeft');
        $xml->writeAttribute('state', 'frozen');
        $xml->endElement();
        $xml->endElement();
        $xml->endElement();
        $xml->startElement('cols');
        foreach ([30, 12, 12, 55, 16, 28, 24, 28, 40, 28] as $index => $width) {
            $xml->startElement('col');
            $xml->writeAttribute('min', (string) ($index + 1));
            $xml->writeAttribute('max', (string) ($index + 1));
            $xml->writeAttribute('width', (string) $width);
            $xml->writeAttribute('customWidth', '1');
            $xml->endElement();
        }
        $xml->endElement();
        $xml->startElement('sheetData');
        $this->writeRow($xml, 1, array_map(fn ($label) => __($label), PriceRecord::FIELDS), true);
        $row = 1;
        // Match the table's newest-first order, without pagination or search filters.
        foreach (PriceRecord::query()->select(['id', ...array_keys(PriceRecord::FIELDS)])->lazyByIdDesc(1000) as $record) {
            if (++$row > 1048576) {
                throw ValidationException::withMessages(['export' => __("The export exceeds Excel's worksheet row limit.")]);
            }
            $this->writeRow($xml, $row, $record->only(array_keys(PriceRecord::FIELDS)));
            if ($row % 1000 === 0) {
                $xml->flush();
            }
        }
        $xml->endElement();
        $xml->startElement('autoFilter');
        $xml->writeAttribute('ref', 'A1:'.chr(64 + count(PriceRecord::FIELDS)).$row);
        $xml->endElement();
        $xml->endElement();
        $xml->endDocument();
        $xml->flush();
    }

    private function writeRow(XMLWriter $xml, int $number, array $values, bool $header = false): void
    {
        $xml->startElement('row');
        $xml->writeAttribute('r', (string) $number);
        foreach (array_keys(PriceRecord::FIELDS) as $index => $field) {
            $numeric = ! $header && in_array($field, ['qty', 'amount'], true);
            $xml->startElement('c');
            $xml->writeAttribute('r', chr(65 + $index).$number);
            $xml->writeAttribute('s', $header ? '1' : ($numeric ? ($field === 'qty' ? '2' : '3') : '0'));
            if ($numeric) {
                $xml->writeElement('v', (string) $values[$field]);
            } else {
                // Explicit strings preserve leading zeros and never execute spreadsheet formulas.
                $xml->writeAttribute('t', 'inlineStr');
                $xml->startElement('is');
                $xml->startElement('t');
                $xml->writeAttribute('xml:space', 'preserve');
                $xml->text(preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', (string) ($values[$field] ?? '')) ?? '');
                $xml->endElement();
                $xml->endElement();
            }
            $xml->endElement();
        }
        $xml->endElement();
    }

    private function workbookParts(): array
    {
        return [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?>
                <Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
                    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
                    <Default Extension="xml" ContentType="application/xml"/>
                    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
                    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
                    <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
                </Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?>
                <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
                    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
                </Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8"?>
                <workbook xmlns="'.self::SPREADSHEET_NS.'" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
                    <sheets><sheet name="Price Monitoring" sheetId="1" r:id="rId1"/></sheets>
                </workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?>
                <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
                    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
                    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
                </Relationships>',
            'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8"?>
                <styleSheet xmlns="'.self::SPREADSHEET_NS.'">
                    <numFmts count="1"><numFmt numFmtId="164" formatCode="0.000"/></numFmts>
                    <fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>
                    <fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>
                    <borders count="1"><border/></borders>
                    <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
                    <cellXfs count="4">
                        <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>
                        <xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>
                        <xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>
                        <xf numFmtId="2" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>
                    </cellXfs>
                    <cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>
                </styleSheet>',
        ];
    }
}
