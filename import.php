<?php
require 'config.php';
require_login();

$canImport = in_array(strtolower((string) ($_SESSION['user']['role'] ?? 'viewer')), ['admin', 'validator', 'validator_germination', 'validator_field', 'validator_harvest'], true);
if (!$canImport) {
    http_response_code(403);
    echo 'Your account can view records but cannot import Excel data.';
    exit;
}

function import_flash(string $message, string $type = 'ok'): void
{
    $_SESSION['import_flash'] = ['message' => $message, 'type' => $type];
}

function import_fail(string $message, string $location = ''): void
{
    import_flash($message, 'error');
    header('Location: report.php' . ($location !== '' ? '?location=' . urlencode($location) : ''));
    exit;
}

function import_cell_text($value): string
{
    $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $value = str_replace(["\xc2\xa0", "\x00"], [' ', ''], $value);
    return trim($value);
}

function import_rows_from_csv(string $raw): array
{
    if (substr($raw, 0, 3) === "\xEF\xBB\xBF") {
        $raw = substr($raw, 3);
    }
    $rows = [];
    foreach (preg_split("/\r\n|\n|\r/", $raw) as $line) {
        if (trim($line) === '') {
            continue;
        }
        $rows[] = array_map('import_cell_text', str_getcsv($line));
    }
    return $rows;
}

