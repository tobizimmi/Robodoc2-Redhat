<?php
declare(strict_types=1);

class ExportController {
    public static function entries(): void {
        Auth::requireView('export');

        [$accessSql, $accessParams] = Auth::entryAccessClause('e');

        // Sort order — defined early so it's available in all query paths
        $sortMap = [
            'date_desc'  => 'e.entry_date DESC, e.id DESC',
            'date_asc'   => 'e.entry_date ASC, e.id ASC',
            'title_asc'  => 'e.title ASC',
            'status'     => 'e.status ASC, e.entry_date DESC',
        ];
        $sort = $sortMap[$_GET['sort'] ?? 'date_desc'] ?? 'e.entry_date DESC, e.id DESC';

        $baseQuery = "SELECT e.id, e.entry_date, e.entry_time, e.title, e.description,
                    e.status, e.priority, e.parent_id, e.epic_id,
                    e.jira_issue_key, e.jira_issue_url, e.jira_status,
                    e.zentao_bug_id, e.zentao_bug_url, e.zentao_status,
                    e.firmware_version, e.app_version, e.mower_serial, e.project_status_robot,
                    e.temperature, e.weather_condition, e.gps_lat, e.gps_lon,
                    et.name type_name, ec.name cat_name, p.name project_name,
                    ep.title epic_title,
                    pe.title parent_title,
                    u.name creator,
                    (SELECT GROUP_CONCAT(t.name SEPARATOR ', ') FROM entry_tags etg
                     JOIN tags t ON t.id = etg.tag_id WHERE etg.entry_id = e.id) AS tag_names
             FROM entries e
             LEFT JOIN entry_types et      ON et.id = e.entry_type_id
             LEFT JOIN error_categories ec ON ec.id = e.error_category_id
             LEFT JOIN projects p          ON p.id  = e.project_id
             LEFT JOIN users u             ON u.id  = e.created_by
             LEFT JOIN epics ep            ON ep.id = e.epic_id
             LEFT JOIN entries pe          ON pe.id = e.parent_id";

        if (!empty($_GET['ids'])) {
            $rawIds = array_values(array_filter(array_map('intval', explode(',', $_GET['ids']))));
            if ($rawIds) {
                $ph      = implode(',', array_fill(0, count($rawIds), '?'));
                $entries = Database::fetchAll("$baseQuery WHERE e.id IN ($ph) AND $accessSql ORDER BY $sort", array_merge($rawIds, $accessParams));
            } else {
                $entries = [];
            }
        } else {
            $where  = [$accessSql];
            $params = $accessParams;
            if (isset($_GET['project_id']) && is_numeric($_GET['project_id'])) {
                $where[] = 'e.project_id=?'; $params[] = (int)$_GET['project_id'];
            }
            if (!empty($_GET['date_from'])) { $where[] = 'e.entry_date >= ?'; $params[] = $_GET['date_from']; }
            if (!empty($_GET['date_to']))   { $where[] = 'e.entry_date <= ?'; $params[] = $_GET['date_to']; }
            // Apply _f_* column filters
            $colFilterMap = [
                'status'   => ['e.status',   'like'],
                'priority'  => ['e.priority',  'like'],
                'type'      => ['et.name',     'like'],
                'category'  => ['ec.name',     'like'],
                'project'   => ['p.name',      'like'],
                'creator'   => ['u.name',      'like'],
                'serial'    => ['e.mower_serial',     'like'],
                'firmware'  => ['e.firmware_version', 'like'],
                'jira'      => ['e.jira_issue_key',   'like'],
            ];
            foreach ($colFilterMap as $colKey => [$sqlExpr, $_]) {
                $rawVal = trim($_GET['_f_'.$colKey] ?? '');
                if ($rawVal === '') continue;
                $terms = array_filter(array_map('trim', preg_split('/[,;]/', $rawVal)));
                if (empty($terms)) continue;
                $clauses = array_map(fn($t) => "$sqlExpr LIKE ?", $terms);
                $where[] = '(' . implode(' OR ', $clauses) . ')';
                foreach ($terms as $t) $params[] = "%$t%";
            }
            $wStr    = implode(' AND ', $where);
            $entries = Database::fetchAll("$baseQuery WHERE $wStr ORDER BY $sort", $params);
        }

        $format = $_GET['format'] ?? 'csv';

        // Available columns definition
        $allCols = [
            'id'                  => 'ID',
            'epic'                => 'Epic',
            'parent_title'        => 'Parent Ticket',
            'is_sub'              => 'Sub-Ticket',
            'entry_date'          => 'Datum',
            'entry_time'          => 'Zeit',
            'title'               => 'Titel',
            'status'              => 'Status',
            'priority'            => 'Priorität',
            'type_name'           => 'Typ',
            'cat_name'            => 'Kategorie',
            'project_name'        => 'Projekt',
            'mower_serial'        => 'Seriennummer',
            'firmware_version'    => 'Firmware',
            'app_version'         => 'App Version',
            'creator'             => 'Ersteller',
            'description'         => 'Beschreibung',
            'tags'                => 'Tags',
            'jira_issue_key'      => 'Jira Key',
            'jira_issue_url'      => 'Jira URL',
            'jira_status'         => 'Jira Status',
            'zentao_bug_id'       => 'Zentao ID',
            'zentao_bug_url'      => 'Zentao URL',
            'zentao_status'       => 'Zentao Status',
            'project_status_robot'=> 'Robot Status',
            'temperature'         => 'Temperatur',
            'weather_condition'   => 'Wetter',
            'gps_lat'             => 'GPS Lat',
            'gps_lon'             => 'GPS Lon',
            'sharepoint_url'      => 'Sharepoint Link',
        ];

        // Selected columns from wizard (default: all except description)
        $defaultCols = ['id','epic','parent_title','is_sub','entry_date','title',
                        'status','priority','type_name','cat_name','project_name',
                        'mower_serial','firmware_version','jira_issue_key','zentao_bug_id','creator'];
        $selectedCols = isset($_GET['cols']) && $_GET['cols']
            ? array_intersect(array_map('trim', explode(',', $_GET['cols'])), array_keys($allCols))
            : $defaultCols;
        if (empty($selectedCols)) $selectedCols = $defaultCols;

        // Description truncation
        $truncDesc     = ($_GET['trunc_desc']     ?? '1') === '1';
        $includeImages = ($_GET['include_images'] ?? '1') === '1';

        $headers = array_values(array_map(fn($k) => $allCols[$k], $selectedCols));

        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="robodoc-export-' . date('Y-m-d') . '.csv"');
            echo "\xEF\xBB\xBF";
            $out = fopen('php://output', 'wb');
            fputcsv($out, $headers, ';');
            foreach ($entries as $e) {
                fputcsv($out, self::entryRow($e, $selectedCols, $truncDesc), ';');
            }
            fclose($out);
            exit;
        }

