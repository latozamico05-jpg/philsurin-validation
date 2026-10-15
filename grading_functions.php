<?php

function grading_stage_list(?PDO $db = null): array
{
    $stages = ['01 GRADING', '02 GRADING', '03 GRADING', '04 GRADING', 'HARVEST'];
    if ($db) {
        try {
            $saved = $db->query('SELECT DISTINCT grading_stage FROM grading_records ORDER BY grading_stage')->fetchAll(PDO::FETCH_COLUMN);
            foreach ($saved as $stage) {
                $stage = normalize_stage_name($stage);
                if ($stage !== '' && !in_array($stage, $stages, true)) {
                    $stages[] = $stage;
                }
            }
        } catch (Throwable $ignore) {
        }
    }
    usort($stages, function ($left, $right) {
        $leftHarvest = strpos($left, 'HARVEST') !== false;
        $rightHarvest = strpos($right, 'HARVEST') !== false;
        if ($leftHarvest !== $rightHarvest) {
            return $leftHarvest ? 1 : -1;
        }
        preg_match('/\d+(?:\.\d+)?/', $left, $leftNumber);
        preg_match('/\d+(?:\.\d+)?/', $right, $rightNumber);
        if (isset($leftNumber[0], $rightNumber[0])) {
            return (float) $leftNumber[0] <=> (float) $rightNumber[0];
        }
        if (isset($leftNumber[0]) !== isset($rightNumber[0])) {
            return isset($leftNumber[0]) ? -1 : 1;
        }
        return strcmp($left, $right);
    });
    return $stages;
}

function normalize_stage_name($stage): string
{
    $stage = strtoupper(trim(str_replace('_', ' ', (string) $stage)));
    return preg_replace('/\s+/', ' ', $stage);
}

function stage_fields($stage): array
{
    $stage = normalize_stage_name($stage);
    if ($stage === '01 GRADING') {
        return [
            ['label' => 'Germination', 'key' => 'a', 'type' => 'number'],
            ['label' => 'E-S', 'key' => 'b', 'type' => 'number'],
            ['label' => 'G-S', 'key' => 'c', 'type' => 'number'],
            ['label' => 'Remarks', 'key' => 'remarks', 'type' => 'textarea'],
        ];
    }
    if ($stage === 'HARVEST' || strpos($stage, 'HARVEST') !== false) {
        return [['label' => 'Observations', 'key' => 'remarks', 'type' => 'textarea']];
    }
    return [
        ['label' => 'Population', 'key' => 'a', 'type' => 'number'],
        ['label' => 'Height', 'key' => 'b', 'type' => 'number'],
        ['label' => 'Size', 'key' => 'c', 'type' => 'number'],
        ['label' => 'Uniformity', 'key' => 'd', 'type' => 'number'],
        ['label' => 'CS', 'key' => 'e', 'type' => 'text'],
        ['label' => 'Remarks', 'key' => 'remarks', 'type' => 'textarea'],
    ];
}

function stage_columns($stage): array
{
    $stage = normalize_stage_name($stage);
    $columns = [];
    foreach (stage_fields($stage) as $field) {
        $columns[] = [
            'label' => $field['label'],
            'json' => $field['key'],
            'db' => $field['key'] === 'remarks' ? 'remarks' : stage_db_field($stage, $field['key']),
        ];
    }
    return $columns;
}

function stage_db_field(string $stage, string $key): string
{
    if (normalize_stage_name($stage) === '01 GRADING') {
        return ['a' => 'germination', 'b' => 'es_value', 'c' => 'gs_value'][$key] ?? '';
    }
    return '';
}

function parse_remarks($remarks): array
{
    $details = json_decode((string) ($remarks ?? ''), true);
    if (is_array($details)) {
        return $details;
    }
    return $remarks === null || $remarks === '' ? [] : ['remarks' => (string) $remarks];
}

function cell_value($record, $stage, $column): string
{
    if (!$record) {
        return '';
    }
    $details = parse_remarks($record['remarks'] ?? '');
    $key = $column['json'];
    if ($column['db'] === 'remarks') {
        return e((string) ($details['remarks'] ?? ''));
    }
    $dbField = $column['db'];
    $value = $dbField !== '' ? ($record[$dbField] ?? null) : null;
    if ($value === null || $value === '') {
        $value = $details[$key] ?? '';
    }
    if ($value === '' || $value === null) {
        return '';
    }
    return is_numeric($value) ? number_format((float) $value, 2, '.', '') : e((string) $value);
}