function import_rows_from_excel_xml(string $raw): array
{
    $raw = ltrim(preg_replace('/^\xEF\xBB\xBF/', '', $raw));
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($raw);
    if (!$xml) {
        return [];
    }
    $xml->registerXPathNamespace('ss', 'urn:schemas-microsoft-com:office:spreadsheet');
    $found = $xml->xpath('//ss:Worksheet[1]//ss:Row');
    if (!$found) {
        $found = $xml->xpath('//Row');
    }
    $rows = [];
    if ($found) {
        foreach ($found as $row) {
            $cells = [];
            if (isset($row->Cell)) {
                foreach ($row->Cell as $cell) {
                    $text = (string) ($cell->Data ?? $cell);
                    $attrs = $cell->attributes('urn:schemas-microsoft-com:office:spreadsheet');
                    $at = (int) ($attrs['Index'] ?? 0);
                    if ($at > count($cells) + 1) {
                        while (count($cells) + 1 < $at) {
                            $cells[] = '';
                        }
                    }
                    $text = import_cell_text($text);
                    $cells[] = $text;
                    // SpreadsheetML merged cells: repeat the label across its
                    // span so two-row report headers align with data columns.
                    $mergeAcross = (int) ($attrs['MergeAcross'] ?? 0);
                    for ($span = 0; $span < $mergeAcross; $span++) {
                        $cells[] = $text;
                    }
                }
            } else {
                foreach ($row->children() as $cell) {
                    $cells[] = import_cell_text((string) $cell);
                }
            }
            while (count($cells) > 0 && end($cells) === '') {
                array_pop($cells);
            }
            if (implode('', $cells) === '') {
                continue;
            }
            $rows[] = $cells;
        }
    }
    return $rows;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['excel_file'])) {
    import_fail('Choose an exported .xls or .csv file, then click Import Excel.');
}
if (($_FILES['excel_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    import_fail('Upload failed. Please try a smaller .xls or .csv file.');
}
$original = (string) ($_FILES['excel_file']['name'] ?? 'upload');
$extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
if (!in_array($extension, ['csv', 'xls', 'xml', 'html'], true)) {
    import_fail('Only .xls (Excel XML from this Report page) or .csv files can be imported.');
}
$raw = @file_get_contents($_FILES['excel_file']['tmp_name']);
if ($raw === false || trim($raw) === '') {
    import_fail('That file looks empty. Re-export it from the Report page, then import it back.');
}

$sheetRows = $extension === 'csv' ? import_rows_from_csv($raw) : import_rows_from_excel_xml($raw);
if (!$sheetRows) {
    import_fail('Could not read that file. Re-export it from the Report page, then import it back.');
}

$headerIndex = -1;
$stageRowIndex = -1;
$dateRowIndex = -1;
$header = [];
foreach ($sheetRows as $index => $row) {
    $lower = array_map(function ($cell) { return strtolower(trim((string) $cell)); }, $row);
    if (in_array('variety', $lower, true) && in_array('rep', $lower, true)) {
        // Could be the stage-block row (Variety|Rep|01 GRADING|02 GRADING...)
        // or the true field row (Variety|Rep|01 GRADING | Germination|...).
        // Peek ahead: if the NEXT rows contain another Variety|Rep row, this
        // one is the block row — skip to the LAST such row (the field row).
        $peek = $index + 1;
        $lastMatch = $index;
        while (isset($sheetRows[$peek]) && $peek < $index + 4) {
            $peekLower = array_map(function ($cell) { return strtolower(trim((string) $cell)); }, (array) $sheetRows[$peek]);
            if (in_array('variety', $peekLower, true) && in_array('rep', $peekLower, true)) {
                $lastMatch = $peek;
            }
            $peek++;
        }
        // If this row's data cells look like bare stage names (no " | "),
        // it is the block row — jump to the field row.
        $bareStages = 0;
        foreach ($lower as $cell) {
            if (preg_match('/^(0[1-4] grading|harvest)$/', trim($cell))) { $bareStages++; }
        }
        if ($lastMatch !== $index) {
            $stageRowIndex = $index;
            $index = $lastMatch;
            $lower = array_map(function ($cell) { return strtolower(trim((string) $cell)); }, (array) $sheetRows[$index]);
        } elseif ($bareStages >= 2) {
            // Block row followed by date row then field row: remember it and
            // advance past the date row to the field row.
            $stageRowIndex = $index;
            $maybeDate = array_values((array) ($sheetRows[$index + 1] ?? []));
            $maybeField = array_values((array) ($sheetRows[$index + 2] ?? []));
            $maybeFieldLower = array_map(function ($cell) { return strtolower(trim((string) $cell)); }, $maybeField);
            if (in_array('variety', $maybeFieldLower, true) && in_array('rep', $maybeFieldLower, true)) {
                $dateRowIndex = $index + 1;
                $index = $index + 2;
                $lower = $maybeFieldLower;
            }
        }
        $headerIndex = $index;
        $header = $lower;
        break;
    }
}
if ($headerIndex < 0) {
    import_fail('Header row with "Variety" and "Rep" was not found. Use the template from the Report page.');
}

$columnMap = [];
foreach ($header as $position => $name) {
    $name = trim((string) $name);
    if ($name === '' || $name === 'variety' || $name === 'rep') {
        continue;
    }
    $columnMap[$position] = $name;
}
if (!$columnMap) {
    import_fail('No grading columns were found in the header row.');
}

// Report exports use a two-row header: a merged stage-label row followed by a
// field-name row. Detect it and merge both rows into one combined header so
// stage + field columns resolve the same way as single-row CSV/template files.
$fieldHeaderIndex = -1;
if (isset($sheetRows[$headerIndex + 1])) {
    $nextRow = array_values((array) $sheetRows[$headerIndex + 1]);
    $nextFieldLabelCount = 0;
    for ($nextPos = 2; $nextPos < count($nextRow); $nextPos++) {
        if (trim((string) $nextRow[$nextPos]) !== '') {
            $nextFieldLabelCount++;
        }
    }
    if (trim((string) ($nextRow[0] ?? '')) === ''
        && trim((string) ($nextRow[1] ?? '')) === ''
        && $nextFieldLabelCount >= 2) {
        $fieldHeaderIndex = $headerIndex + 1;
    }
}
$stageMetaSize = [];
$stageMetaDate = [];
if ($fieldHeaderIndex >= 0) {
    $stageCells = array_values((array) $sheetRows[$headerIndex]);
    $fieldCells = array_values((array) $sheetRows[$fieldHeaderIndex]);
    $combinedWidth = max(count($stageCells), count($fieldCells));
    $combinedHeader = [];
    for ($combinedPos = 0; $combinedPos < $combinedWidth; $combinedPos++) {
        if ($combinedPos < 2) {
            $combinedHeader[$combinedPos] = $combinedPos === 0 ? 'variety' : 'rep';
            continue;
        }
        $combinedStagePart = strtolower(trim((string) ($stageCells[$combinedPos] ?? '')));
        $combinedFieldPart = strtolower(trim((string) ($fieldCells[$combinedPos] ?? '')));
        if ($combinedStagePart !== '' && $combinedFieldPart !== '') {
            $combinedHeader[$combinedPos] = $combinedStagePart . ' | ' . $combinedFieldPart;
        } else {
            $combinedHeader[$combinedPos] = $combinedStagePart !== '' ? $combinedStagePart : $combinedFieldPart;
        }
    }
    $header = $combinedHeader;
    $columnMap = [];
    foreach ($header as $position => $name) {
        $name = trim((string) $name);
        if ($name === '' || $name === 'variety' || $name === 'rep') {
            continue;
        }
        $columnMap[$position] = $name;
    }
    if (!$columnMap) {
        import_fail('No grading columns were found in the header row.');
    }
    // Per-stage size/date ride along in the stage-label row of report exports;
    // keep them so the round trip preserves each stage's own values.
    foreach ($stageCells as $stageLabel) {
        $stageLabel = (string) $stageLabel;
        $stageHasSize = preg_match('/SIZE OF FIELD\s*:\s*([^|]+)/i', $stageLabel, $stageSizeMatch);
        $stageHasDate = preg_match('/DATE\s*:\s*([0-9\/\-]+)/i', $stageLabel, $stageDateMatch);
        if (!$stageHasSize && !$stageHasDate) {
            continue;
        }
        $stageName = normalize_stage_name(trim((string) explode('|', $stageLabel)[0]));
        if ($stageName === '') {
            continue;
        }
        if ($stageHasSize) {
            $stageMetaSize[$stageName] = trim($stageSizeMatch[1]);
        }
        if ($stageHasDate) {
            $stageMetaDate[$stageName] = trim($stageDateMatch[1]);
        }
    }
}
// Sheet-exact date row: in the photo each stage block has its own date UNDER
// the block label (05/07/24 under 01, 07/23/2024 under 02/03/04). Map each
// block's date to its stage by column position so every replicate keeps its
// own grading_date — nothing defaults to today.
if ($stageRowIndex >= 0 && $dateRowIndex < 0 && isset($sheetRows[$stageRowIndex + 1])) {
    $peekCells = array_values((array) $sheetRows[$stageRowIndex + 1]);
    $peekLower = array_map(function ($cell) { return strtolower(trim((string) $cell)); }, $peekCells);
    if (!in_array('variety', $peekLower, true) && !in_array('rep', $peekLower, true)) {
        $dateRowIndex = $stageRowIndex + 1;
    }
}
if ($dateRowIndex >= 0) {
    $stageCells = array_values((array) ($stageRowIndex >= 0 ? $sheetRows[$stageRowIndex] : []));
    $dateCells = array_values((array) $sheetRows[$dateRowIndex]);
    $width = max(count($stageCells), count($dateCells));
    $runningStage = '';
    for ($pos = 2; $pos < $width; $pos++) {
        $stageLabel = normalize_stage_name(trim((string) ($stageCells[$pos] ?? '')));
        if ($stageLabel === '' && $runningStage !== '') {
            // Date row positions BETWEEN merged stage blocks: the date cell
            // sits under the block, not necessarily on the label column.
        } elseif ($stageLabel !== '') {
            $runningStage = $stageLabel;
        }
        $cell = trim((string) ($dateCells[$pos] ?? ''));
        if ($cell === '' || $runningStage === '') {
            continue;
        }
        if (preg_match('/\d/', $cell)) {
            $stageMetaDate[$runningStage] = $cell;
        }
    }
}

$meta = ['location_id' => '', 'project_location' => '', 'date_planted' => null, 'dr_dp' => null, 'control_name' => '', 'location_size' => '', 'grading_date' => date('Y-m-d')];
for ($i = 0; $i < $headerIndex; $i++) {
    $row = array_values((array) $sheetRows[$i]);
    $joined = implode(' | ', $row);
    if (preg_match('/SIZE OF FIELD\s*:\s*([^|]+)/i', $joined, $m)) {
        $meta['location_size'] = trim($m[1]);
    }
    if (preg_match('/DATE\s*:\s*([0-9\/\-]+)/i', $joined, $m)) {
        $meta['grading_date'] = trim($m[1]);
    }
    for ($c = 0; $c < count($row); $c += 2) {
        $key = strtolower(trim((string) ($row[$c] ?? '')));
        $value = trim((string) ($row[$c + 1] ?? ''));
        if ($value === '') {
            continue;
        }
        if (strpos($key, 'location of project') !== false || $key === 'project') {
            $meta['project_location'] = $value;
        } elseif (strpos($key, 'location id') !== false) {
            $meta['location_id'] = $value;
        } elseif (strpos($key, 'date planted') !== false) {
            $meta['date_planted'] = $value;
        } elseif (strpos($key, 'dr') !== false && strpos($key, 'dp') !== false) {
            $meta['dr_dp'] = $value;
        } elseif (strpos($key, 'control') !== false || strpos($key, 'check') !== false) {
            $meta['control_name'] = $value;
        } elseif (strpos($key, 'size of location') !== false || strpos($key, 'size of field') !== false || $key === 'size') {
            $meta['location_size'] = $value;
        }
    }
}
if ($meta['project_location'] === '') {
    import_fail('Location of Project is missing from the file header. Fill it in, then import again.');
}
if (strtoupper(trim($meta['project_location'])) === 'TYPE PROJECT HERE') {
    import_fail('Fill in the "Location of Project" cell in the template first, then import again.');
}

$toDate = function ($value) {
    $value = trim((string) ($value ?? ''));
    if ($value === '') {
        return null;
    }
    // DP/date quirk in the photo: "DP: January 18, 2024" and harvest
    // "12/01/204" (3-digit year typo). Strip the DP: prefix and repair
    // 3-digit years (204 -> 2024) BEFORE parsing so nothing is lost.
    $value = preg_replace('/^(DP|DR\s*\/\s*DP|DR|DATE)\s*:\s*/i', '', $value);
    $value = trim($value);
    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{3})$/', $value, $m)) {
        $yy = $m[3];
        // 204 -> 2024, 024 -> 2024, 194 -> 1994 style repair: assume 20xx
        // when the 3-digit year starts with 2, else 19xx.
        $full = ($yy[0] === '2' ? '2' . $yy : '1' . $yy);
        if (strlen($full) === 4) { $full = substr($full, 0, 4); }
        if (strlen($full) !== 4) { $full = '20' . substr($yy, -2); }
        $value = $m[1] . '/' . $m[2] . '/' . $full;
    }
    foreach (['Y-m-d', 'm/d/Y', 'm/d/y', 'd/m/Y', 'm-d-Y', 'd-m-Y', 'Y/m/d', 'F j, Y', 'M j, Y'] as $format) {
        $parsed = DateTime::createFromFormat($format, $value);
        if ($parsed && $parsed->format($format) === $value) {
            return $parsed->format('Y-m-d');
        }
    }
    $timestamp = strtotime($value);
    return $timestamp ? date('Y-m-d', $timestamp) : null;
};
$meta['date_planted'] = $toDate($meta['date_planted']);
$meta['dr_dp'] = $toDate($meta['dr_dp']);
$meta['grading_date'] = $toDate($meta['grading_date']) ?: date('Y-m-d');
foreach ($stageMetaDate as $stageMetaKey => $stageRawDate) {
    $stageParsedDate = $toDate($stageRawDate);
    if ($stageParsedDate) {
        $stageMetaDate[$stageMetaKey] = $stageParsedDate;
    } else {
        unset($stageMetaDate[$stageMetaKey]);
    }
}

