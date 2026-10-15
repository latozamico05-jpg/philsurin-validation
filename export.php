<?php
require 'config.php';
require_login();
philsurin_require_export();

$locationFilter = trim((string) ($_GET['location'] ?? ''));
$sql = 'SELECT g.*,v.code,v.name FROM grading_records g JOIN varieties v ON v.id=g.variety_id WHERE NOT EXISTS (SELECT 1 FROM hidden_trial_locations h WHERE h.location_id=g.location_id)';
$params = [];
if ($locationFilter !== '') {
	$sql .= ' AND g.location_id=?';
	$params[] = $locationFilter;
}
$sql .= ' ORDER BY g.location_id,v.code,g.replicate,g.created_at,g.id';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
$locations = [];
foreach ($rows as $row) {
	if (row_has_data($row)) {
		$locations[$row['location_id']][] = $row;
	}
}
$stages = grading_stage_list($db);

function excel_xml_cell($value, string $style = ''): string
{
	$value = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
	$styleAttr = $style !== '' ? ' ss:StyleID="' . htmlspecialchars($style, ENT_XML1, 'UTF-8') . '"' : '';
	return '<Cell' . $styleAttr . '><Data ss:Type="String">' . $value . '</Data></Cell>';
}

// Visible grid lines so the template/report sheets are legible in Excel.
function excel_xml_styles(): string
{
	$border = function ($position) {
		return '<Border ss:Position="' . $position . '" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>';
	};
	$borders = '<Borders>' . $border('Top') . $border('Bottom') . $border('Left') . $border('Right') . '</Borders>';
	return '<Styles>'
		. '<Style ss:ID="Default" ss:Name="Normal"><Font ss:FontName="Calibri" ss:Size="11"/>' . $borders . '</Style>'
		. '<Style ss:ID="hdr"><Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1"/><Interior ss:Color="#DFF2EA" ss:Pattern="Solid"/>' . $borders . '</Style>'
		. '</Styles>';
}

function excel_xml_row(array $cells): string
{
	return '<Row>' . implode('', $cells) . '</Row>';
}

function excel_sheet_name(string $name, array &$used): string
{
	$name = preg_replace('/[\\\\\/\?\*\[\]:]/', '_', $name);
	$name = substr($name ?: 'Trial', 0, 31);
	$base = $name;
	$suffix = 1;
	while (isset($used[$name])) {
		$tail = '-' . $suffix++;
		$name = substr($base, 0, 31 - strlen($tail)) . $tail;
	}
	$used[$name] = true;
	return $name;
}

function export_header_labels(array $stages): array
{
    $labels = ['Variety', 'Rep'];
    foreach ($stages as $stage) {
        foreach (stage_columns($stage) as $column) {
            $labels[] = $stage . ' | ' . $column['label'];
        }
    }
    return $labels;
}

$exportFormat = strtolower(trim((string) ($_GET['format'] ?? '')));
$isTemplate = isset($_GET['template']) && (string) $_GET['template'] !== '' && (string) $_GET['template'] !== '0';
if ($isTemplate) {
    $varieties = $db->query('SELECT code, name FROM varieties WHERE active=1 ORDER BY code')->fetchAll();
    $headerLabels = export_header_labels($stages);
    $fileBase = 'philsurin-import-template';
    if ($exportFormat === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fileBase . '.csv"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Location ID', '', 'Location of Project', 'TYPE PROJECT HERE']);
        fputcsv($out, ['DR / DP', '', 'Date Planted', '', 'Control', '', 'Size of Field', '']);
        fputcsv($out, $headerLabels);
        foreach ($varieties as $variety) {
            $label = trim((string) ($variety['code'] ?? ''));
            if (($variety['name'] ?? '') !== '') {
                $label .= ' - ' . $variety['name'];
            }
            for ($replicate = 1; $replicate <= 4; $replicate++) {
                $csvRow = array_fill(0, count($headerLabels), '');
                $csvRow[0] = $replicate === 1 ? $label : '';
                $csvRow[1] = (string) $replicate;
                fputcsv($out, $csvRow);
            }
        }
        fclose($out);
    } else {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fileBase . '.xls"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        echo '<?mso-application progid="Excel.Sheet"?>';
        echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
        echo excel_xml_styles();
        echo '<Worksheet ss:Name="Template"><Table>';
        echo excel_xml_row([excel_xml_cell('Location ID', 'hdr'), excel_xml_cell(''), excel_xml_cell('Location of Project', 'hdr'), excel_xml_cell('TYPE PROJECT HERE')]);
        echo excel_xml_row([excel_xml_cell('DR / DP', 'hdr'), excel_xml_cell(''), excel_xml_cell('Date Planted', 'hdr'), excel_xml_cell(''), excel_xml_cell('Control', 'hdr'), excel_xml_cell(''), excel_xml_cell('Size of Field', 'hdr'), excel_xml_cell('')]);
        echo excel_xml_row(array_map(function ($label) { return excel_xml_cell($label, 'hdr'); }, $headerLabels));
        foreach ($varieties as $variety) {
            $label = trim((string) ($variety['code'] ?? ''));
            if (($variety['name'] ?? '') !== '') {
                $label .= ' - ' . $variety['name'];
            }
            for ($replicate = 1; $replicate <= 4; $replicate++) {
                $cells = [excel_xml_cell($replicate === 1 ? $label : ''), excel_xml_cell((string) $replicate)];
                while (count($cells) < count($headerLabels)) {
                    $cells[] = excel_xml_cell('');
                }
                echo excel_xml_row($cells);
            }
        }
        echo '</Table></Worksheet>';
        echo '</Workbook>';
    }
    exit;
}

