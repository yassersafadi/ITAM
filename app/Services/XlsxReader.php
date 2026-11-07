<?php
declare(strict_types=1);

namespace ITAM\Services;

/**
 * Reads the first worksheet of an .xlsx file into rows of strings, without external libraries.
 * Shared and inline strings, booleans and numbers are supported; cells formatted as dates come back as
 * Y-m-d, and whole numbers under a zero-padded format (000000, "AST-"0000) come back as Excel shows them,
 * so tags keep their leading zeros. Rows keep their column positions, so blank cells stay blank.
 */
final class XlsxReader
{
    private const MAX_ROWS = 20000;

    /** @return list<list<string>> */
    public static function rows(string $file): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($file) !== true) {
            throw new \RuntimeException('Not an .xlsx file');
        }
        try {
            $sheet = self::firstSheetPath($zip);
            $xml = $zip->getFromName($sheet);
            if ($xml === false) {
                throw new \RuntimeException('Worksheet not found');
            }
            $strings = self::sharedStrings($zip);
            [$dateStyles, $padStyles] = self::styles($zip);
        } finally {
            $zip->close();
        }

        $rows = [];
        $doc = self::load($xml);
        foreach ($doc->sheetData->row ?? [] as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $col = self::columnIndex((string) $c['r']) ?? count($cells);
                $type = (string) $c['t'];
                $raw = isset($c->v) ? (string) $c->v : '';
                $value = match ($type) {
                    's' => $strings[(int) $raw] ?? '',
                    'inlineStr' => self::text($c->is),
                    'b' => $raw === '1' ? '1' : '0',
                    'str', 'e' => $raw,
                    default => $raw !== '' && isset($dateStyles[(int) $c['s']]) && is_numeric($raw) ? self::excelDate((float) $raw) : self::number($raw),
                };
                if ($type === '' || $type === 'n') {
                    $value = self::padded($value, $padStyles[(int) $c['s']] ?? null);
                }
                $cells[$col] = $value;
            }
            if ($cells) {
                $max = max(array_keys($cells));
                $line = array_fill(0, $max + 1, '');
                foreach ($cells as $i => $v) {
                    $line[$i] = $v;
                }
                $rows[] = $line;
            } else {
                $rows[] = [];
            }
            if (count($rows) >= self::MAX_ROWS) {
                break;
            }
        }
        return $rows;
    }

    private static function load(string $xml): \SimpleXMLElement
    {
        // No network or external entities; libxml blocks them by default since PHP 8.
        $doc = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
        if ($doc === false) {
            throw new \RuntimeException('Unreadable worksheet');
        }
        return $doc;
    }

    private static function firstSheetPath(\ZipArchive $zip): string
    {
        $wb = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($wb !== false && $rels !== false) {
            $book = self::load($wb);
            $first = $book->sheets->sheet[0] ?? null;
            $rid = $first ? (string) $first->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] : '';
            foreach (self::load($rels)->Relationship as $r) {
                if ((string) $r['Id'] === $rid) {
                    $target = ltrim((string) $r['Target'], '/');
                    return str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
                }
            }
        }
        return 'xl/worksheets/sheet1.xml';
    }

    private static function sharedStrings(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }
        $out = [];
        foreach (self::load($xml)->si as $si) {
            $out[] = self::text($si);
        }
        return $out;
    }

    /** Plain text of a string item, including rich-text runs. */
    private static function text(?\SimpleXMLElement $node): string
    {
        if ($node === null) {
            return '';
        }
        if (isset($node->t)) {
            return (string) $node->t;
        }
        $s = '';
        foreach ($node->r as $run) {
            $s .= (string) $run->t;
        }
        return $s;
    }

    /**
     * Cell styles by index: those whose number format is a date, and those that zero-pad whole numbers
     * (as [prefix, digits, suffix]).
     * @return array{0: array<int, true>, 1: array<int, array{string, int, string}>}
     */
    private static function styles(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/styles.xml');
        if ($xml === false) {
            return [[], []];
        }
        $doc = self::load($xml);
        $custom = $pads = [];
        foreach ($doc->numFmts->numFmt ?? [] as $f) {
            $format = (string) $f['formatCode'];
            $code = strtolower(preg_replace('/\[[^\]]*\]|"[^"]*"/', '', $format));
            $custom[(int) $f['numFmtId']] = (bool) preg_match('/[dy]|m{3,}/', $code) && !str_contains($code, 'h');
            // Only the first section counts (positive numbers); literals may sit around the zeros.
            $first = preg_split('/;(?=(?:[^"]*"[^"]*")*[^"]*$)/', $format)[0];
            $literal = '((?:"[^"]*"|\\\\.|[-\/ ])*)';
            if (preg_match('/^' . $literal . '(0{2,})' . $literal . '$/', $first, $m)) {
                $pads[(int) $f['numFmtId']] = [self::literal($m[1]), strlen($m[2]), self::literal($m[3])];
            }
        }
        // SimpleXML iteration keys are element names, so index explicitly.
        $dates = $padded = [];
        $i = 0;
        foreach ($doc->cellXfs->xf ?? [] as $xf) {
            $id = (int) $xf['numFmtId'];
            if (($id >= 14 && $id <= 17) || $id === 22 || ($custom[$id] ?? false)) {
                $dates[$i] = true;
            }
            if (isset($pads[$id])) {
                $padded[$i] = $pads[$id];
            }
            $i++;
        }
        return [$dates, $padded];
    }

    /** Text of a format's literal part: quoted runs and backslash-escaped characters. */
    private static function literal(string $part): string
    {
        return preg_replace_callback('/"([^"]*)"|\\\\(.)/', fn ($m) => $m[1] !== '' ? $m[1] : ($m[2] ?? ''), $part);
    }

    /** A whole, non-negative number shown through a zero-padding format, e.g. 11 under 000000 → 000011. */
    private static function padded(string $value, ?array $pad): string
    {
        if ($pad === null || !ctype_digit($value)) {
            return $value;
        }
        return $pad[0] . str_pad($value, $pad[1], '0', STR_PAD_LEFT) . $pad[2];
    }

    private static function columnIndex(string $ref): ?int
    {
        if (!preg_match('/^([A-Z]+)\d*$/', $ref, $m)) {
            return null;
        }
        $n = 0;
        foreach (str_split($m[1]) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }
        return $n - 1;
    }

    /** Excel serial day (1900 system) → Y-m-d. */
    private static function excelDate(float $serial): string
    {
        return gmdate('Y-m-d', (int) round(($serial - 25569) * 86400));
    }

    /** Whole numbers without a trailing ".0", and no float noise. */
    private static function number(string $raw): string
    {
        if ($raw === '' || !is_numeric($raw)) {
            return $raw;
        }
        $f = (float) $raw;
        return floor($f) === $f && abs($f) < 1e15 ? (string) (int) $f : rtrim(rtrim(number_format($f, 6, '.', ''), '0'), '.');
    }
}
