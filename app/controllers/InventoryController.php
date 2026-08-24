<?php
declare(strict_types=1);

class InventoryController {
    public static function index(): void {
        Auth::requireView('inventory');
        $projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;

        if ($projectId === null) {
            // Project selection page
            $projects = self::allowedProjects();
            foreach ($projects as &$p) {
                $p['item_count'] = (int)(Database::fetchOne('SELECT COUNT(*) c FROM inventory_items WHERE project_id=?', [$p['id']])['c'] ?? 0);
            }
            unset($p);
            $unassignedCount = (int)(Database::fetchOne('SELECT COUNT(*) c FROM inventory_items WHERE project_id IS NULL')['c'] ?? 0);
            View::render('inventory/projects', compact('projects', 'unassignedCount') + ['title' => 'Inventar']);
            return;
        }

        // Show items for the selected project (0 = unassigned)
        $project = $projectId > 0
            ? Database::fetchOne('SELECT id, name FROM projects WHERE id=?', [$projectId])
            : ['id' => 0, 'name' => 'Unassigned'];
        if (!$project && $projectId > 0) abort(404);

        $where  = $projectId > 0 ? 'inv.project_id = ?' : 'inv.project_id IS NULL';
        $params = $projectId > 0 ? [$projectId] : [];
        $items  = Database::fetchAll(
            "SELECT inv.*, p.name project_name,
                    (SELECT COUNT(*) FROM inventory_logbook WHERE item_id = inv.id) log_count
             FROM inventory_items inv
             LEFT JOIN projects p ON p.id = inv.project_id
             WHERE $where
             ORDER BY inv.name",
            $params
        );
        View::render('inventory/index', compact('items', 'project', 'projectId') + ['title' => 'Inventar – ' . $project['name']]);
    }

    public static function importCsv(): void {
        Auth::requireEdit('inventory');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $projects = self::allowedProjects();
            View::render('inventory/import', compact('projects') + ['title' => 'CSV Import']);
            return;
        }
        Auth::verifyCsrf();
        $projectId = (int)($_POST['project_id'] ?? 0) ?: null;

        if (empty($_FILES['csv']['tmp_name']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'No CSV file uploaded.');
            redirect('/inventory/import');
        }

        $handle = fopen($_FILES['csv']['tmp_name'], 'r');
        if (!$handle) { flash('error', 'Could not read file.'); redirect('/inventory/import'); }

        $headers  = null;
        $imported = 0;
        $skipped  = 0;
        $allowed  = ['name','serial_number','firmware_version','location','comment','status','purchased_at'];
        $statuses = ['available','in_use','maintenance','retired'];

        while (($row = fgetcsv($handle, 2000, ';')) !== false || ($row = fgetcsv($handle, 2000, ',')) !== false) {
            if ($headers === null) {
                $headers = array_map(fn($h) => strtolower(trim($h)), $row);
                // support semicolon as well — re-parse first line with comma if only 1 column
                if (count($headers) === 1) {
                    rewind($handle);
                    $headers = null;
                    // switch to semicolon handled by loop restart
                    break;
                }
                continue;
            }
            $row  = array_combine($headers, array_pad($row, count($headers), ''));
            $name = trim($row['name'] ?? '');
            if (!$name) { $skipped++; continue; }
            $status = in_array($row['status'] ?? '', $statuses) ? $row['status'] : 'available';
            Database::insert(
                'INSERT INTO inventory_items (project_id, name, serial_number, firmware_version, location, comment, status, purchased_at, created_by) VALUES (?,?,?,?,?,?,?,?,?)',
                [
                    $projectId,
                    $name,
                    trim($row['serial_number'] ?? ''),
                    trim($row['firmware_version'] ?? ''),
                    trim($row['location'] ?? ''),
                    trim($row['comment'] ?? ''),
                    $status,
                    trim($row['purchased_at'] ?? '') ?: null,
                    Auth::id(),
                ]
            );
            $imported++;
        }

        // Retry with semicolon separator if comma gave only 1 column
        if ($headers === null) {
            rewind($handle);
            $headers = null;
            while (($row = fgetcsv($handle, 2000, ';')) !== false) {
                if ($headers === null) {
                    $headers = array_map(fn($h) => strtolower(trim($h)), $row);
                    continue;
                }
                $row  = array_combine($headers, array_pad($row, count($headers), ''));
                $name = trim($row['name'] ?? '');
                if (!$name) { $skipped++; continue; }
                $status = in_array($row['status'] ?? '', $statuses) ? $row['status'] : 'available';
                Database::insert(
                    'INSERT INTO inventory_items (project_id, name, serial_number, firmware_version, location, comment, status, purchased_at, created_by) VALUES (?,?,?,?,?,?,?,?,?)',
                    [$projectId, $name, trim($row['serial_number'] ?? ''), trim($row['firmware_version'] ?? ''),
                     trim($row['location'] ?? ''), trim($row['comment'] ?? ''), $status,
                     trim($row['purchased_at'] ?? '') ?: null, Auth::id()]
                );
                $imported++;
            }
        }
        fclose($handle);