if ($exportFormat === 'csv') {
	$headerLabels = export_header_labels($stages);
	$fileName = $locationFilter !== '' ? 'philsurin-report-' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $locationFilter) . '.csv' : 'philsurin-trial-reports.csv';
	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename="' . $fileName . '"');
	header('Cache-Control: no-store, no-cache, must-revalidate');
	echo "\xEF\xBB\xBF";
	$out = fopen('php://output', 'w');
	foreach ($locations as $locationId => $locationRows) {
		$groups = build_report_groups($locationRows);
		$first = $locationRows[0];
		fputcsv($out, ['Location ID', $locationId, 'Location of Project', $first['project_location'] ?? '']);
		fputcsv($out, ['DR / DP', $first['dr_dp'] ?? '', 'Date Planted', $first['date_planted'] ?? '', 'Control', $first['control_name'] ?? '', 'Size of Field', $first['location_size'] ?? '']);
		fputcsv($out, $headerLabels);
		foreach ($groups as $group) {
			for ($replicate = 1; $replicate <= 4; $replicate++) {
				$csvRow = [$replicate === 1 ? $group['code'] . ' - ' . $group['name'] : '', (string) $replicate];
				foreach ($stages as $stage) {
					foreach (stage_values($group['rows'][$stage][$replicate] ?? null, $stage) as $value) {
						$csvRow[] = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
					}
				}
				fputcsv($out, $csvRow);
			}
			$csvRow = ['', 'Ave.'];
			foreach ($stages as $stage) {
				foreach (stage_columns($stage) as $columnIndex => $column) {
					$csvRow[] = stage_average($group['rows'][$stage] ?? [], $stage, $columnIndex);
				}
			}
			fputcsv($out, $csvRow);
		}
		fputcsv($out, []);
	}
	fclose($out);
	exit;
}


header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="philsurin-trial-reports.xls"');
header('Cache-Control: no-store, no-cache, must-revalidate');
echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<?mso-application progid="Excel.Sheet"?>';
echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
echo excel_xml_styles();
$usedNames = [];
foreach ($locations as $locationId => $locationRows) {
	$groups = build_report_groups($locationRows);
	$first = $locationRows[0];
	$name = excel_sheet_name($locationId . ' ' . ($first['project_location'] ?? ''), $usedNames);
	echo '<Worksheet ss:Name="' . htmlspecialchars($name, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '"><Table>';
	echo excel_xml_row([excel_xml_cell('Location ID', 'hdr'), excel_xml_cell($locationId), excel_xml_cell('Location of Project', 'hdr'), excel_xml_cell($first['project_location'] ?? '')]);
	echo excel_xml_row([excel_xml_cell('DR / DP', 'hdr'), excel_xml_cell($first['dr_dp'] ?? ''), excel_xml_cell('Date Planted', 'hdr'), excel_xml_cell($first['date_planted'] ?? ''), excel_xml_cell('Control', 'hdr'), excel_xml_cell($first['control_name'] ?? '')]);
	$stageHeader = [excel_xml_cell('Variety', 'hdr'), excel_xml_cell('Rep', 'hdr')];
	foreach ($stages as $stage) {
		$columns = stage_columns($stage);
		$stageRows = array_filter($locationRows, function ($row) use ($stage) { return normalize_stage_name($row['grading_stage'] ?? '') === normalize_stage_name($stage); });
		$stageRow = reset($stageRows) ?: [];
		$label = $stage;
		if ($stageRow) {
			$label .= ' | SIZE OF FIELD: ' . ($stageRow['location_size'] ?? '') . ' | DATE: ' . ($stageRow['grading_date'] ?? '');
		}
		$mergeAcross = max(0, count($columns) - 1);
		$stageHeader[] = '<Cell ss:MergeAcross="' . $mergeAcross . '" ss:StyleID="hdr"><Data ss:Type="String">' . htmlspecialchars($label, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
	}
	echo excel_xml_row($stageHeader);
	$fieldHeader = [excel_xml_cell('', 'hdr'), excel_xml_cell('', 'hdr')];
	foreach ($stages as $stage) {
		foreach (stage_columns($stage) as $column) {
			$fieldHeader[] = excel_xml_cell($column['label'], 'hdr');
		}
	}
	echo excel_xml_row($fieldHeader);
	foreach ($groups as $group) {
		for ($replicate = 1; $replicate <= 4; $replicate++) {
			$cells = [excel_xml_cell($replicate === 1 ? $group['code'] . ' - ' . $group['name'] : ''), excel_xml_cell($replicate)];
			foreach ($stages as $stage) {
				foreach (stage_values($group['rows'][$stage][$replicate] ?? null, $stage) as $value) {
					$cells[] = excel_xml_cell(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
				}
			}
			echo excel_xml_row($cells);
		}
		$cells = [excel_xml_cell(''), excel_xml_cell('Ave.')];
		foreach ($stages as $stage) {
			foreach (stage_columns($stage) as $columnIndex => $column) {
				$cells[] = excel_xml_cell(stage_average($group['rows'][$stage] ?? [], $stage, $columnIndex));
			}
		}
		echo excel_xml_row($cells);
	}
	echo '</Table></Worksheet>';
}
echo '</Workbook>';
