<?php
declare(strict_types=1);

namespace ITAM\Services;

use ITAM\Core\Lang;

/**
 * One table, several download formats: CSV (opens in Excel, round-trips with import), Excel .xlsx
 * (written directly as Office Open XML, no library needed), PDF (rendered by Edge/Chrome, Arabic included)
 * and JSON for integrations.
 */
final class Exporter
{
    public const FORMATS = ['csv', 'xlsx', 'pdf', 'json'];
    private const PDF_ROW_LIMIT = 3000;

    public static function format(?string $f): string
    {
        return in_array($f, self::FORMATS, true) ? $f : 'csv';
    }

    /**
     * Send the table as a download and stop.
     * @param list<string> $headers
     * @param list<list<scalar|null>> $rows values in header order
     */
    public static function download(string $format, string $basename, string $title, array $headers, array $rows): never
    {
        $file = $basename . '-' . date('Ymd-His');
        switch (self::format($format)) {
            case 'json':
                self::send('application/json; charset=utf-8', "$file.json", json_encode([
                    'title' => $title, 'generated_at' => date('c'), 'count' => count($rows),
                    'rows' => array_map(fn ($r) => array_combine($headers, array_map(fn ($v) => $v, $r)), $rows),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            case 'xlsx':
                self::send('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', "$file.xlsx", self::xlsx($title, $headers, $rows));
            case 'pdf':
                $pdf = Pdf::fromHtml(self::html($title, $headers, array_slice($rows, 0, self::PDF_ROW_LIMIT), count($rows)), ITAM_STORAGE . '/reports/export-' . bin2hex(random_bytes(6)) . '.pdf');
                if (!$pdf) {
                    flash('error', t('export.pdf_unavailable'));
                    back();
                }
                $bytes = (string) file_get_contents($pdf);
                @unlink($pdf);
                self::send('application/pdf', "$file.pdf", $bytes);
            default:
                self::send('text/csv; charset=utf-8', "$file.csv", self::csv($headers, $rows));
        }
    }

    public static function csv(array $headers, array $rows): string
    {
        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF");        // BOM: Excel then reads Arabic correctly
        fputcsv($out, $headers, escape: '');
        foreach ($rows as $r) {
            fputcsv($out, array_map('csv_safe', $r), escape: '');
        }
        rewind($out);
        return (string) stream_get_contents($out);
    }

    /** Minimal Office Open XML workbook: bold frozen header, filter, sized columns, right-to-left in Arabic. */
    public static function xlsx(string $title, array $headers, array $rows): string
    {
        $esc = fn ($s) => htmlspecialchars((string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $col = function (int $i): string {
            $s = '';
            for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
                $s = chr(65 + ($i - 1) % 26) . $s;
            }
            return $s;
        };
        $cell = function (int $c, int $r, mixed $v, bool $head = false) use ($esc, $col): string {
            $ref = $col($c) . $r;
            if (!$head && (is_int($v) || is_float($v) || (is_string($v) && preg_match('/^-?\d{1,15}(\.\d+)?$/', $v) && !preg_match('/^0\d/', $v)))) {
                return "<c r=\"$ref\"><v>$v</v></c>";
            }
            if ($v === null || $v === '') {
                return '';
            }
            return "<c r=\"$ref\" t=\"inlineStr\"" . ($head ? ' s="1"' : '') . '><is><t xml:space="preserve">' . $esc($v) . '</t></is></c>';
        };
        $widths = array_map(fn ($h) => mb_strlen((string) $h), $headers);
        $xmlRows = '<row r="1">' . implode('', array_map(fn ($h, $i) => $cell($i, 1, $h, true), $headers, array_keys($headers))) . '</row>';
        foreach ($rows as $n => $r) {
            $line = '';
            foreach (array_values($r) as $i => $v) {
                $line .= $cell($i, $n + 2, $v);
                $widths[$i] = max($widths[$i] ?? 0, mb_strlen((string) $v));
            }
            $xmlRows .= '<row r="' . ($n + 2) . '">' . $line . '</row>';
        }
        $cols = implode('', array_map(fn ($w, $i) => '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . min(60, max(8, $w + 2)) . '" customWidth="1"/>', $widths, array_keys($widths)));
        $last = $col(max(0, count($headers) - 1)) . (count($rows) + 1);
        $rtl = Lang::dir() === 'rtl' ? ' rightToLeft="1"' : '';
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"' . $rtl . '><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<cols>' . $cols . '</cols><sheetData>' . $xmlRows . '</sheetData>'
            . '<autoFilter ref="A1:' . $last . '"/></worksheet>';
        $sheetName = $esc(mb_substr(preg_replace('#[\\\\/?*\[\]:]#u', ' ', $title) ?: 'Export', 0, 31));
        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
                . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<sheets><sheet name="' . $sheetName . '" sheetId="1" r:id="rId1"/></sheets>'
                . '<definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">\'' . $sheetName . '\'!$A$1:$' . preg_replace('/\d+$/', '', $last) . '$' . (count($rows) + 1) . '</definedName></definedNames></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
                . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF' . strtoupper(ltrim(\ITAM\Services\Themes::primary(), '#')) . '"/><bgColor indexed="64"/></patternFill></fill></fills>'
                . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
                . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
                . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
                . '</styleSheet>',
            'xl/worksheets/sheet1.xml' => $sheet,
        ];
        $tmp = tempnam(sys_get_temp_dir(), 'itamx');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);
        return $bytes;
    }

    /** Landscape A4 table for the PDF renderer (self-contained, light). */
    public static function html(string $title, array $headers, array $rows, int $total): string
    {
        $e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $brand = \ITAM\Services\Themes::primary();
        $body = '';
        foreach ($rows as $r) {
            $body .= '<tr>' . implode('', array_map(fn ($v) => '<td dir="auto">' . $e($v) . '</td>', $r)) . '</tr>';
        }
        $note = $total > count($rows) ? '<p class="note">' . $e(t('export.pdf_limited', ['n' => fmt_num(count($rows)), 'total' => fmt_num($total)])) . '</p>' : '';
        return '<!doctype html><html lang="' . $e(Lang::locale()) . '" dir="' . $e(Lang::dir()) . '"><head><meta charset="utf-8"><title>' . $e($title) . '</title>'
            . '<link href="https://fonts.googleapis.com/css2?family=Alexandria:wght@400;600;700&display=swap" rel="stylesheet">'
            . '<style>@page{size:A4 landscape;margin:10mm}*{box-sizing:border-box}html{-webkit-print-color-adjust:exact;print-color-adjust:exact}'
            . 'body{margin:0;font:400 8.5px/1.4 "Alexandria","Segoe UI",Tahoma,sans-serif;color:#0f1b33;background:#fff}'
            . 'header{display:flex;justify-content:space-between;align-items:flex-end;border-bottom:2px solid ' . $brand . ';padding-bottom:6px;margin-bottom:8px}'
            . 'h1{margin:0;font-size:14px}header p{margin:0;color:#5b6b86}'
            . 'table{width:100%;border-collapse:collapse}thead{display:table-header-group}th{background:' . $brand . ';color:#fff;font-weight:600;text-align:start;padding:4px 5px;white-space:nowrap}'
            . 'td{padding:3px 5px;border-bottom:1px solid #e3e8f0;vertical-align:top}tr:nth-child(even) td{background:#f6f8fb}tr{break-inside:avoid}.note{color:#b7791f;margin:6px 0 0}</style></head><body>'
            . '<header><div><h1>' . $e($title) . '</h1><p>' . $e(app_name()) . ' · ' . $e(vendor_name()) . '</p></div>'
            . '<p>' . $e(t('export.generated', ['t' => fmt_date(now(), true), 'n' => fmt_num($total)])) . '</p></header>'
            . '<table><thead><tr>' . implode('', array_map(fn ($h) => '<th>' . $e($h) . '</th>', $headers)) . '</tr></thead><tbody>' . $body . '</tbody></table>' . $note
            . '</body></html>';
    }

    private static function send(string $type, string $filename, string $bytes): never
    {
        header('Content-Type: ' . $type);
        header('Content-Disposition: attachment; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
        header('Content-Length: ' . strlen($bytes));
        header('Cache-Control: no-store');
        echo $bytes;
        exit;
    }
}
