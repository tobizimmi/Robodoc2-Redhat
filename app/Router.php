<?php
declare(strict_types=1);

class Router {
    private array $routes = [];

    public function get(string $pattern, callable $handler): void {
        $this->routes['GET'][] = [$pattern, $handler];
    }

    public function post(string $pattern, callable $handler): void {
        $this->routes['POST'][] = [$pattern, $handler];
    }

    public function any(string $pattern, callable $handler): void {
        $this->routes['GET'][]  = [$pattern, $handler];
        $this->routes['POST'][] = [$pattern, $handler];
    }

    public function dispatch(): void {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $base   = BASE_URL;
        if ($base && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        $uri = '/' . ltrim($uri, '/');

        $routes = $this->routes[$method] ?? [];
        foreach ($routes as [$pattern, $handler]) {
            $regex = $this->toRegex($pattern);
            if (preg_match($regex, $uri, $matches)) {
                array_shift($matches);
                $handler(...$matches);
                return;
            }
        }
        abort(404, 'Seite nicht gefunden');
    }

    private function toRegex(string $pattern): string {
        $regex = preg_replace('/\{(\w+)\}/', '([^/]+)', $pattern);
        return '#^' . $regex . '$#';
    }
}
