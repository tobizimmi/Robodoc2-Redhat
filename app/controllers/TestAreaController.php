<?php
declare(strict_types=1);

class TestAreaController
{
    public static function index(): void
    {
        Auth::requireView('test_areas');
        $areas = Database::fetchAll("
            SELECT ta.*, u.name creator_name,
                   (SELECT COUNT(*) FROM entries WHERE test_area_id = ta.id) entry_count,
                   (SELECT COUNT(*) FROM test_area_photos WHERE test_area_id = ta.id) photo_count
            FROM test_areas ta
            LEFT JOIN users u ON u.id = ta.created_by
            ORDER BY ta.name
        ");
        View::render('test-areas/index', ['areas' => $areas, 'title' => 'Test Areas']);
    }

    public static function show(string $id): void
    {
        Auth::requireView('test_areas');
        $area = self::findOr404((int)$id);
        $photos = Database::fetchAll(
            "SELECT * FROM test_area_photos WHERE test_area_id = ? ORDER BY id",
            [(int)$id]
        );
        $entries = Database::fetchAll("
            SELECT e.id, e.title, e.description, e.entry_date,
                   et.name type_name, et.color type_color,
                   p.name project_name
            FROM entries e
            LEFT JOIN entry_types et ON et.id = e.entry_type_id
            LEFT JOIN projects p     ON p.id  = e.project_id
            WHERE e.test_area_id = ?
            ORDER BY e.entry_date DESC LIMIT 50
        ", [(int)$id]);
        View::render('test-areas/show', compact('area', 'photos', 'entries') + ['title' => $area['name']]);
    }

    public static function create(): void
    {
        Auth::requireEdit('test_areas');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            if (trim($_POST['name'] ?? '') === '') {
                flash('error', 'Name is required.');
                redirect('/test-areas/create');
            }
            $id = Database::insert(
                "INSERT INTO test_areas
                 (name, location_description, gps_lat, gps_lon, slope_max_percent,
                  boundary_type, boundary_length_m, area_sqm, surface_types, obstacles, notes, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
                self::extractFields(Auth::id())
            );
            self::handlePhotos($id);
            flash('success', 'Test area created.');
            redirect('/test-areas/' . $id);
        }
        View::render('test-areas/create', ['data' => [], 'title' => 'New Test Area']);
    }

    public static function edit(string $id): void
    {
        Auth::requireEdit('test_areas');
        $area = self::findOr404((int)$id);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::verifyCsrf();
            Database::execute(
                "UPDATE test_areas SET
                 name=?, location_description=?, gps_lat=?, gps_lon=?, slope_max_percent=?,
                 boundary_type=?, boundary_length_m=?, area_sqm=?, surface_types=?, obstacles=?, notes=?
                 WHERE id=?",
                [...array_slice(self::extractFields(null), 0, 11), (int)$id]
            );
            self::handlePhotos((int)$id);
            flash('success', 'Test area updated.');
            redirect('/test-areas/' . $id);
        }
        $photos = Database::fetchAll(
            "SELECT * FROM test_area_photos WHERE test_area_id = ? ORDER BY id",
            [(int)$id]
        );
        $data = $area;
        View::render('test-areas/edit', compact('area', 'data', 'photos') + ['title' => 'Edit: ' . $area['name']]);
    }

    public static function delete(string $id): void
    {
        Auth::requireEdit('test_areas');
        Auth::verifyCsrf();
        // Clean up photo files
        $photos = Database::fetchAll("SELECT file_path FROM test_area_photos WHERE test_area_id=?", [(int)$id]);
        foreach ($photos as $p) { @unlink(UPLOAD_DIR . $p['file_path']); }
        Database::execute('DELETE FROM test_areas WHERE id=?', [(int)$id]);
        flash('success', 'Test area deleted.');
        redirect('/test-areas');
    }

    public static function deletePhoto(string $id, string $pid): void
    {
        Auth::requireEdit('test_areas');
        Auth::verifyCsrf();
        $photo = Database::fetchOne(
            "SELECT * FROM test_area_photos WHERE id=? AND test_area_id=?",
            [(int)$pid, (int)$id]
        );
        if ($photo) {
            @unlink(UPLOAD_DIR . $photo['file_path']);
            Database::execute("DELETE FROM test_area_photos WHERE id=?", [(int)$pid]);
        }
        flash('success', 'Photo removed.');
        redirect('/test-areas/' . $id . '/edit');
    }

    public static function servePhoto(string $id, string $pid): void
    {
        Auth::require();
        $photo = Database::fetchOne(
            "SELECT * FROM test_area_photos WHERE id=? AND test_area_id=?",
            [(int)$pid, (int)$id]
        );
        if (!$photo) { http_response_code(404); exit; }
        $path = UPLOAD_DIR . $photo['file_path'];
        if (!file_exists($path)) { http_response_code(404); exit; }
        $mime = mime_content_type($path) ?: 'image/jpeg';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=3600');
        readfile($path);
        exit;
    }

    public static function serveThumb(string $id, string $pid): void
    {
        Auth::require();
        $photo = Database::fetchOne(
            "SELECT * FROM test_area_photos WHERE id=? AND test_area_id=?",
            [(int)$pid, (int)$id]
        );
        if (!$photo) { http_response_code(404); exit; }
        $path = UPLOAD_DIR . $photo['file_path'];
        if (!file_exists($path)) { http_response_code(404); exit; }
        $thumbPath = UPLOAD_DIR . 'thumbs/ta_' . $photo['id'] . '.jpg';
        if (!file_exists($thumbPath)) {
            @mkdir(UPLOAD_DIR . 'thumbs/', 0755, true);
            $mime = mime_content_type($path) ?: 'image/jpeg';
            AttachmentController::makeThumb($path, $thumbPath, $mime, 400, 300);
        }
        if (file_exists($thumbPath)) {
            header('Content-Type: image/jpeg');
            header('Cache-Control: private, max-age=3600');
            readfile($thumbPath);
        } else {
            self::servePhoto($id, $pid);
        }
        exit;
    }

    // ── Helpers ───────────────────────────────────────────────────

    private static function extractFields(?int $createdBy): array
    {
        $f = [
            trim($_POST['name'] ?? ''),
            trim($_POST['location_description'] ?? '') ?: null,
            ($_POST['gps_lat'] ?? '') !== '' ? (float)$_POST['gps_lat'] : null,
            ($_POST['gps_lon'] ?? '') !== '' ? (float)$_POST['gps_lon'] : null,
            ($_POST['slope_max_percent'] ?? '') !== '' ? (float)$_POST['slope_max_percent'] : null,
            trim($_POST['boundary_type'] ?? '') ?: null,
            ($_POST['boundary_length_m'] ?? '') !== '' ? (float)$_POST['boundary_length_m'] : null,
            ($_POST['area_sqm'] ?? '') !== '' ? (float)$_POST['area_sqm'] : null,
            trim($_POST['surface_types'] ?? '') ?: null,
            trim($_POST['obstacles'] ?? '') ?: null,
            trim($_POST['notes'] ?? '') ?: null,
        ];
        if ($createdBy !== null) $f[] = $createdBy;
        return $f;
    }

    private static function handlePhotos(int $areaId): void
    {
        if (empty($_FILES['photos']['name'][0])) return;
        foreach ($_FILES['photos']['error'] as $i => $err) {
            if ($err !== UPLOAD_ERR_OK) continue;
            $tmp  = $_FILES['photos']['tmp_name'][$i];
            $mime = mime_content_type($tmp) ?: '';
            if (!str_starts_with($mime, 'image/')) continue;
            // Derive extension from validated MIME type (never trust user-supplied filename)
            $imgExtMap = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp','image/heic'=>'heic'];
            $ext = $imgExtMap[$mime] ?? 'jpg';
            $fn  = bin2hex(random_bytes(16)) . '.' . $ext; // cryptographically random, not guessable
            if (move_uploaded_file($tmp, UPLOAD_DIR . $fn)) {
                Database::insert(
                    "INSERT INTO test_area_photos (test_area_id, file_path, display_name, file_size, uploaded_by)
                     VALUES (?,?,?,?,?)",
                    [$areaId, $fn, $_FILES['photos']['name'][$i], $_FILES['photos']['size'][$i], Auth::id()]
                );
            }
        }
    }

    private static function findOr404(int $id): array
    {
        $area = Database::fetchOne("SELECT * FROM test_areas WHERE id=?", [$id]);
        if (!$area) { http_response_code(404); exit('Test area not found'); }
        return $area;
    }
}
