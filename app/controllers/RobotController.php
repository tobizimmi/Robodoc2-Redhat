<?php
declare(strict_types=1);

class RobotController
{
    public static function index(): void
    {
        Auth::requireView('inventory');

        [$entrySql, $entryParams] = Auth::entryAccessClause('e');
        $robots = Database::fetchAll("
            SELECT e.mower_serial,
                   COUNT(*)                                              entry_count,
                   MAX(e.entry_date)                                     last_seen,
                   MIN(e.entry_date)                                     first_seen,
                   GROUP_CONCAT(DISTINCT e.firmware_version
                       ORDER BY e.entry_date DESC SEPARATOR ', ')        firmwares
            FROM entries e
            WHERE e.mower_serial IS NOT NULL AND e.mower_serial != ''
              AND $entrySql
            GROUP BY e.mower_serial
            ORDER BY last_seen DESC
        ", $entryParams);

        [$invSql, $invParams] = self::inventoryProjectClause('inv');
        $invItems = Database::fetchAll("
            SELECT inv.serial_number, inv.name, inv.status, inv.firmware_version, inv.location
            FROM inventory_items inv
            WHERE inv.serial_number IS NOT NULL AND inv.serial_number != ''
              AND $invSql
        ", $invParams);
        $invBySerial = array_column($invItems, null, 'serial_number');

        // Also include mowers from the managed test_mowers list that have no legacy entries yet
        $managedMowers = Database::fetchAll("
            SELECT tm.serial_number,
                   COUNT(DISTINCT sm.session_id) session_count,
                   COUNT(DISTINCT em.entry_id)   test_entry_count
            FROM test_mowers tm
            LEFT JOIN session_mowers sm ON sm.mower_id = tm.id
            LEFT JOIN entry_mowers   em ON em.mower_id = tm.id
            WHERE tm.serial_number IS NOT NULL AND tm.serial_number != ''
            GROUP BY tm.serial_number
        ");
        $managedBySerial = array_column($managedMowers, null, 'serial_number');

        // Merge: add managed-only serials not already in $robots
        $existingSerials = array_column($robots, 'mower_serial');
        foreach ($managedMowers as $m) {
            if ($m['serial_number'] && !in_array($m['serial_number'], $existingSerials, true)) {
                $robots[] = [
                    'mower_serial'  => $m['serial_number'],
                    'entry_count'   => 0,
                    'last_seen'     => null,
                    'first_seen'    => null,
                    'firmwares'     => null,
                ];
            }
        }

        View::render('robots/index', compact('robots', 'invBySerial', 'managedBySerial') + ['title' => 'Robot History']);
    }

    public static function show(string $serial): void
    {
        Auth::requireView('inventory');
        $serial = urldecode($serial);

        [$entrySql, $entryParams] = Auth::entryAccessClause('e');
        $entries = Database::fetchAll("
            SELECT e.id, e.title, e.description, e.entry_date, e.entry_time,
                   e.firmware_version, e.app_version, e.status, e.is_test_entry,
                   e.jira_issue_key, e.jira_issue_url, e.jira_status,
                   et.name type_name, et.color type_color,
                   ec.name cat_name,  ec.color cat_color,
                   p.name project_name, p.color project_color,
                   u.name creator,
                   (SELECT COUNT(*) FROM entry_attachments WHERE entry_id = e.id) att_count
            FROM entries e
            LEFT JOIN entry_types et      ON et.id = e.entry_type_id
            LEFT JOIN error_categories ec ON ec.id = e.error_category_id
            LEFT JOIN projects p          ON p.id  = e.project_id
            LEFT JOIN users u             ON u.id  = e.created_by
            WHERE e.mower_serial = ? AND $entrySql
            ORDER BY e.entry_date DESC, e.entry_time DESC, e.id DESC
        ", array_merge([$serial], $entryParams));

        $invItem = Database::fetchOne(
            "SELECT * FROM inventory_items WHERE serial_number = ? LIMIT 1",
            [$serial]
        );

        $fwHistory = Database::fetchAll("
            SELECT firmware_version,
                   MIN(entry_date) first_date,
                   MAX(entry_date) last_date,
                   COUNT(*)        cnt
            FROM entries e
            WHERE e.mower_serial = ? AND e.firmware_version IS NOT NULL AND e.firmware_version != ''
              AND $entrySql
            GROUP BY firmware_version
            ORDER BY MIN(entry_date) ASC
        ", array_merge([$serial], $entryParams));

        // Load logbook entries for this serial via inventory item
        $logbook = $invItem ? Database::fetchAll(
            'SELECT il.*, u.name user_name FROM inventory_logbook il
             LEFT JOIN users u ON u.id = il.user_id
             WHERE il.item_id = ?
             ORDER BY il.log_date DESC, il.log_time DESC',
            [$invItem['id']]
        ) : [];

        // ── Test Sessions linked to this mower via serial_number ──
        $testSessions = [];
        $testRunEntries = [];
        $mowerRecord = Database::fetchOne(
            'SELECT id FROM test_mowers WHERE serial_number = ? LIMIT 1',
            [$serial]
        );
        if ($mowerRecord) {
            $mowerId = (int)$mowerRecord['id'];

            $testSessions = Database::fetchAll(
                "SELECT ts.id, ts.title, ts.description, ts.firmware_version, ts.app_version,
                        ts.temperature, ts.weather_condition, ts.status,
                        ts.started_at, ts.ended_at,
                        ta.name area_name,
                        u.name operator_name,
                        (SELECT COUNT(*) FROM entries WHERE session_id = ts.id) entry_count
                 FROM test_sessions ts
                 JOIN session_mowers sm ON sm.session_id = ts.id AND sm.mower_id = ?
                 LEFT JOIN test_areas ta ON ta.id = ts.test_area_id
                 LEFT JOIN users u       ON u.id  = ts.operator_id
                 ORDER BY ts.started_at DESC",
                [$mowerId]
            );

            // Test-run entries linked via entry_mowers, not already captured by mower_serial
            $existingEntryIds = array_column($entries, 'id');
            $extraEntries = Database::fetchAll(
                "SELECT e.id, e.title, e.description, e.entry_date, e.entry_time,
                        e.firmware_version, e.app_version, e.status, e.is_test_entry,
                        e.jira_issue_key, e.jira_issue_url, e.jira_status,
                        et.name type_name, et.color type_color,
                        ec.name cat_name,  ec.color cat_color,
                        p.name project_name, p.color project_color,
                        u.name creator,
                        tpi.title item_title,
                        tr.name run_name, tr.id run_id,
                        (SELECT COUNT(*) FROM entry_attachments WHERE entry_id = e.id) att_count
                 FROM entry_mowers em
                 JOIN entries e ON e.id = em.entry_id
                 LEFT JOIN entry_types et      ON et.id = e.entry_type_id
                 LEFT JOIN error_categories ec ON ec.id = e.error_category_id
                 LEFT JOIN projects p          ON p.id  = e.project_id
                 LEFT JOIN users u             ON u.id  = e.created_by
                 LEFT JOIN test_run_results trr ON trr.id = e.test_run_result_id
                 LEFT JOIN test_plan_items tpi  ON tpi.id = trr.test_plan_item_id
                 LEFT JOIN test_runs tr         ON tr.id  = trr.test_run_id
                 WHERE em.mower_id = ?" .
                ($existingEntryIds ? ' AND e.id NOT IN (' . implode(',', $existingEntryIds) . ')' : ''),
                [$mowerId]
            );
            $testRunEntries = $extraEntries;
        }

        // Build merged timeline
        $entriesTagged  = array_map(fn($e) => $e + ['_kind' => 'entry'],        $entries);
        $testEntTagged  = array_map(fn($e) => $e + ['_kind' => 'test_entry'],   $testRunEntries);
        $logbookTagged  = array_map(fn($l) => $l + ['_kind' => 'logbook'],      $logbook);
        $sessionsTagged = array_map(fn($s) => $s + ['_kind' => 'test_session'], $testSessions);

        $timeline = array_merge($entriesTagged, $testEntTagged, $logbookTagged, $sessionsTagged);
        usort($timeline, function ($a, $b) {
            $getDate = fn($x) => match ($x['_kind']) {
                'logbook'      => ($x['log_date']    ?? '1970-01-01') . ' ' . ($x['log_time']    ?? '00:00:00'),
                'test_session' => ($x['started_at']  ?? '1970-01-01 00:00:00'),
                default        => ($x['entry_date']  ?? '1970-01-01') . ' ' . ($x['entry_time']  ?? '00:00:00'),
            };
            return strcmp($getDate($b), $getDate($a));
        });

        $allEntries = array_merge($entries, $testRunEntries);
        $byType = [];
        foreach ($allEntries as $e) {
            if ($e['type_name']) {
                $byType[$e['type_name']] = ($byType[$e['type_name']] ?? 0) + 1;
            }
        }
        arsort($byType);

        $stats = [
            'total'        => count($allEntries),
            'open'         => count(array_filter($allEntries, fn($e) => ($e['status'] ?? '') === 'open')),
            'logbook'      => count($logbook),
            'test_sessions'=> count($testSessions),
            'by_type'      => $byType,
        ];

        View::render('robots/show', compact(
            'serial', 'entries', 'testRunEntries', 'logbook', 'testSessions',
            'timeline', 'invItem', 'fwHistory', 'stats'
        ) + ['title' => 'Robot: ' . $serial]);
    }

    public static function addLogbook(string $serial): void
    {
        Auth::requireView('inventory');
        Auth::verifyCsrf();
        $serial  = urldecode($serial);
        $invItem = Database::fetchOne("SELECT id FROM inventory_items WHERE serial_number = ? LIMIT 1", [$serial]);
        if (!$invItem) abort(404, 'No inventory item found for this serial number');

        $action = trim($_POST['action'] ?? '');
        if ($action === '') { flash('error', 'Action is required.'); redirect('/robots/' . urlencode($serial)); }

        Database::insert(
            'INSERT INTO inventory_logbook (item_id, user_id, log_date, log_time, action, description) VALUES (?,?,?,?,?,?)',
            [
                $invItem['id'],
                Auth::id(),
                $_POST['log_date'] ?? date('Y-m-d'),
                $_POST['log_time'] ?? date('H:i'),
                $action,
                trim($_POST['description'] ?? ''),
            ]
        );
        flash('success', 'Logbook entry added.');
        redirect('/robots/' . urlencode($serial));
    }

    public static function deleteLogbook(string $serial, string $lid): void
    {
        Auth::requireView('inventory');
        Auth::verifyCsrf();
        $serial  = urldecode($serial);
        $invItem = Database::fetchOne("SELECT id FROM inventory_items WHERE serial_number = ? LIMIT 1", [$serial]);
        if ($invItem) {
            Database::execute('DELETE FROM inventory_logbook WHERE id=? AND item_id=?', [(int)$lid, $invItem['id']]);
        }
        redirect('/robots/' . urlencode($serial));
    }

    public static function export(string $serial): void
    {
        Auth::requireView('inventory');
        $serial   = urldecode($serial);
        $format   = $_GET['format']  ?? 'csv';
        $include  = $_GET['include'] ?? 'both'; // 'both' | 'entries' | 'logbook'

        $withEntries = in_array($include, ['both', 'entries'], true);
        $withLogbook = in_array($include, ['both', 'logbook'], true);

        [$exportAccessSql, $exportAccessParams] = Auth::entryAccessClause('e');
        $entries = $withEntries ? Database::fetchAll("
            SELECT e.id, e.entry_date, e.entry_time, e.title, e.description,
                   et.name type_name, ec.name cat_name, p.name project_name,
                   e.firmware_version, e.app_version, e.mower_serial,
                   e.status, e.jira_issue_key, u.name creator
            FROM entries e
            LEFT JOIN entry_types et      ON et.id = e.entry_type_id
            LEFT JOIN error_categories ec ON ec.id = e.error_category_id
            LEFT JOIN projects p          ON p.id  = e.project_id
            LEFT JOIN users u             ON u.id  = e.created_by
            WHERE e.mower_serial = ? AND $exportAccessSql
            ORDER BY e.entry_date DESC, e.entry_time DESC, e.id DESC
        ", array_merge([$serial], $exportAccessParams)) : [];

        $invItem = Database::fetchOne("SELECT * FROM inventory_items WHERE serial_number = ? LIMIT 1", [$serial]);

        $logbook = ($withLogbook && $invItem) ? Database::fetchAll(
            'SELECT il.*, u.name user_name FROM inventory_logbook il
             LEFT JOIN users u ON u.id = il.user_id
             WHERE il.item_id = ? ORDER BY il.log_date DESC, il.log_time DESC',
            [$invItem['id']]
        ) : [];

        $fwHistory = $withEntries ? Database::fetchAll("
            SELECT firmware_version, MIN(entry_date) first_date, MAX(entry_date) last_date, COUNT(*) cnt
            FROM entries WHERE mower_serial = ? AND firmware_version IS NOT NULL AND firmware_version != ''
            GROUP BY firmware_version ORDER BY MIN(entry_date) ASC
        ", [$serial]) : [];

        $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $serial);
        $suffix   = $include === 'entries' ? '-entries' : ($include === 'logbook' ? '-logbook' : '');
        $filename = 'robot-' . $safeName . $suffix . '-' . date('Y-m-d');

        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
            echo "\xEF\xBB\xBF";
            $out = fopen('php://output', 'wb');
            if ($include === 'logbook') {
                fputcsv($out, ['Date','Time','Action','Description','User'], ';');
                foreach ($logbook as $l) {
                    fputcsv($out, [$l['log_date'], substr($l['log_time'],0,5), $l['action'], $l['description'] ?? '', $l['user_name'] ?? ''], ';');
                }
            } elseif ($include === 'entries') {
                fputcsv($out, ['ID','Date','Time','Title','Description','Type','Category','Project','Firmware','App Version','Status','Jira','Created By'], ';');
                foreach ($entries as $e) {
                    fputcsv($out, [$e['id'],$e['entry_date'],substr($e['entry_time']??'',0,5),$e['title'],$e['description'],$e['type_name'],$e['cat_name'],$e['project_name'],$e['firmware_version'],$e['app_version'],$e['status'],$e['jira_issue_key'],$e['creator']], ';');
                }
            } else {
                // Both: combined with Kind column
                fputcsv($out, ['Kind','Date','Time','Action / Title','Description','Type / User','Status / Firmware'], ';');
                foreach ($entries as $e) {
                    fputcsv($out, ['Entry',$e['entry_date'],substr($e['entry_time']??'',0,5),$e['title'],$e['description'],$e['type_name']??'',$e['status'].($e['firmware_version']?' / '.$e['firmware_version']:'')], ';');
                }
                foreach ($logbook as $l) {
                    fputcsv($out, ['Logbook',$l['log_date'],substr($l['log_time'],0,5),$l['action'],$l['description']??'',$l['user_name']??'',''], ';');
                }
            }
            fclose($out);
            exit;
        }

        if ($format === 'xlsx') {
            self::exportRobotXlsx($entries, $logbook, $fwHistory, $invItem, $serial, $filename, $include);
            exit;
        }

        // JSON
        $payload = ['serial' => $serial, 'inventory' => $invItem, 'export_date' => date('Y-m-d')];
        if ($withEntries) { $payload['firmware_history'] = $fwHistory; $payload['entries'] = $entries; }
        if ($withLogbook) { $payload['logbook'] = $logbook; }
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.json"');
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    private static function inventoryProjectClause(string $alias): array {
        $ids = Auth::groupProjectIds();
        if ($ids === null) return ['1=1', []];
        if (empty($ids)) return ["$alias.project_id IS NULL", []];
        $ph = implode(',', array_fill(0, count($ids), '?'));
        return ["($alias.project_id IS NULL OR $alias.project_id IN ($ph))", $ids];
    }

    private static function exportRobotXlsx(
        array $entries, array $logbook, array $fwHistory,
        ?array $invItem, string $serial, string $filename, string $include
    ): void {
        if (!class_exists('ZipArchive')) { abort(500, 'ZipArchive not available'); }

        $sharedStrings = [];
        $sharedMap     = [];
        $addStr = function(string $s) use (&$sharedStrings, &$sharedMap): int {
            if (!isset($sharedMap[$s])) { $sharedMap[$s] = count($sharedStrings); $sharedStrings[] = $s; }
            return $sharedMap[$s];
        };
        $esc = fn($v) => htmlspecialchars((string)$v, ENT_XML1);

        $buildSheet = function(array $headerRow, array $dataRows, bool $firstIsInt = false) use ($esc, &$sharedStrings, &$sharedMap): string {
            $col = fn(int $n): string => $n < 26 ? chr(65+$n) : chr(64+intdiv($n,26)).chr(65+($n%26));
            $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                 . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/sheet"><sheetData>'
                 . '<row r="1">';
            foreach ($headerRow as $ci => $h) $xml .= '<c r="'.$col($ci).'1" t="s"><v>'.($sharedMap[$h] ?? 0).'</v></c>';
            $xml .= '</row>';
            foreach ($dataRows as $ri => $row) {
                $rn = $ri + 2;
                $xml .= '<row r="'.$rn.'">';
                foreach ($row as $ci => $val) {
                    $cr = $col($ci).$rn;
                    if ($ci === 0 && $firstIsInt && is_int($val)) {
                        $xml .= '<c r="'.$cr.'"><v>'.$val.'</v></c>';
                    } else {
                        $key = (string)$val;
                        if (!isset($sharedMap[$key])) { $sharedMap[$key] = count($sharedStrings); $sharedStrings[] = $key; }
                        $xml .= '<c r="'.$cr.'" t="s"><v>'.$sharedMap[$key].'</v></c>';
                    }
                }
                $xml .= '</row>';
            }
            return $xml . '</sheetData></worksheet>';
        };

        // ── Build sheets based on $include ────────────────────────────
        $sheets    = [];
        $sheetXmls = [];

        if (in_array($include, ['both', 'entries'], true) && $entries) {
            $h = ['ID','Date','Time','Title','Description','Type','Category','Project','Firmware','App Version','Status','Jira Key','Created By'];
            foreach ($h as $s) $addStr($s);
            $rows = [];
            foreach ($entries as $e) {
                $row = [(int)$e['id'],$e['entry_date']??'',substr($e['entry_time']??'',0,5),$e['title']??'',$e['description']??'',$e['type_name']??'',$e['cat_name']??'',$e['project_name']??'',$e['firmware_version']??'',$e['app_version']??'',$e['status']??'',$e['jira_issue_key']??'',$e['creator']??''];
                $rows[] = $row;
                foreach (array_slice($row, 1) as $v) $addStr((string)$v);
            }
            $sheets[]    = 'Entries';
            $sheetXmls[] = $buildSheet($h, $rows, true);
        }

        if (in_array($include, ['both', 'logbook'], true) && $logbook) {
            $h = ['Date','Time','Action','Description','User'];
            foreach ($h as $s) $addStr($s);
            $rows = [];
            foreach ($logbook as $l) {
                $row = [$l['log_date'],substr($l['log_time'],0,5),$l['action'],$l['description']??'',$l['user_name']??''];
                $rows[] = $row;
                foreach ($row as $v) $addStr((string)$v);
            }
            $sheets[]    = 'Logbook';
            $sheetXmls[] = $buildSheet($h, $rows);
        }

        if ($fwHistory) {
            $h = ['Firmware Version','First Date','Last Date','Entry Count'];
            foreach ($h as $s) $addStr($s);
            foreach ($fwHistory as $f) foreach (['firmware_version','first_date','last_date'] as $k) $addStr((string)$f[$k]);
            $fwRows = [];
            foreach ($fwHistory as $f) $fwRows[] = [$f['firmware_version'],$f['first_date'],$f['last_date'],(int)$f['cnt']];
            $sheets[]    = 'Firmware History';
            $sheetXmls[] = $buildSheet($h, $fwRows);
        }

        // Overview sheet always
        $ovH = ['Field','Value'];
        foreach ($ovH as $s) $addStr($s);
        $overview = [
            ['Serial Number', $serial],
            ['Name',     $invItem['name'] ?? '—'],
            ['Status',   $invItem['status'] ?? '—'],
            ['Location', $invItem['location'] ?? '—'],
            ['Entries',  (string)count($entries)],
            ['Logbook',  (string)count($logbook)],
            ['Export',   date('Y-m-d')],
        ];
        foreach ($overview as $r) { $addStr($r[0]); $addStr($r[1]); }
        $sheets[]    = 'Overview';
        $sheetXmls[] = $buildSheet($ovH, array_map(fn($r) => [(string)$r[0],(string)$r[1]], $overview));

        // ── Shared strings XML ────────────────────────────────────────
        $ssXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
               . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/sheet" count="'.count($sharedStrings).'" uniqueCount="'.count($sharedStrings).'">';
        foreach ($sharedStrings as $s) $ssXml .= '<si><t xml:space="preserve">'.$esc($s).'</t></si>';
        $ssXml .= '</sst>';

        // ── Workbook XML ──────────────────────────────────────────────
        $sheetTags = $relTags = $ctTags = '';
        foreach ($sheets as $i => $name) {
            $id = $i + 1;
            $sheetTags .= '<sheet name="'.htmlspecialchars($name, ENT_XML1).'" sheetId="'.$id.'" r:id="rId'.$id.'"/>';
            $relTags   .= '<Relationship Id="rId'.$id.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$id.'.xml"/>';
            $ctTags    .= '<Override PartName="/xl/worksheets/sheet'.$id.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        $ssRelId = count($sheets) + 1;
        $relTags .= '<Relationship Id="rId'.$ssRelId.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';

        $wbXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/sheet" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$sheetTags.'</sheets></workbook>';
        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relTags.'</Relationships>';
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'.$ctTags.'<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/></Types>';
        $pkgRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';

        $tmp = tempnam(sys_get_temp_dir(), 'robot_xlsx_');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $zip->addFromString('_rels/.rels',                $pkgRels);
        $zip->addFromString('[Content_Types].xml',        $contentTypes);
        $zip->addFromString('xl/workbook.xml',            $wbXml);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);
        $zip->addFromString('xl/sharedStrings.xml',       $ssXml);
        foreach ($sheetXmls as $i => $xml) {
            $zip->addFromString('xl/worksheets/sheet'.($i+1).'.xml', $xml);
        }
        $zip->close();

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        unlink($tmp);
    }
}
