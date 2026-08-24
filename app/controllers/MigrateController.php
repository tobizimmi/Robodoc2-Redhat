<?php
declare(strict_types=1);

class MigrateController
{
    public static function index(): void
    {
        Auth::requireAdmin();

        $result = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'run') {
            Auth::verifyCsrf();
            $result = runMigrationsReport();
            // Reset the lock file so bootstrap re-runs next request too
            @unlink(sys_get_temp_dir() . '/robodoc2_schema.lock');
        }

        View::render('migrate/index', compact('result') + ['title' => 'Update Assistant']);
    }
}
