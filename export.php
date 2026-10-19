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

// Sheet-exact date row: one date cell centered under each stage block,
// e.g. 05/07/24 under 01 GRADING, 07/23/2024 under 02/03/04, 12/01/204
// style harvest date under HARVEST. Blank dates export as ''.
function export_stage_date_row(array $stages, array $headerLabels, array $stageDates): array
{
    $row = array_fill(0, count($headerLabels), '');
    $pos = 2;
    foreach ($stages as $stage) {
        $cols = stage_columns($stage);
        $date = $stageDates[normalize_stage_name($stage)] ?? '';
        if ($date !== '' && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) {
            $date = $m[2] . '/' . $m[3] . '/' . substr($m[1], 2);
        }
        if ($date !== '' && count($cols) > 0) {
            $row[$pos] = $date;
        }
        $pos += count($cols);
    }
    return $row;
}

$exportFormat = strtolower(trim((string) ($_GET['format'] ?? '')));
$isTemplate = isset($_GET['template']) && (string) $_GET['template'] !== '' && (string) $_GET['template'] !== '0';
if ($isTemplate) {
    $varieties = $db->query('SELECT code, name FROM varieties WHERE active=1 ORDER BY code')->fetchAll();
    $headerLabels = export_header_labels($stages);
    $fileBase = 'philsurin-import-template';
    // Sheet-exact: stage blocks carry their field dates (01 = 05/07/24,
    // 02/03/04 = 07/23/2024, Harvest = 12/01/204 in the photo).
    $templateDates = ['01 GRADING' => '05/07/24', '02 GRADING' => '07/23/2024', '03 GRADING' => '07/23/2024', '04 GRADING' => '07/23/2024', 'HARVEST' => ''];
    if ($exportFormat === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fileBase . '.csv"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Location ID', '', 'Location of Project', 'TYPE PROJECT HERE']);
        fputcsv($out, ['DR / DP', '', 'Date Planted', '', 'Control', '', 'Size of Field', '']);
        // Stage-label row then date row then field row — mirrors the photo.
        $stageLabelRow = array_fill(0, count($headerLabels), '');
        $pos = 2;
        foreach ($stages as $stage) {
            $n = count(stage_columns($stage));
            for ($k = 0; $k < $n; $k++) { $stageLabelRow[$pos + $k] = $k === 0 ? $stage : ''; }
            $pos += $n;
        }
        fputcsv($out, $stageLabelRow);
        fputcsv($out, export_stage_date_row($stages, $headerLabels, $templateDates));
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
            // Ave. row per variety exactly like the photo.
            $aveRow = array_fill(0, count($headerLabels), '');
            $aveRow[1] = 'Ave.';
            fputcsv($out, $aveRow);
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
        echo excel_xml_row([excel_xml_cell('DP: January 18, 2024', 'hdr'), excel_xml_cell('Date Planted', 'hdr'), excel_xml_cell('', 'hdr'), excel_xml_cell('Control', 'hdr'), excel_xml_cell('', 'hdr'), excel_xml_cell('Size of Field', 'hdr'), excel_xml_cell('')]);
        // Stage block row (merged, like photo: 01 GRADING / 02 GRADING / ...)
        $stageBlock = [excel_xml_cell('Variety', 'hdr'), excel_xml_cell('Rep', 'hdr')];
        foreach ($stages as $stage) {
            $mergeAcross = max(0, count(stage_columns($stage)) - 1);
            $stageBlock[] = '<Cell ss:MergeAcross="' . $mergeAcross . '" ss:StyleID="hdr"><Data ss:Type="String">' . htmlspecialchars($stage, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
        }
        echo excel_xml_row($stageBlock);
        // Date row under each block (photo: 05/07/24, 07/23/2024, ...).
        $dateCells = [excel_xml_cell('', 'hdr'), excel_xml_cell('', 'hdr')];
        foreach ($stages as $stage) {
            $mergeAcross = max(0, count(stage_columns($stage)) - 1);
            $d = $templateDates[normalize_stage_name($stage)] ?? '';
            $dateCells[] = '<Cell ss:MergeAcross="' . $mergeAcross . '" ss:StyleID="hdr"><Data ss:Type="String">' . htmlspecialchars($d, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
        }
        echo excel_xml_row($dateCells);
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
            // Ave. row per variety exactly like the photo.
            $ave = [excel_xml_cell(''), excel_xml_cell('Ave.')];
            while (count($ave) < count($headerLabels)) { $ave[] = excel_xml_cell(''); }
            echo excel_xml_row($ave);
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
		fputcsv($out, ['DP: ' . ($first['dr_dp'] ?? ''), 'Date Planted', $first['date_planted'] ?? '', 'Control', $first['control_name'] ?? '', 'Size of Field', $first['location_size'] ?? '']);
		// Sheet-exact: stage block row + date row before the field header row.
		$stageDates = [];
		foreach ($stages as $stage) {
		    foreach ($locationRows as $lr) {
		        if (normalize_stage_name($lr['grading_stage'] ?? '') === normalize_stage_name($stage)) {
		            if (!empty($lr['grading_date'])) { $stageDates[normalize_stage_name($stage)] = $lr['grading_date']; break; }
		        }
		    }
		}
		$stageLabelRow = array_fill(0, count($headerLabels), '');
		$pos = 2;
		foreach ($stages as $stage) {
		    $n = count(stage_columns($stage));
		    for ($k = 0; $k < $n; $k++) { $stageLabelRow[$pos + $k] = $k === 0 ? $stage : ''; }
		    $pos += $n;
		}
		fputcsv($out, $stageLabelRow);
		fputcsv($out, export_stage_date_row($stages, $headerLabels, $stageDates));
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
	echo excel_xml_row([excel_xml_cell('DP: ' . ($first['dr_dp'] ?? ''), 'hdr'), excel_xml_cell('Date Planted', 'hdr'), excel_xml_cell($first['date_planted'] ?? ''), excel_xml_cell('Control', 'hdr'), excel_xml_cell($first['control_name'] ?? ''), excel_xml_cell('Size of Field', 'hdr'), excel_xml_cell($first['location_size'] ?? '')]);
	// Per-stage real dates + sizes (photo: 05/07/24 under 01, 07/23/2024 under 02/03/04).
	$stageDates = [];
	foreach ($stages as $stage) {
	    foreach ($locationRows as $lr) {
	        if (normalize_stage_name($lr['grading_stage'] ?? '') === normalize_stage_name($stage)) {
	            if (!empty($lr['grading_date'])) { $stageDates[normalize_stage_name($stage)] = $lr['grading_date']; break; }
	        }
	    }
	}
	$stageHeader = [excel_xml_cell('Variety', 'hdr'), excel_xml_cell('Rep', 'hdr')];
	foreach ($stages as $stage) {
		$columns = stage_columns($stage);
		$label = $stage;
		$mergeAcross = max(0, count($columns) - 1);
		$stageHeader[] = '<Cell ss:MergeAcross="' . $mergeAcross . '" ss:StyleID="hdr"><Data ss:Type="String">' . htmlspecialchars($label, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
	}
	echo excel_xml_row($stageHeader);
	// Date row under each block (photo: 05/07/24, 07/23/2024, ...).
	$dateCells = [excel_xml_cell('', 'hdr'), excel_xml_cell('', 'hdr')];
	foreach ($stages as $stage) {
	    $mergeAcross = max(0, count(stage_columns($stage)) - 1);
	    $raw = $stageDates[normalize_stage_name($stage)] ?? '';
	    if ($raw !== '' && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m)) {
	        $raw = $m[2] . '/' . $m[3] . '/' . substr($m[1], 2);
	    }
	    $dateCells[] = '<Cell ss:MergeAcross="' . $mergeAcross . '" ss:StyleID="hdr"><Data ss:Type="String">' . htmlspecialchars($raw, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
	}
	echo excel_xml_row($dateCells);
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
