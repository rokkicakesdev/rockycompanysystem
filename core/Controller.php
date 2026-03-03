<?php
class Controller {

    protected function view(string $viewPath, array $data = []): void {
        extract($data);
        $fullPath = __DIR__ . '/../app/views/' . $viewPath . '.php';
        if (!file_exists($fullPath)) {
            die("View not found: $fullPath");
        }
        require $fullPath;
    }

    protected function redirect(string $url): void {
        header('Location: ' . $url);
        exit;
    }

    protected function isLoggedIn(): bool {
        return isset($_SESSION['user_id']);
    }

    protected function requireAuth(string $role = null): void {
        if (!$this->isLoggedIn()) {
            $this->redirect('index.php');
        }
        if ($role && $_SESSION['role'] !== $role) {
            $this->redirect('index.php?error=unauthorized');
        }
    }

    protected function currentUser(): array {
        return $_SESSION['user'] ?? [];
    }
}
