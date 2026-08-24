<?php
declare(strict_types=1);

class View {
    public static function render(string $view, array $data = [], string $layout = 'app'): void {
        extract($data);
        ob_start();
        require __DIR__ . '/views/' . $view . '.php';
        $content = ob_get_clean();
        require __DIR__ . '/views/layouts/' . $layout . '.php';
    }
}
