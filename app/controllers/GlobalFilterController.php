<?php
declare(strict_types=1);

class GlobalFilterController
{
    public static function set(): void
    {
        Auth::require();
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        // Handle both project_ids (single/clear) and project_ids[] (array)
        $projectIds = $_POST['project_ids[]'] ?? $_POST['project_ids'] ?? null;
        if ($projectIds === null || $projectIds === '' || $projectIds === 'all') {
            Auth::setGlobalProjectFilter(null);
            echo json_encode(['success' => true, 'active' => false, 'count' => 0]);
        } else {
            $ids = array_filter(array_map('intval', (array)$projectIds), fn($id) => $id > 0);
            Auth::setGlobalProjectFilter(array_values($ids));
            echo json_encode(['success' => true, 'active' => true, 'count' => count($ids)]);
        }
        exit;
    }

    public static function clear(): void
    {
        Auth::require();
        header('Content-Type: application/json');
        Auth::verifyCsrf();
        Auth::setGlobalProjectFilter(null);
        echo json_encode(['success' => true]);
        exit;
    }
}
