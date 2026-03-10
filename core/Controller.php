<?php
declare(strict_types=1);

class Controller
{
    /**
     * Render a view with data.
     *
     * @throws RuntimeException If the view file does not exist
     */
    protected function view(string $viewPath, array $data = []): void
    {
        $fullPath = __DIR__ . '/../app/views/' . $viewPath . '.php';

        if (!file_exists($fullPath)) {
            http_response_code(404);
            header('Content-Type: text/html; charset=UTF-8');
            echo sprintf(
                '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>404 - View Not Found</title></head>' .
                '<body style="font-family:sans-serif;padding:40px;"><h1>View Not Found</h1>' .
                '<p>The requested view <code>%s</code> could not be found.</p>' .
                '<p>Full path attempted: <code>%s</code></p></body></html>',
                htmlspecialchars($viewPath),
                htmlspecialchars($fullPath)
            );
            exit;
        }

        // Extract only after file existence is confirmed
        extract($data, EXTR_SKIP);
        require $fullPath;
    }

    protected function redirect(string $url, int $status = 302): never
    {
        header('Location: ' . $url, true, $status);
        exit;
    }

    protected function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']);
    }

    protected function requireAuth(?string $requiredRole = null): void
    {
        if (!$this->isLoggedIn()) {
            $this->redirect('index.php?error=not_logged_in');
        }

        if ($requiredRole !== null) {
            $actualRole = $_SESSION['role'] ?? null;
            if ($actualRole !== $requiredRole) {
                $this->redirect('index.php?error=unauthorized');
            }
        }
    }

    protected function getCurrentUserId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    protected function currentUser(): array
    {
        return $_SESSION['user'] ?? [];
    }

    protected function getUserRole(): ?string
    {
        return $_SESSION['role'] ?? null;
    }

    /**
     * Generate or retrieve current CSRF token
     */
    protected function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Validate submitted CSRF token
     *
     * @throws RuntimeException on failure
     */
    protected function validateCsrf(string $token): void
    {
        if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            http_response_code(403);
            die('CSRF token validation failed. Please try again.');
        }

        // Optional: one-time use (recommended for POST/PUT/DELETE)
        unset($_SESSION['csrf_token']);
    }

    /**
     * Quick JSON response helper (useful for AJAX in HR system)
     */
    protected function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Set a flash message (one-time display after redirect)
     */
    protected function flash(string $key, string $message): void
    {
        $_SESSION['flash'][$key] = $message;
    }

    /**
     * Get and consume a flash message
     */
    protected function getFlash(string $key): ?string
    {
        $message = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $message;
    }
}