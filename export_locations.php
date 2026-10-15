<?php
require 'config.php';
require_login();
require_once __DIR__ . '/location_filters.php';

$allRows = $db->query('SELECT g.*,v.code,v.name FROM grading_records g JOIN varieties v ON v.id=g.variety_id ORDER BY g.created_at DESC,g.id DESC')->fetchAll();
$locations = [];
foreach ($allRows as $row) {
    if (!row_has_data($row)) {
        continue;
    }
    $id = $row['location_id'];
    if (!isset($locations[$id])) {
        $locations[$id] = [
            'location_id'    => $id,
            'project_location' => $row['project_location'],
            'location_size'  => $row['location_size'],
            'date_planted'   => $row['date_planted'],
            'dr_dp'          => $row['dr_dp'],
            'encoded_rows'   => 0,
            'created_at'     => $row['created_at'],
        ];
    }
    $locations[$id]['encoded_rows']++;
}
$hiddenIds = $db->query('SELECT location_id FROM hidden_trial_locations')->fetchAll(PDO::FETCH_COLUMN);
$hidden = array_fill_keys($hiddenIds, true);
foreach ($locations as $lid => $item) {
    $locations[$lid]['is_hidden'] = isset($hidden[$item['location_id']]);
}

$filterSpec = location_filter_input();
$rows = location_apply_filter($locations, $filterSpec);

function xlsx_cell($value, string $style = ''): string
{
    $value = htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $attr = $style !== '' ? ' ss:StyleID="' . htmlspecialchars($style, ENT_XML1, 'UTF-8') . '"' : '';
    return '<Cell' . $attr . '><Data ss:Type="String">' . $value . '</Data></Cell>';
}
function xlsx_row(array $cells): string
{
    return '<Row>' . implode('', $cells) . '</Row>';
}
function xlsx_styles(): string
{
    $b = '<Borders>'
        . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>'
        . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>'
        . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>'
        . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>'
        . '</Borders>';
    return '<Styles>'
        . '<Style ss:ID="Default" ss:Name="Normal"><Font ss:FontName="Calibri" ss:Size="11"/>' . $b . '</Style>'
        . '<Style ss:ID="hdr"><Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1"/><Interior ss:Color="#DFF2EA" ss:Pattern="Solid"/>' . $b . '</Style>'
        . '</Styles>';
}

$base = 'philsurin-trial-locations';
if ($filterSpec['hasFilters']) {
    $base .= '-' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $filterSpec['filters']['project'] !== '' ? $filterSpec['filters']['project'] : 'filtered');
}
$fileName = $base . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');

echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<?mso-application progid="Excel.Sheet"?>';
echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
echo xlsx_styles();
echo '<Worksheet ss:Name="Trial Locations"><Table>';
echo xlsx_row(array_map(function ($label) { return xlsx_cell($label, 'hdr'); }, ['Location ID', 'Location of Project', 'Size of Location', 'Encoded Rows', 'Date Planted', 'DR / DP', 'Status']));
foreach ($rows as $item) {
    echo xlsx_row([
        xlsx_cell($item['location_id']),
        xlsx_cell($item['project_location']),
        xlsx_cell($item['location_size']),
        xlsx_cell($item['encoded_rows']),
        xlsx_cell($item['date_planted']),
        xlsx_cell($item['dr_dp']),
        xlsx_cell(!empty($item['is_hidden']) ? 'Removed' : 'Active'),
    ]);
}
echo '</Table></Worksheet>';
echo '</Workbook>';