function stage_values($record, $stage): array
{
    $values = [];
    foreach (stage_columns($stage) as $column) {
        $values[] = cell_value($record, $stage, $column);
    }
    return $values;
}

function row_has_data(array $row): bool
{
    foreach (['germination', 'es_value', 'gs_value'] as $field) {
        if (($row[$field] ?? null) !== null && trim((string) $row[$field]) !== '') {
            return true;
        }
    }
    foreach (parse_remarks($row['remarks'] ?? '') as $value) {
        if (is_scalar($value) && trim((string) $value) !== '') {
            return true;
        }
    }
    return false;
}

function stage_summary(array $rows, string $stage): int
{
    $count = 0;
    foreach ($rows as $row) {
        if (normalize_stage_name($row['grading_stage'] ?? '') === normalize_stage_name($stage) && row_has_data($row)) {
            $count++;
        }
    }
    return $count;
}

function build_report_groups(array $rows): array
{
    $groups = [];
    foreach ($rows as $row) {
        if (!row_has_data($row)) {
            continue;
        }
        $varietyId = $row['variety_id'];
        $stage = normalize_stage_name($row['grading_stage'] ?? '');
        $replicate = (int) ($row['replicate'] ?? 0);
        $groups[$varietyId]['code'] = $row['code'];
        $groups[$varietyId]['name'] = $row['name'];
        $groups[$varietyId]['rows'][$stage][$replicate] = $row;
    }
    return $groups;
}

function stage_average(array $replicateRows, string $stage, int $columnIndex): string
{
    $columns = stage_columns($stage);
    if (!isset($columns[$columnIndex])) {
        return '';
    }
    $column = $columns[$columnIndex];
    $values = [];
    foreach ($replicateRows as $row) {
        $details = parse_remarks($row['remarks'] ?? '');
        $value = $column['db'] !== '' ? ($row[$column['db']] ?? null) : null;
        if ($value === null || $value === '') {
            $value = $details[$column['json']] ?? null;
        }
        if (is_numeric($value)) {
            $values[] = (float) $value;
        }
    }
    return $values ? number_format(array_sum($values) / count($values), 2, '.', '') : '';
}

function load_validation_rows(PDO $db, string $locationId, string $stage, int $varietyId): array
{
    $stmt = $db->prepare('SELECT * FROM grading_records WHERE location_id=? AND UPPER(REPLACE(grading_stage, "_", " "))=? AND variety_id=? ORDER BY created_at DESC, id DESC');
    $stmt->execute([$locationId, normalize_stage_name($stage), $varietyId]);
    $loaded = [];
    foreach ($stmt->fetchAll() as $row) {
        $replicate = (int) $row['replicate'];
        if (!isset($loaded[$replicate])) {
            $loaded[$replicate] = array_merge(parse_remarks($row['remarks'] ?? ''), [
                'a' => parse_remarks($row['remarks'] ?? '')['a'] ?? $row['germination'] ?? '',
                'b' => parse_remarks($row['remarks'] ?? '')['b'] ?? $row['es_value'] ?? '',
                'c' => parse_remarks($row['remarks'] ?? '')['c'] ?? $row['gs_value'] ?? '',
            ]);
        }
    }
    return $loaded;
}

