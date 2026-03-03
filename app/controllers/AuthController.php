<?php
require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/Model.php';

class AuthController extends Controller {

    public function loginPage(): void {
        if ($this->isLoggedIn()) {
            $this->redirectByRole($_SESSION['role']);
        }
        $error = $_GET['error'] ?? null;
        $this->view('login', ['error' => $error]);
    }

    public function login(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('index.php');
        }

        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($username) || empty($password)) {
            $this->redirect('index.php?error=empty');
        }

        $user = Model::findUserByUsername($username);

        if (!$user || !password_verify($password, $user['password'])) {
            $this->redirect('index.php?error=invalid');
        }

        if ($user['status'] !== 'active') {
            $this->redirect('index.php?error=inactive');
        }

        // Start session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role']    = $user['role'];
        $_SESSION['name']    = $user['name'];
        $_SESSION['user']    = $user;

        $this->redirectByRole($user['role']);
    }

    public function logout(): void {
        session_destroy();
        $this->redirect('index.php?msg=loggedout');
    }

    private function redirectByRole(string $role): void {
        match ($role) {
            ROLE_ADMIN      => $this->redirect('app/views/admin/dashboard.php'),
            ROLE_MANAGEMENT => $this->redirect('app/views/management/dashboard.php'),
            default         => $this->redirect('index.php?error=unauthorized'),
        };
    }
}
