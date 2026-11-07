<?php
// Excel import reader: tags stored as numbers under a zero-padding format keep their zeros, text and dates are untouched.
use ITAM\Services\XlsxReader;

return function (string $root, string $tmp): void {
    $file = $tmp . '/pad-test.xlsx';
    $zip = new ZipArchive();
    $zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('xl/styles.xml', '<?xml version="1.0"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<numFmts count="3"><numFmt numFmtId="164" formatCode="000000"/><numFmt numFmtId="165" formatCode="&quot;AST-&quot;0000"/>'
        . '<numFmt numFmtId="166" formatCode="000000;[Red]\-000000"/></numFmts>'
        . '<cellXfs count="5"><xf numFmtId="0"/><xf numFmtId="164"/><xf numFmtId="165"/><xf numFmtId="14"/><xf numFmtId="166"/></cellXfs></styleSheet>');
    $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>000001</t></si></sst>');
    $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
        . '<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" s="1"><v>11</v></c><c r="C1" s="1"><v>100</v></c><c r="D1" s="2"><v>7</v></c>'
        . '<c r="E1"><v>12</v></c><c r="F1" s="3"><v>45000</v></c><c r="G1" s="4"><v>42</v></c><c r="H1" s="1"><v>1.5</v></c></row>'
        . '</sheetData></worksheet>');
    $zip->close();

    $row = XlsxReader::rows($file)[0] ?? [];
    unlink($file);
    check('xlsx: text tag kept as typed', ($row[0] ?? null) === '000001');
    check('xlsx: numbers under 000000 keep leading zeros', ($row[1] ?? null) === '000011' && ($row[2] ?? null) === '000100', json_encode($row));
    check('xlsx: literal prefix in the format is kept', ($row[3] ?? null) === 'AST-0007', json_encode($row));
    check('xlsx: General numbers and dates unchanged', ($row[4] ?? null) === '12' && ($row[5] ?? null) === '2023-03-15', json_encode($row));
    check('xlsx: multi-section format padded, fractions untouched', ($row[6] ?? null) === '000042' && ($row[7] ?? null) === '1.5', json_encode($row));
};