function save_validation_rows(PDO $db, int $userId, array $post): array
{
    $project = trim((string) ($post['project_location'] ?? ''));
    $stage = normalize_stage_name($post['stage'] ?? '');
    $varietyId = (int) ($post['variety_id'] ?? 0);
    if ($project === '' || $stage === '' || $varietyId < 1) {
        throw new InvalidArgumentException('Project, grading stage, and variety are required.');
    }
    if (trim((string) ($post['location_size'] ?? '')) === '') {
        throw new InvalidArgumentException('Size of Location is required for every grading, including Harvest.');
    }

    $requestedLocationId = trim((string) ($post['location_id'] ?? ''));
    $existingLocation = false;
    if ($requestedLocationId !== '' && preg_match('/^LOC-[A-Z0-9-]+$/i', $requestedLocationId)) {
        $checkLocation = $db->prepare('SELECT 1 FROM grading_records WHERE location_id=? LIMIT 1');
        $checkLocation->execute([$requestedLocationId]);
        $existingLocation = (bool) $checkLocation->fetchColumn();
    }
    if (!$existingLocation) {
        $findLocation = $db->prepare('SELECT location_id FROM grading_records WHERE project_location=? AND date_planted <=> ? AND dr_dp <=> ? AND control_name <=> ? ORDER BY created_at DESC,id DESC LIMIT 1');
        $findLocation->execute([$project, $post['date_planted'] ?: null, $post['dr_dp'] ?: null, $post['control_name'] ?? null]);
        $matchedLocationId = $findLocation->fetchColumn();
        if ($matchedLocationId) {
            $requestedLocationId = (string) $matchedLocationId;
            $existingLocation = true;
        }
    }
    // Location identity MUST match save_validation_rows(): project | date_planted
    // | dr_dp | control_name (no location_size). Including location_size here made
    // index.php and grading_functions.php disagree, so a location edited later
    // with a different size got a brand-new LOC- id and split its history.
    $locationId = $existingLocation
        ? $requestedLocationId
        : 'LOC-' . strtoupper(substr(hash('sha256', trim($project . '|' . ($post['date_planted'] ?? '') . '|' . ($post['dr_dp'] ?? '') . '|' . ($post['control_name'] ?? ''))), 0, 12));
    $db->beginTransaction();
    try {
        $find = $db->prepare('SELECT id FROM grading_records WHERE location_id=? AND UPPER(REPLACE(grading_stage, "_", " "))=? AND variety_id=? AND replicate=? ORDER BY created_at DESC, id DESC LIMIT 1');
        $update = $db->prepare('UPDATE grading_records SET user_id=?,project_location=?,date_planted=?,dr_dp=?,control_name=?,location_size=?,grading_date=?,germination=?,es_value=?,gs_value=?,remarks=? WHERE id=?');
        $insert = $db->prepare('INSERT INTO grading_records(user_id,location_id,project_location,date_planted,dr_dp,control_name,grading_stage,variety_id,location_size,grading_date,replicate,germination,es_value,gs_value,remarks) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $saved = 0;
        for ($replicate = 1; $replicate <= 4; $replicate++) {
            $a = trim((string) ($post['field_a'][$replicate] ?? $post['germination'][$replicate] ?? ''));
            $b = trim((string) ($post['field_b'][$replicate] ?? $post['es_value'][$replicate] ?? ''));
            $c = trim((string) ($post['field_c'][$replicate] ?? $post['gs_value'][$replicate] ?? ''));
            $d = trim((string) ($post['field_d'][$replicate] ?? $post['uniformity'][$replicate] ?? ''));
            $eValue = trim((string) ($post['field_e'][$replicate] ?? ''));
            $remarks = trim((string) ($post['remarks'][$replicate] ?? $post['remarks_text'][$replicate] ?? ''));
            if ($a === '' && $b === '' && $c === '' && $d === '' && $eValue === '' && $remarks === '') {
                continue;
            }
            $payload = json_encode(['a' => $a, 'b' => $b, 'c' => $c, 'd' => $d, 'e' => $eValue, 'remarks' => $remarks], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $values = [is_numeric($a) ? (float) $a : null, is_numeric($b) ? (float) $b : null, is_numeric($c) ? (float) $c : null];
            $find->execute([$locationId, $stage, $varietyId, $replicate]);
            $existingId = $find->fetchColumn();
            if ($existingId) {
                $update->execute([$userId, $project, $post['date_planted'] ?: null, $post['dr_dp'] ?: null, $post['control_name'] ?? '', $post['location_size'] ?? '', $post['grading_date'] ?? date('Y-m-d'), ...$values, $payload, $existingId]);
            } else {
                $insert->execute([$userId, $locationId, $project, $post['date_planted'] ?: null, $post['dr_dp'] ?: null, $post['control_name'] ?? '', $stage, $varietyId, $post['location_size'] ?? '', $post['grading_date'] ?? date('Y-m-d'), $replicate, ...$values, $payload]);
            }
            $saved++;
        }
        if ($saved === 0) {
            throw new InvalidArgumentException('Enter at least one replicate value before saving.');
        }
        $db->commit();
        return ['success' => true, 'message' => 'Validation saved successfully.', 'location_id' => $locationId, 'saved' => $saved];
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
}