        flash('success', "$imported device(s) imported" . ($skipped ? ", $skipped skipped (empty name)" : '') . '.');
        redirect('/inventory' . ($projectId ? '?project_id=' . $projectId : ''));
    }

    public static function show(string $id): void {
        Auth::requireView('inventory');
        $item = self::findOr404((int)$id);
        self::checkProjectAccess($item);
        $logbook = Database::fetchAll(
            'SELECT il.*, u.name user_name FROM inventory_logbook il LEFT JOIN users u ON u.id=il.user_id WHERE il.item_id=? ORDER BY il.log_date DESC, il.log_time DESC',
            [(int)$id]
        );
        View::render('inventory/show', compact('item', 'logbook') + ['title' => $item['name']]);
    }

    public static function create(): void {
        Auth::requireEdit('inventory');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            $data = self::extractFields();
            $id = Database::insert(
                'INSERT INTO inventory_items (project_id, name, serial_number, firmware_version, location, comment, status, purchased_at, created_by) VALUES (?,?,?,?,?,?,?,?,?)',
                [$data['project_id'] ?: null, $data['name'], $data['serial_number'], $data['firmware_version'],
                 $data['location'], $data['comment'], $data['status'], $data['purchased_at'] ?: null, Auth::id()]
            );
            flash('success', 'Device added.');
            redirect('/inventory/' . $id);
        }
        $projects = self::allowedProjects();
        View::render('inventory/create', ['projects' => $projects, 'data' => [], 'title' => 'New Device']);
    }

    public static function edit(string $id): void {
        Auth::requireEdit('inventory');
        $item = self::findOr404((int)$id);
        self::checkProjectAccess($item);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            $oldFirmware = $item['firmware_version'] ?? '';
            $data = self::extractFields();
            Database::execute(
                'UPDATE inventory_items SET project_id=?, name=?, serial_number=?, firmware_version=?, location=?, comment=?, status=?, purchased_at=? WHERE id=?',
                [$data['project_id'] ?: null, $data['name'], $data['serial_number'], $data['firmware_version'],
                 $data['location'], $data['comment'], $data['status'], $data['purchased_at'] ?: null, (int)$id]
            );
            if ($data['firmware_version'] !== '' && $data['firmware_version'] !== $oldFirmware) {
                $desc = $oldFirmware !== ''
                    ? 'Firmware upgraded from ' . $oldFirmware . ' to ' . $data['firmware_version']
                    : 'Firmware set to ' . $data['firmware_version'];
                Database::insert(
                    'INSERT INTO inventory_logbook (item_id, user_id, log_date, log_time, action, description) VALUES (?,?,?,?,?,?)',
                    [(int)$id, Auth::id(), date('Y-m-d'), date('H:i'), 'Firmware Update', $desc]
                );
            }
            flash('success', 'Device updated.');
            redirect('/inventory/' . $id);
        }
        $projects = self::allowedProjects();
        $data = $item;
        View::render('inventory/edit', compact('item', 'data', 'projects') + ['title' => 'Edit Device']);
    }

    public static function delete(string $id): void {
        Auth::requireEdit('inventory');
        Auth::verifyCsrf();
        $item = self::findOr404((int)$id);
        self::checkProjectAccess($item);
        Database::execute('DELETE FROM inventory_items WHERE id = ?', [(int)$id]);
        flash('success', 'Device deleted.');
        redirect('/inventory');
    }

    public static function addLog(string $id): void {
        Auth::requireEdit('inventory');
        Auth::verifyCsrf();
        Database::insert(
            'INSERT INTO inventory_logbook (item_id, user_id, log_date, log_time, action, description) VALUES (?,?,?,?,?,?)',
            [(int)$id, Auth::id(), $_POST['log_date'] ?? date('Y-m-d'), $_POST['log_time'] ?? '00:00',
             trim($_POST['action'] ?? ''), trim($_POST['description'] ?? '')]
        );
        flash('success', 'Log entry added.');
        redirect('/inventory/' . $id);
    }

    public static function deleteLog(string $id, string $lid): void {
        Auth::requireEdit('inventory');
        Auth::verifyCsrf();
        Database::execute('DELETE FROM inventory_logbook WHERE id=? AND item_id=?', [(int)$lid, (int)$id]);
        flash('success', 'Entry deleted.');
        redirect('/inventory/' . $id);
    }

    private static function findOr404(int $id): array {
        $item = Database::fetchOne(
            'SELECT inv.*, p.name project_name FROM inventory_items inv LEFT JOIN projects p ON p.id=inv.project_id WHERE inv.id=?',
            [$id]
        );
        if (!$item) abort(404, 'Device not found');
        return $item;
    }

    private static function checkProjectAccess(array $item): void {
        if (Auth::isAdmin()) return;
        $pid = $item['project_id'] ?? null;
        if (!$pid) return; // unassigned items are visible to all
        $ids = Auth::groupProjectIds();
        if ($ids !== null && !in_array((int)$pid, $ids, true)) {
            abort(403, 'Kein Zugriff auf dieses Projekt');
        }
    }

    // SQL clause to filter inventory items by project access (NULL project_id = accessible to all)
    private static function projectClause(string $alias): array {
        $ids = Auth::groupProjectIds();
        if ($ids === null) return ['1=1', []];
        if (empty($ids)) return ["$alias.project_id IS NULL", []];
        $ph = implode(',', array_fill(0, count($ids), '?'));
        return ["($alias.project_id IS NULL OR $alias.project_id IN ($ph))", $ids];
    }

    private static function allowedProjects(): array {
        [$sql, $params] = Auth::projectAccessClause('p');
        return Database::fetchAll("SELECT id, name FROM projects p WHERE $sql ORDER BY name", $params);
    }

    private static function extractFields(): array {
        return [
            'project_id'       => (int)($_POST['project_id'] ?? 0),
            'name'             => trim($_POST['name'] ?? ''),
            'serial_number'    => trim($_POST['serial_number'] ?? ''),
            'firmware_version' => trim($_POST['firmware_version'] ?? ''),
            'location'         => trim($_POST['location'] ?? ''),
            'comment'          => trim($_POST['comment'] ?? ''),
            'status'           => $_POST['status'] ?? 'available',
            'purchased_at'     => $_POST['purchased_at'] ?? '',
        ];
    }
}