$locationId = '';
if (preg_match('/^LOC-[A-Z0-9-]+$/i', trim((string) $meta['location_id']))) {
    $fileLocation = trim((string) $meta['location_id']);
    $checkLocation = $db->prepare('SELECT 1 FROM grading_records WHERE location_id=? LIMIT 1');
    $checkLocation->execute([$fileLocation]);
    if ($checkLocation->fetchColumn()) {
        $locationId = $fileLocation;
    }
}
if ($locationId === '') {
    $findLocation = $db->prepare('SELECT location_id FROM grading_records WHERE project_location=? AND date_planted <=> ? AND dr_dp <=> ? AND control_name <=> ? ORDER BY created_at DESC,id DESC LIMIT 1');
    $findLocation->execute([$meta['project_location'], $meta['date_planted'], $meta['dr_dp'], $meta['control_name']]);
    $matchedLocationId = $findLocation->fetchColumn();
    if ($matchedLocationId) {
        $locationId = (string) $matchedLocationId;
    }
}
if ($locationId === '') {
    $locationId = 'LOC-' . strtoupper(substr(hash('sha256', trim($meta['project_location'] . '|' . ($meta['date_planted'] ?? '') . '|' . ($meta['dr_dp'] ?? '') . '|' . $meta['control_name'])), 0, 12));
}
$userId = (int) ($_SESSION['user']['id'] ?? 0);
$varietyIdByCode = [];
foreach ($db->query('SELECT id, code FROM varieties')->fetchAll() as $row) {
    $varietyIdByCode[strtolower(trim((string) $row['code']))] = (int) $row['id'];
}