        if ($format === 'xlsx') {
            self::exportXlsx($entries, $headers, $selectedCols, $truncDesc, $includeImages);
            exit;
        }

        if ($format === 'pdf') {
            self::exportPdf($entries);
            exit;
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="robodoc-export-' . date('Y-m-d') . '.json"');
        echo json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Sort entries into tree: Epic headers → epic entries → sub-tickets
    private static function sortTreeOrder(array $entries): array
    {
        $byEpic   = [];
        $noEpic   = [];
        $epicMeta = [];
        $subMap   = [];
        foreach ($entries as $e) {
            if (!empty($e['parent_id'])) { $subMap[$e['parent_id']][] = $e; continue; }
            if (!empty($e['epic_id'])) {
                $byEpic[$e['epic_id']][] = $e;
                if (!isset($epicMeta[$e['epic_id']]))
                    $epicMeta[$e['epic_id']] = $e['epic_title'] ?? 'Epic';
            } else { $noEpic[] = $e; }
        }
        $result = [];
        $add = function(array $e, int $lvl) use (&$result, &$subMap, &$add) {
            $e['_level']    = $lvl;
            $e['_has_subs'] = !empty($subMap[$e['id']]);
            $result[] = $e;
            foreach ($subMap[$e['id']] ?? [] as $sub) {
                $sub['_level']    = $lvl + 1;
                $sub['_has_subs'] = false;
                $result[] = $sub;
            }
        };
        foreach ($epicMeta as $epicId => $epicTitle) {
            $result[] = ['_epic_header' => true, '_level' => 0, '_has_subs' => false,
                         'title' => $epicTitle, 'epic_id' => $epicId, 'id' => null];
            foreach ($byEpic[$epicId] as $e) $add($e, 1);
        }
        foreach ($noEpic as $e) $add($e, 1);
        return $result;
    }

    private static function entryRow(array $e, array $selectedCols = [], bool $truncDesc = true): array {
        if (empty($selectedCols)) {
            $selectedCols = ['id','epic','parent_title','is_sub','entry_date','entry_time','title',
                             'status','priority','type_name','cat_name','project_name',
                             'mower_serial','firmware_version','creator'];
        }
        $map = [
            'id'                   => $e['id'],
            'epic'                 => $e['epic_title'] ?? '',
            'parent_title'         => $e['parent_title'] ?? '',
            'is_sub'               => !empty($e['parent_id']) ? 'Ja' : '',
            'entry_date'           => $e['entry_date'],
            'entry_time'           => substr($e['entry_time'] ?? '', 0, 5),
            'title'                => $e['title'],
            'status'               => $e['status'] ?? '',
            'priority'             => $e['priority'] ?? '',
            'type_name'            => $e['type_name'] ?? '',
            'cat_name'             => $e['cat_name'] ?? '',
            'project_name'         => $e['project_name'] ?? '',
            'mower_serial'         => $e['mower_serial'] ?? '',
            'firmware_version'     => $e['firmware_version'] ?? '',
            'app_version'          => $e['app_version'] ?? '',
            'creator'              => $e['creator'] ?? '',
            'description'          => $truncDesc
                ? mb_substr($e['description'] ?? '', 0, 300)
                : ($e['description'] ?? ''),
            'tags'                 => $e['tag_names'] ?? '',
            'jira_issue_key'       => $e['jira_issue_key'] ?? '',
            'jira_issue_url'       => $e['jira_issue_url'] ?? '',
            'jira_status'          => $e['jira_status'] ?? '',
            'zentao_bug_id'        => $e['zentao_bug_id'] ? (string)$e['zentao_bug_id'] : '',
            'zentao_bug_url'       => $e['zentao_bug_url'] ?? '',
            'zentao_status'        => $e['zentao_status'] ?? '',
            'project_status_robot' => $e['project_status_robot'] ?? '',
            'temperature'          => $e['temperature'] ?? '',
            'weather_condition'    => $e['weather_condition'] ?? '',
            'gps_lat'              => $e['gps_lat'] ?? '',
            'gps_lon'              => $e['gps_lon'] ?? '',
            'sharepoint_url'       => '', // column not in DB yet
        ];
        return array_values(array_map(fn($k) => $map[$k] ?? '', $selectedCols));
    }

    private static function exportXlsx(array $entries, array $headers, array $selectedCols = [], bool $truncDesc = true, bool $includeImages = true): void
    {
        if (!class_exists('ZipArchive')) {
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="robodoc-export-' . date('Y-m-d') . '.csv"');
            echo "\xEF\xBB\xBF";
            $out = fopen('php://output', 'wb');
            fputcsv($out, $headers, ';');
            foreach ($entries as $e) { fputcsv($out, self::entryRow($e, $selectedCols, $truncDesc), ';'); }
            fclose($out);
            return;
        }

        // Fetch image attachments
        $entryIds = array_column($entries, 'id');
        $imageMap = [];
        if ($includeImages && $entryIds) {
            $ph   = implode(',', array_fill(0, count($entryIds), '?'));
            $atts = Database::fetchAll(
                "SELECT entry_id, file_path, mime_type FROM entry_attachments
                 WHERE entry_id IN ($ph) AND mime_type IN ('image/jpeg','image/png','image/gif')
                 ORDER BY entry_id, created_at",
                $entryIds
            );
            foreach ($atts as $att) {
                $eid = $att['entry_id'];
                if (!isset($imageMap[$eid]) && file_exists($att['file_path'])) {
                    $ext = match($att['mime_type']) {
                        'image/jpeg' => 'jpeg', 'image/png' => 'png', 'image/gif' => 'gif', default => 'jpg'
                    };
                    $imageMap[$eid] = ['file_path' => $att['file_path'], 'mime_type' => $att['mime_type'], 'ext' => $ext];
                }
            }
        }

        $hasImages = !empty($imageMap);
        $cols = $headers;
        if ($hasImages) $cols[] = 'Photo';
        $imgColIdx = count($cols) - 1;

        // Build tree-ordered rows
        $treeEntries = self::sortTreeOrder($entries);
        $rows = [];
        foreach ($treeEntries as $e) {
            if (!empty($e['_epic_header'])) {
                $row = array_fill(0, count($selectedCols), '');
                $row[0] = '⚡ ' . ($e['title'] ?? 'Epic');
                $rows[] = $row;
            } else {
                $row = self::entryRow($e, $selectedCols, $truncDesc);
                if (($e['_level'] ?? 1) >= 2) {
                    $ti = array_search('title', $selectedCols);
                    if ($ti !== false) $row[$ti] = '    ↳ ' . ($row[$ti] ?? '');
                }
                $rows[] = $row;
            }
        }

        // Shared strings
        $strs   = [];
        $strIdx = function(string $s) use (&$strs): int {
            $key = array_search($s, $strs, true);
            if ($key === false) { $strs[] = $s; return count($strs) - 1; }
            return $key;
        };

        // Build sheet rows
        $rowNum    = 1;
        $allRows   = array_merge([$cols], $rows);
        $sheetRows = '';
        $imgRows   = [];

        foreach ($allRows as $rIdx => $row) {
            $te        = $rIdx > 0 ? ($treeEntries[$rIdx - 1] ?? null) : null;
            $entryId   = $te ? ($te['id'] ?? null) : null;
            $rowHasImg = $entryId && isset($imageMap[$entryId]);
            if ($rowHasImg) $imgRows[$rowNum] = $entryId;
            // Style: 0=normal,1=col-header,2=epic-header,3=sub-ticket,4=parent-with-subs
            if ($rIdx === 0)                              { $style = 1; $ol = 0; $coll = ''; }
            elseif (!empty($te['_epic_header']))          { $style = 2; $ol = 0; $coll = ''; }
            elseif (($te['_level'] ?? 1) >= 2)           { $style = 3; $ol = 2; $coll = ''; }
            elseif (!empty($te['_has_subs']))             { $style = 4; $ol = 1; $coll = ''; }
            elseif (!empty($te['epic_id']))               { $style = 0; $ol = 1; $coll = ''; }
            else                                          { $style = 0; $ol = 0; $coll = ''; }
            $rowHeight = $rowHasImg ? ' ht="80" customHeight="1"' : '';
            $olAttr    = $ol > 0 ? ' outlineLevel="'.$ol.'"'.$coll : '';
            $sheetRows .= '<row r="'.$rowNum.'"'.$rowHeight.$olAttr.'>';
            $colIdx = 0;
            foreach ($row as $cell) {
                $col = self::colLetter($colIdx);
                $ref = $col . $rowNum;
                $si  = $strIdx((string)($cell ?? ''));
                $sheetRows .= '<c r="'.$ref.'" t="s" s="'.$style.'"><v>'.$si.'</v></c>';
                $colIdx++;
            }
            if ($hasImages && $rIdx > 0) {
                $col = self::colLetter($imgColIdx);
                $sheetRows .= '<c r="'.$col.$rowNum.'" t="s"><v>'.$strIdx('').'</v></c>';
            }
            $sheetRows .= '</row>';
            $rowNum++;
        }

        $sharedStringsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($strs) . '" uniqueCount="' . count($strs) . '">';
        foreach ($strs as $s) {
            $sharedStringsXml .= '<si><t xml:space="preserve">' . htmlspecialchars($s, ENT_XML1) . '</t></si>';
        }
        $sharedStringsXml .= '</sst>';

        // Minimal styles.xml ? required by Excel
        $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="3">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="5">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF1E3A5F"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFE8F0FF"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF5F5F5"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="5">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            . '<xf numFmtId="0" fontId="0" fillId="3" borderId="0" xfId="0" applyFill="1"/>'
            . '<xf numFmtId="0" fontId="1" fillId="4" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            . '</cellXfs>'
            . '</styleSheet>';

        // Drawing
        $drawingXml = $drawingRelsXml = $sheetRelsXml = '';
        $drawingRels = [];
        $contentTypeOverrides = '';
        $hasDrawing = $hasImages && !empty($imgRows);

        if ($hasDrawing) {
            $maxEmu = 1016000;
            $drawingAnchors = '';
            $imgCounter = 0;
            foreach ($imgRows as $rNum => $eid) {
                $imgInfo = $imageMap[$eid];
                $imgCounter++;
                $rId     = 'rId' . $imgCounter;
                $imgName = 'image' . $imgCounter . '.jpeg';
                [$imgW, $imgH] = @getimagesize($imgInfo['file_path']) ?: [1,1];
                if ($imgW <= 0) $imgW = 1;
                if ($imgH <= 0) $imgH = 1;
                if ($imgW >= $imgH) { $cx = $maxEmu; $cy = (int)round($maxEmu * $imgH / $imgW); }
                else { $cy = $maxEmu; $cx = (int)round($maxEmu * $imgW / $imgH); }
                $drawingRels[] = ['id'=>$rId, 'target'=> '../media/'.$imgName,
                    'type'=> 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image',
                    'file'=> $imgInfo['file_path'], 'dest'=> 'xl/media/'.$imgName, 'mime'=> $imgInfo['mime_type']];
                $drawingAnchors .= '<xdr:oneCellAnchor>'
                    . '<xdr:from><xdr:col>'.$imgColIdx.'</xdr:col><xdr:colOff>0</xdr:colOff>'
                    . '<xdr:row>'  .($rNum-1).'</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:from>'
                    . '<xdr:ext cx="'.$cx.'" cy="'.$cy.'"/>'
                    . '<xdr:pic><xdr:nvPicPr>'
                    . '<xdr:cNvPr id="'  .($imgCounter+1).'" name="Image'.$imgCounter.'"/>'
                    . '<xdr:cNvPicPr><a:picLocks noChangeAspect="1"/></xdr:cNvPicPr></xdr:nvPicPr>'
                    . '<xdr:blipFill><a:blip r:embed="'.$rId.'"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>'
                    . '<xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="'.$cx.'" cy="'.$cy.'"/></a:xfrm>'
                    . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr></xdr:pic>'
                    . '<xdr:clientData/></xdr:oneCellAnchor>';
            }
            $drawingXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing"'
                . ' xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"'
                . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . $drawingAnchors . '</xdr:wsDr>';
            $drb = '';
            foreach ($drawingRels as $rel) {
                $drb .= '<Relationship Id="'.$rel['id'].'" Type="'.$rel['type'].'" Target="'.$rel['target'].'"/>';
            }
            $drawingRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$drb.'</Relationships>';
            $sheetRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>'
                . '</Relationships>';
            $contentTypeOverrides = '<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>';
        }

        $drawingRef = $hasDrawing ? '<drawing r:id="rId2"/>' : '';

        // Build column widths
        $colWidthMap = [
            'id'                   => 8,
            'epic'                 => 20,
            'parent_title'         => 30,
            'is_sub'               => 10,
            'entry_date'           => 12,
            'entry_time'           => 8,
            'title'                => 40,
            'status'               => 16,
            'priority'             => 12,
            'type_name'            => 16,
            'cat_name'             => 16,
            'project_name'         => 20,
            'mower_serial'         => 18,
            'firmware_version'     => 14,
            'app_version'          => 14,
            'creator'              => 14,
            'description'          => 60,
            'tags'                 => 20,
            'jira_issue_key'       => 14,
            'jira_issue_url'       => 40,
            'jira_status'          => 16,
            'zentao_bug_id'        => 12,
            'zentao_bug_url'       => 40,
            'zentao_status'        => 16,
            'project_status_robot' => 16,
            'temperature'          => 12,
            'weather_condition'    => 16,
            'gps_lat'              => 12,
            'gps_lon'              => 12,
            'sharepoint_url'       => 50,
        ];
        $colsXml = '<cols>';
        foreach ($selectedCols as $ci => $colKey) {
            $w = $colWidthMap[$colKey] ?? 16;
            $n = $ci + 1;
            $colsXml .= '<col min="' . $n . '" max="' . $n . '" width="' . $w . '" bestFit="1" customWidth="1"/>';
        }
        if ($hasImages) {
            $n = count($selectedCols) + 1;
            $colsXml .= '<col min="' . $n . '" max="' . $n . '" width="20" customWidth="1"/>';
        }
        $colsXml .= '</cols>';

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetViews><sheetView workbookViewId="0">'
            . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
            . '</sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15" outlineLevelRow="2"/>'
            . $colsXml
            . '<sheetData>' . $sheetRows . '</sheetData>'
            . $drawingRef . '</worksheet>';

        $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Entries" sheetId="1" r:id="rId1"/></sheets></workbook>';

        $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';

        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Default Extension="jpeg" ContentType="image/jpeg"/>'
            . '<Default Extension="png" ContentType="image/png"/>'
            . '<Default Extension="gif" ContentType="image/gif"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . $contentTypeOverrides
            . '</Types>';

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml',          $contentTypes);
        $zip->addFromString('_rels/.rels',                  $rels);
        $zip->addFromString('xl/workbook.xml',              $workbookXml);
        $zip->addFromString('xl/_rels/workbook.xml.rels',   $workbookRels);
        $zip->addFromString('xl/worksheets/sheet1.xml',     $sheetXml);
        $zip->addFromString('xl/sharedStrings.xml',         $sharedStringsXml);
        $zip->addFromString('xl/styles.xml',                $stylesXml);
        if ($hasDrawing) {
            $zip->addFromString('xl/drawings/drawing1.xml',              $drawingXml);
            $zip->addFromString('xl/drawings/_rels/drawing1.xml.rels',   $drawingRelsXml);
            $zip->addFromString('xl/worksheets/_rels/sheet1.xml.rels',   $sheetRelsXml);
            foreach ($drawingRels as $rel) {
                if (file_exists($rel['file'])) {
                    $resized = self::resizeImageForEmbed($rel['file'], $rel['mime'], 600, 400);
                    $zip->addFromString($rel['dest'], $resized ?? file_get_contents($rel['file']));
                }
            }
        }
        $zip->close();

        // Flush all output buffers before sending binary file
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="robodoc-export-' . date('Y-m-d') . '.xlsx"');
        header('Content-Length: ' . filesize($tmp));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: public');
        readfile($tmp);
        unlink($tmp);
        exit;
    }

    private static function resizeImageForEmbed(string $path, string $mime, int $maxW, int $maxH): ?string
    {
        if (!extension_loaded('gd')) return null;
        @ini_set('memory_limit', '256M');
        $img = match(true) {
            in_array($mime, ['image/jpeg','image/heic','image/heif']) => @imagecreatefromjpeg($path),
            $mime === 'image/png'  => @imagecreatefrompng($path),
            $mime === 'image/gif'  => @imagecreatefromgif($path),
            $mime === 'image/webp' => @imagecreatefromwebp($path),
            default => false,
        };
        if (!$img) return null;
        $w = imagesx($img); $h = imagesy($img);
        $ratio = min($maxW / max($w,1), $maxH / max($h,1), 1.0);
        $nw = max(1,(int)round($w*$ratio)); $nh = max(1,(int)round($h*$ratio));
        $out = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($out, $img, 0,0,0,0, $nw,$nh,$w,$h);
        imagedestroy($img);
        ob_start(); imagejpeg($out, null, 82); imagedestroy($out);
        return ob_get_clean() ?: null;
    }

    private static function colLetter(int $idx): string
    {
        $letter = '';
        do { $letter = chr(65+($idx%26)).$letter; $idx = intdiv($idx,26)-1; } while ($idx >= 0);
        return $letter;
    }

    private static function exportPdf(array $entries): void
    {
        $appName = appSetting('app_name', 'RoboDoc');
        $date    = date('d.m.Y H:i');
        $count   = count($entries);

        $imageMap = [];
        $entryIds = array_column($entries, 'id');
        if ($entryIds) {
            $ph   = implode(',', array_fill(0, count($entryIds), '?'));
            $atts = Database::fetchAll(
                "SELECT entry_id, file_path, mime_type FROM entry_attachments
                 WHERE entry_id IN ($ph) AND mime_type IN ('image/jpeg','image/png','image/gif','image/webp')
                 ORDER BY entry_id, created_at",
                $entryIds
            );
            foreach ($atts as $att) {
                $eid = $att['entry_id'];
                if (!isset($imageMap[$eid])) $imageMap[$eid] = [];
                if (count($imageMap[$eid]) >= 4 || !file_exists($att['file_path'])) continue;
                $resized = self::resizeImageForEmbed($att['file_path'], $att['mime_type'], 800, 600);
                if ($resized !== null) {
                    $imageMap[$eid][] = 'data:image/jpeg;base64,' . base64_encode($resized);
                } else {
                    $raw = @file_get_contents($att['file_path']);
                    if ($raw !== false) $imageMap[$eid][] = 'data:'.$att['mime_type'].';base64,'.base64_encode($raw);
                }
            }
        }
        $hasImages = !empty($imageMap);

        header('Content-Type: text/html; charset=utf-8');
        ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Entry Export - <?= htmlspecialchars($appName) ?></title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, sans-serif; font-size: 11px; color: #111; background: #fff; }
  h1 { font-size: 16px; margin-bottom: 4px; }
  .meta { font-size: 10px; color: #666; margin-bottom: 16px; }
  table { width: 100%; border-collapse: collapse; font-size: 10px; }
  th { background: #1a1a2e; color: #fff; padding: 5px 6px; text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: .04em; }
  td { padding: 5px 6px; border-bottom: 1px solid #e5e5e5; vertical-align: top; }
  tr:nth-child(even) td { background: #f9f9f9; }
  .desc { color: #444; }
  .thumb { width: 176px; min-width: 176px; }
  .photo-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2px; }
  .photo-grid img { width: 100%; height: 60px; object-fit: cover; border-radius: 2px; display: block; }
  .sub-row td { background: #e8f0ff !important; padding-left: 28px; }
  .parent-row td { background: #f5f5f5 !important; font-weight: 600; }
  @media print {
    @page { size: A4 landscape; margin: 12mm; }
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .no-print { display: none; }
    tr { page-break-inside: avoid; }
  }
</style>
</head>
<body>
<div class="no-print" style="padding:12px;background:#1a1a2e;color:#fff;display:flex;align-items:center;gap:12px;margin-bottom:16px">
  <strong><?= htmlspecialchars($appName) ?> - Entry Export (<?= $count ?> entries)</strong>
  <button onclick="window.print()" style="margin-left:auto;padding:6px 16px;background:#6366f1;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:12px">Save as PDF</button>
  <button onclick="window.close()" style="padding:6px 12px;background:#374151;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:12px">X</button>
</div>
<div style="padding:0 12px 12px">
  <h1><?= htmlspecialchars($appName) ?> - Entry Export</h1>
  <p class="meta">Exported on <?= $date ?> &middot; <?= $count ?> entries</p>
  <table>
    <thead>
      <tr>
        <th>Epic</th>
        <th>Sub?</th>
        <th style="width:56px">Date</th>
        <th style="width:65px">Type</th>
        <th style="width:65px">Status</th>
        <th style="width:65px">Priority</th>
        <th style="width:120px">Title</th>
        <th class="desc">Description</th>
        <th style="width:90px">Project</th>
        <th style="width:75px">Serial No.</th>
        <th style="width:62px">Firmware</th>
        <th style="width:60px">Creator</th>
        <?php if ($hasImages): ?><th style="width:84px">Photo</th><?php endif; ?>
      </tr>
    </thead>
    <tbody>
      <?php $treeE = self::sortTreeOrder($entries); foreach ($treeE as $e): ?>
      <?php if (!empty($e['_epic_header'])): ?>
      <tr><td colspan="12" style="background:#1e3a5f;color:#fff;font-weight:bold;padding:6px 8px">&#9889; <?= htmlspecialchars($e['title'] ?? '') ?></td></tr>
      <?php else: ?>
      <tr<?= ($e['_level']??1)>=2 ? ' class="sub-row"' : (!empty($e['_has_subs']) ? ' class="parent-row"' : '') ?>>
        <td><?= htmlspecialchars($e['epic_title'] ?? '')?></td>
        <td><?= !empty($e['parent_id']) ? '? '.htmlspecialchars($e['parent_title'] ?? '') : '' ?></td>
        <td><?= htmlspecialchars($e['entry_date']) ?></td>
        <td><?= htmlspecialchars($e['type_name'] ?? '?') ?></td>
        <td><?= htmlspecialchars($e['status'] ?? '') ?></td>
        <td><?= htmlspecialchars($e['priority'] ?? '') ?></td>
        <td><strong><?= htmlspecialchars($e['title'] ?: '?') ?></strong></td>
        <td class="desc"><?= htmlspecialchars(mb_substr($e['description'] ?? '', 0, 180)) ?></td>
        <td><?= htmlspecialchars($e['project_name'] ?? '?') ?></td>
        <td><?= htmlspecialchars($e['mower_serial'] ?? '?') ?></td>
        <td><?= htmlspecialchars($e['firmware_version'] ?? '?') ?></td>
        <td><?= htmlspecialchars($e['creator'] ?? '?') ?></td>
        <?php if ($hasImages): ?>
        <?php $uris = $imageMap[$e['id']] ?? []; ?>
        <td class="<?= $uris ? 'thumb' : '' ?>">
          <?php if ($uris): ?><div class="photo-grid"><?php foreach ($uris as $uri): ?><img src="<?= $uri ?>" alt=""><?php endforeach; ?></div><?php else: ?>?<?php endif; ?>
        </td>
        <?php endif; ?>
      </tr>
      <?php endif; ?>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<script>window.addEventListener('load', () => { if (window.location.search.includes('autoprint')) window.print(); });</script>
</body>
</html>
<?php
    }
}
