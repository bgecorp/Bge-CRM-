<?php

namespace App\Services;

use DateTimeInterface;
use RuntimeException;
use ZipArchive;

/**
 * Writes a one-sheet Excel (.xlsx) file with a bold, frozen header row and filters.
 *
 * Text is stored as plain text, never as a formula, so a value such as "=HYPERLINK(...)"
 * typed into a customer field cannot run when the file is opened.
 */
class XlsxWriter
{
    private const STYLE_HEADER = 1;
    private const STYLE_DATE = 2;
    private const STYLE_MONEY = 3;

    /**
     * @param  array<int, array{0: string, 1: int, 2?: string}>  $columns  [header, width, type]; type is text, number, money or date
     * @param  iterable<array<int, mixed>>  $rows
     * @return string Path to the written temporary file.
     */
    public function write(string $sheetName, array $columns, iterable $rows): string
    {
        $xml = [];
        $xml[] = $this->row(1, array_map(fn ($c) => [$c[0], 'text', self::STYLE_HEADER], $columns));
        $r = 1;
        foreach ($rows as $values) {
            $r++;
            $cells = [];
            foreach ($columns as $i => $c) {
                $type = $c[2] ?? 'text';
                $cells[] = [$values[$i] ?? null, $type, match ($type) { 'date' => self::STYLE_DATE, 'money' => self::STYLE_MONEY, default => 0 }];
            }
            $xml[] = $this->row($r, $cells);
        }

        $lastCell = $this->column(count($columns)).max($r, 1);
        $widths = '';
        foreach ($columns as $i => $c) {
            $n = $i + 1;
            $widths .= "<col min=\"{$n}\" max=\"{$n}\" width=\"{$c[1]}\" customWidth=\"1\"/>";
        }

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            ."<cols>{$widths}</cols>"
            .'<sheetData>'.implode('', $xml).'</sheetData>'
            ."<autoFilter ref=\"A1:{$lastCell}\"/>"
            .'</worksheet>';

        $name = $this->escape(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', ' ', $sheetName), 0, 31));
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the Excel file.');
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            ."<sheets><sheet name=\"{$name}\" sheetId=\"1\" r:id=\"rId1\"/></sheets>"
            ."<definedNames><definedName name=\"_xlnm._FilterDatabase\" localSheetId=\"0\" hidden=\"1\">'{$name}'!\$A\$1:\${$this->column(count($columns))}\$".max($r, 1).'</definedName></definedNames>'
            .'</workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="2"><numFmt numFmtId="164" formatCode="mmm d, yyyy"/><numFmt numFmtId="165" formatCode="&quot;₱&quot;#,##0.00"/></numFmts>'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF1F3A68"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="4">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'</cellXfs>'
            .'</styleSheet>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();

        return $path;
    }

    /** @param  array<int, array{0: mixed, 1: string, 2: int}>  $cells */
    private function row(int $r, array $cells): string
    {
        $out = "<row r=\"{$r}\">";
        foreach ($cells as $i => [$value, $type, $style]) {
            $ref = $this->column($i + 1).$r;
            $s = $style ? " s=\"{$style}\"" : '';
            if ($value === null || $value === '') {
                $out .= $style ? "<c r=\"{$ref}\"{$s}/>" : '';
            } elseif ($type === 'date') {
                $out .= "<c r=\"{$ref}\"{$s}><v>{$this->serial($value)}</v></c>";
            } elseif (in_array($type, ['number', 'money'], true) && is_numeric($value)) {
                $out .= "<c r=\"{$ref}\"{$s}><v>".(0 + $value).'</v></c>';
            } else {
                $out .= "<c r=\"{$ref}\" t=\"inlineStr\"{$s}><is><t xml:space=\"preserve\">{$this->escape((string) $value)}</t></is></c>";
            }
        }

        return $out.'</row>';
    }

    /** Excel stores a date as the number of days since 30 Dec 1899. */
    private function serial(string|DateTimeInterface $date): int
    {
        $ts = $date instanceof DateTimeInterface ? $date->getTimestamp() : strtotime(substr($date, 0, 10).' 00:00:00 UTC');

        return (int) floor($ts / 86400) + 25569;
    }

    /** 1 = A, 27 = AA. */
    private function column(int $n): string
    {
        $s = '';
        for (; $n > 0; $n = intdiv($n - 1, 26)) {
            $s = chr(65 + ($n - 1) % 26).$s;
        }

        return $s;
    }

    private function escape(string $text): string
    {
        // Control characters are not allowed in Excel's XML.
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $text) ?? '';

        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