$stages = grading_stage_list($db);
$stageKeys = [];
foreach ($stages as $stage) {
    $stageKeys[normalize_stage_name($stage)] = $stage;
}
$columnStage = [];
$columnField = [];
foreach ($columnMap as $position => $label) {
    $foundStage = '';
    $foundField = '';
    $labelLower = strtolower($label);
    foreach ($stageKeys as $norm => $stageName) {
        if (strpos($labelLower, strtolower($norm)) === 0 || strpos($labelLower, strtolower($stageName)) !== false) {
            $foundStage = $norm;
            break;
        }
    }
    if ($foundStage === '') {
        $foundStage = normalize_stage_name($stages[0]);
    }
    $fieldPart = preg_replace('/^.*\|\s*/', '', $label);
    $fieldPart = strtolower(trim($fieldPart));
    // Sheet-exact aliases from attached photo:
    // 01 = Germination|E-S|GS|REMARKS · 02/03/04 = Uniformity|Popn|Height|Size|GS|REMARKS
    $aliases = ['germination' => 'a', 'germinatio' => 'a', 'e-s' => 'b', 'es' => 'b', 'g-s' => 'c', 'gs' => 'c', 'germ' => 'a', 'population' => 'a', 'popn' => 'a', 'pop' => 'a', 'height' => 'b', 'size' => 'c', 'uniformity' => 'd', 'uniform' => 'd', 'niform' => 'd', 'cs' => 'e', 'observations' => 'remarks', 'observation' => 'remarks', 'remarks' => 'remarks', 'remark' => 'remarks'];
    $foundField = $aliases[$fieldPart] ?? '';
    if ($foundField === '') {
        foreach (stage_columns($foundStage) as $column) {
            if (strtolower($column['label']) === $fieldPart) {
                $foundField = $column['json'];
                break;
            }
        }
    }
    if ($foundField === '') {
        continue;
    }
    $columnStage[$position] = $foundStage;
    $columnField[$position] = $foundField;
}
if (!$columnStage) {
    import_fail('No recognizable grading columns were found. Use the template from the Report page.');
}
$extraBlocks = 0;
for ($r = $headerIndex + 1; $r < count($sheetRows); $r++) {
    $probe = array_values((array) $sheetRows[$r]);
    if (strtolower(trim((string) ($probe[0] ?? ''))) === 'location id') {
        $extraBlocks++;
    }
}
if ($extraBlocks > 0) {
    import_fail('That file contains more than one location. Open one location report at a time, export it, then import it back.');
}

