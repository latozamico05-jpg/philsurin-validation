<?php
/**
 * Shared Trial Locations filter / sort helpers.
 *
 * Used by locations_management.php (to filter the table) and by
 * export_locations.php (so "Export filtered" writes exactly the rows the
 * user is looking at). Keeping it in one place guarantees the screen and the
 * workbook can never disagree.
 *
 * Supported query string parameters:
 *   q         free-text over Location ID, Location of Project and Size
 *   project   exact Location of Project (dropdown)
 *   size      exact Size of Location (dropdown)
 *   status    active | removed | all
 *   min_rows  minimum number of encoded rows
 *   sort      location_id | project_location | location_size | encoded_rows | created_at
 *   dir       asc | desc
 */

function location_filter_input(): array
{
    $status = strtolower(trim((string)($_GET['status'] ?? 'active')));
    if (!in_array($status, ['active', 'removed', 'all'], true)) {
        $status = 'active';
    }

    $sortKey = (string)($_GET['sort'] ?? 'created_at');
    if (!in_array($sortKey, ['location_id', 'project_location', 'location_size', 'encoded_rows', 'created_at'], true)) {
        $sortKey = 'created_at';
    }

    $minRows = trim((string)($_GET['min_rows'] ?? ''));
    if ($minRows !== '' && (!is_numeric($minRows) || (int)$minRows < 0)) {
        $minRows = '';
    }

    $filters = [
        'status'   => $status,
        'q'        => trim((string)($_GET['q'] ?? '')),
        'project'  => trim((string)($_GET['project'] ?? '')),
        'size'     => trim((string)($_GET['size'] ?? '')),
        'min_rows' => $minRows,
    ];

    return [
        'filters' => $filters,
        'sortKey' => $sortKey,
        'sortDir' => (strtoupper(trim((string)($_GET['dir'] ?? 'desc'))) === 'ASC') ? 'ASC' : 'DESC',
        'hasFilters' => $filters['q'] !== '' || $filters['project'] !== ''
            || $filters['size'] !== '' || $filters['min_rows'] !== ''
            || $filters['status'] !== 'active',
    ];
}

/** Builds a query string that preserves the active filter (used by sort links). */
function location_filter_query(array $filters, string $sortKey, string $sortDir, array $overrides = []): string
{
    $params = array_merge($filters, ['sort' => $sortKey, 'dir' => strtolower($sortDir)], $overrides);
    foreach ($params as $key => $value) {
        if ((string)$value === '' || $value === null) {
            unset($params[$key]);
        }
    }
    return $params ? '?' . http_build_query($params) : '';
}

function location_row_matches(array $item, array $filters): bool
{
    $rowStatus = !empty($item['is_hidden']) ? 'removed' : 'active';
    if ($filters['status'] !== 'all' && $rowStatus !== $filters['status']) {
        return false;
    }
    if ($filters['q'] !== '') {
        $needle = function_exists('mb_strtolower') ? mb_strtolower($filters['q']) : strtolower($filters['q']);
        $hay = $item['location_id'] . ' ' . $item['project_location'] . ' ' . $item['location_size'];
        $hay = function_exists('mb_strtolower') ? mb_strtolower($hay) : strtolower($hay);
        if (strpos($hay, $needle) === false) {
            return false;
        }
    }
    if ($filters['project'] !== '' && (string)$item['project_location'] !== $filters['project']) {
        return false;
    }
    if ($filters['size'] !== '' && (string)$item['location_size'] !== $filters['size']) {
        return false;
    }
    if ($filters['min_rows'] !== '' && (int)$item['encoded_rows'] < (int)$filters['min_rows']) {
        return false;
    }
    return true;
}

function location_sort(array $rows, string $sortKey, string $sortDir): array
{
    usort($rows, function ($a, $b) use ($sortKey, $sortDir) {
        if ($sortKey === 'encoded_rows' || $sortKey === 'location_size') {
            $cmp = (float)$a[$sortKey] <=> (float)$b[$sortKey];
        } else {
            $cmp = strcasecmp((string)$a[$sortKey], (string)$b[$sortKey]);
        }
        if ($cmp === 0) {
            $cmp = strcasecmp((string)$a['location_id'], (string)$b['location_id']);
        }
        return $sortDir === 'ASC' ? $cmp : -$cmp;
    });
    return $rows;
}

/** Applies the filter + sort to the full location list and returns the rows to show. */
function location_apply_filter(array $locations, array $filterSpec): array
{
    $rows = array_filter($locations, function ($item) use ($filterSpec) {
        return location_row_matches($item, $filterSpec['filters']);
    });
    return location_sort(array_values($rows), $filterSpec['sortKey'], $filterSpec['sortDir']);
}

/** Distinct dropdown values, built from every location (hidden ones included). */
function location_filter_options(array $locations): array
{
    $projects = [];
    $sizes = [];
    foreach ($locations as $item) {
        $project = trim((string)$item['project_location']);
        if ($project !== '') {
            $projects[$project] = $project;
        }
        $size = trim((string)$item['location_size']);
        if ($size !== '') {
            $sizes[$size] = $size;
        }
    }
    ksort($projects, SORT_NATURAL | SORT_FLAG_CASE);
    ksort($sizes, SORT_NATURAL | SORT_FLAG_CASE);
    return ['projects' => $projects, 'sizes' => $sizes];
}