$find = $db->prepare('SELECT id FROM grading_records WHERE location_id=? AND UPPER(REPLACE(grading_stage, "_", " "))=? AND variety_id=? AND replicate=? ORDER BY created_at DESC, id DESC LIMIT 1');
$update = $db->prepare('UPDATE grading_records SET user_id=?,project_location=?,date_planted=?,dr_dp=?,control_name=?,location_size=?,grading_date=?,germination=?,es_value=?,gs_value=?,remarks=? WHERE id=?');
$insert = $db->prepare('INSERT INTO grading_records(user_id,location_id,project_location,date_planted,dr_dp,control_name,grading_stage,variety_id,location_size,grading_date,replicate,germination,es_value,gs_value,remarks) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');

$imported = 0;
$skipped = [];
$currentCode = '';
$db->beginTransaction();
try {
    for ($r = $headerIndex + 1; $r < count($sheetRows); $r++) {
        $row = array_values((array) $sheetRows[$r]);
        $firstCell = trim((string) ($row[0] ?? ''));
        if ($firstCell !== '') {
            $parts = preg_split('/\s+-\s+/', $firstCell, 2);
            $code = strtolower(trim((string) $parts[0]));
            if (!isset($varietyIdByCode[$code])) {
                $code = strtolower(trim($firstCell));
            }
            if (isset($varietyIdByCode[$code])) {
                $currentCode = $code;
            }
        }
        $repText = strtolower(trim((string) ($row[1] ?? '')));
        if (strpos($repText, 'ave') !== false || $repText === '') {
            continue;
        }
        if (!ctype_digit($repText) || (int) $repText < 1 || (int) $repText > 4) {
            continue;
        }
        if ($currentCode === '' || !isset($varietyIdByCode[$currentCode])) {
            $skipped[] = 'Row ' . ($r + 1) . ': unknown variety';
            continue;
        }
        $replicate = (int) $repText;
        $varietyId = $varietyIdByCode[$currentCode];
        $byStage = [];
        foreach ($columnStage as $position => $stageNorm) {
            $value = trim((string) ($row[$position] ?? ''));
            if ($value === '' || $value === '—') {
                continue;
            }
            $byStage[$stageNorm][$columnField[$position]] = $value;
        }
        foreach ($byStage as $stageNorm => $fields) {
            $a = $fields['a'] ?? '';
            $b = $fields['b'] ?? '';
            $c = $fields['c'] ?? '';
            $d = $fields['d'] ?? '';
            $eValue = $fields['e'] ?? '';
            $remarks = $fields['remarks'] ?? '';
            if ($a === '' && $b === '' && $c === '' && $d === '' && $eValue === '' && $remarks === '') {
                continue;
            }
            $recordSize = $stageMetaSize[$stageNorm] ?? $meta['location_size'];
            $recordDate = $stageMetaDate[$stageNorm] ?? $meta['grading_date'];
            $payload = json_encode(['a' => $a, 'b' => $b, 'c' => $c, 'd' => $d, 'e' => $eValue, 'remarks' => $remarks], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $values = [is_numeric($a) ? (float) $a : null, is_numeric($b) ? (float) $b : null, is_numeric($c) ? (float) $c : null];
            $find->execute([$locationId, $stageNorm, $varietyId, $replicate]);
            $existingId = $find->fetchColumn();
            if ($existingId) {
                $update->execute([$userId, $meta['project_location'], $meta['date_planted'], $meta['dr_dp'], $meta['control_name'], $recordSize, $recordDate, $values[0], $values[1], $values[2], $payload, $existingId]);
            } else {
                $insert->execute([$userId, $locationId, $meta['project_location'], $meta['date_planted'], $meta['dr_dp'], $meta['control_name'], $stageNorm, $varietyId, $recordSize, $recordDate, $replicate, $values[0], $values[1], $values[2], $payload]);
            }
            $imported++;
        }
    }
    $db->commit();
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    import_fail('Import failed: ' . $error->getMessage());
}

if ($imported === 0) {
    import_fail('No data rows were imported. ' . ($skipped ? implode('; ', array_slice($skipped, 0, 3)) : 'Check the Variety and Rep columns.'), $locationId);
}

$message = 'Imported ' . $imported . ' replicate record' . ($imported === 1 ? '' : 's') . ' from ' . $original . '.';
if ($skipped) {
    $message .= ' Skipped ' . count($skipped) . ' row' . (count($skipped) === 1 ? '' : 's') . '.';
}
import_flash($message, 'ok');
header('Location: report.php?location=' . urlencode($locationId));



