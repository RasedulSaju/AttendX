<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\CSRF;
use App\Core\Auth;
use App\Core\AuditLogger;
use App\Core\Database;
use PDO;

class AuthController extends Controller
{
    // ─── Login ────────────────────────────────────────────────────────────────

    public function showLogin(): void
    {
        if (Auth::check()) {
            Response::redirect('/dashboard');
        }
        $this->view('auth/login', [
            'title'      => 'Login',
            'csrf_token' => CSRF::generate(),
            'error'      => Session::getFlash('login_error'),
        ]);
    }

    public function login(): void
    {
        if (Auth::check()) {
            Response::redirect('/dashboard');
        }

        CSRF::verify();

        $email    = strtolower(trim(Request::post('email', '')));
        $password = Request::post('password', '');
        $remember = (bool)Request::post('remember');

        // Basic input validation
        if (empty($email) || empty($password)) {
            Session::flash('login_error', 'Email and password are required.');
            Response::redirect('/login');
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('login_error', 'Invalid email address.');
            Response::redirect('/login');
            return;
        }

        // Rate limiting via session-stored attempt counter
        $attemptKey = '_login_attempts_' . md5($email);
        $lockKey    = '_login_lockout_'  . md5($email);
        $maxAttempts = (int)config('security.max_login_attempts', 5);
        $lockoutSec  = (int)config('security.lockout_duration', 30) * 60;

        if (Session::has($lockKey)) {
            $lockedUntil = Session::get($lockKey);
            if (time() < $lockedUntil) {
                $remaining = ceil(($lockedUntil - time()) / 60);
                Session::flash('login_error', "Too many attempts. Try again in {$remaining} minute(s).");
                Response::redirect('/login');
                return;
            }
            Session::forget($lockKey);
            Session::forget($attemptKey);
        }

        if (Auth::attempt($email, $password)) {
            Session::forget($attemptKey);
            Session::forget($lockKey);

            if ($remember) {
                // Extend session to 30 days
                ini_set('session.cookie_lifetime', (string)(30 * 24 * 3600));
            }

            $intended = Session::get('intended', '/dashboard');
            Session::forget('intended');
            Response::redirect($intended);
            return;
        }

        // Failed attempt
        $attempts = (int)Session::get($attemptKey, 0) + 1;
        Session::set($attemptKey, $attempts);

        AuditLogger::logAuth('login_failed', $email, false);

        if ($attempts >= $maxAttempts) {
            Session::set($lockKey, time() + $lockoutSec);
            Session::forget($attemptKey);
            Session::flash('login_error', "Account locked for {$maxAttempts} failed attempts. Try again in " . ($lockoutSec / 60) . " minutes.");
        } else {
            $remaining = $maxAttempts - $attempts;
            Session::flash('login_error', "Invalid credentials. {$remaining} attempt(s) remaining.");
        }

        Response::redirect('/login');
    }

    // ─── Logout ───────────────────────────────────────────────────────────────

    public function logout(): void
    {
        if (!Request::isPost()) {
            Response::redirect('/login');
            return;
        }
        CSRF::verify();
        Auth::logout();
        Session::flash('success', 'You have been logged out.');
        Response::redirect('/login');
    }

    // ─── Profile ──────────────────────────────────────────────────────────────

    public function profile(): void
    {
        $this->requireAuth();
        $user = Auth::user();

        // Fetch linked employee if any
        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT e.*, d.name AS department_name, des.name AS designation_name
            FROM employees e
            LEFT JOIN departments  d   ON d.id   = e.department_id
            LEFT JOIN designations des ON des.id = e.designation_id
            WHERE e.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$user['id']]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);

        if (Request::isPost()) {
            CSRF::verify();
            $name  = trim(Request::post('name', ''));
            $phone = trim(Request::post('phone', ''));

            if (empty($name) || mb_strlen($name) < 2) {
                Session::flash('error', 'Name must be at least 2 characters.');
                Response::redirect('/profile');
                return;
            }

            $db->prepare("UPDATE users SET name=?, updated_at=NOW() WHERE id=?")->execute([$name, $user['id']]);

            // Update employee phone if linked
            if ($employee && $phone) {
                $db->prepare("UPDATE employees SET phone=? WHERE id=?")->execute([$phone, $employee['id']]);
            }

            // Update profile photo
            if (Request::hasFile('photo')) {
                $file = $_FILES['photo'];
                $this->handleProfilePhoto($file, $user['id'], $employee['id'] ?? null);
            }

            AuditLogger::log('auth', 'profile_updated', 'User updated own profile.');
            Session::flash('success', 'Profile updated.');
            Response::redirect('/profile');
            return;
        }

        $this->view('auth/profile', [
            'title'      => 'My Profile',
            'user'       => $user,
            'employee'   => $employee,
            'csrf_token' => CSRF::generate(),
        ]);
    }

    // ─── Change Password ──────────────────────────────────────────────────────

    public function showChangePassword(): void
    {
        $this->requireAuth();
        $this->view('auth/change_password', [
            'title'      => 'Change Password',
            'csrf_token' => CSRF::generate(),
        ]);
    }

    public function changePassword(): void
    {
        $this->requireAuth();
        CSRF::verify();

        $current  = Request::post('current_password', '');
        $new      = Request::post('new_password', '');
        $confirm  = Request::post('confirm_password', '');

        $minLen = (int)config('security.min_password_length', 8);

        if (empty($current) || empty($new) || empty($confirm)) {
            Session::flash('error', 'All fields are required.');
            Response::redirect('/change-password');
            return;
        }

        if (mb_strlen($new) < $minLen) {
            Session::flash('error', "New password must be at least {$minLen} characters.");
            Response::redirect('/change-password');
            return;
        }

        if ($new !== $confirm) {
            Session::flash('error', 'New passwords do not match.');
            Response::redirect('/change-password');
            return;
        }

        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([Auth::id()]);
        $stored = $stmt->fetchColumn();

        if (!password_verify($current, $stored)) {
            Session::flash('error', 'Current password is incorrect.');
            Response::redirect('/change-password');
            return;
        }

        $hashed = Auth::hashPassword($new);
        $db->prepare("UPDATE users SET password=?, updated_at=NOW() WHERE id=?")->execute([$hashed, Auth::id()]);

        AuditLogger::log('auth', 'password_changed', 'User changed own password.');
        Auth::logout();
        Session::flash('success', 'Password changed. Please log in again.');
        Response::redirect('/login');
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    private function handleProfilePhoto(array $file, int $userId, ?int $employeeId): void
    {
        $allowed  = ['image/jpeg', 'image/png', 'image/webp'];
        $maxBytes = 2 * 1024 * 1024; // 2MB

        if ($file['error'] !== UPLOAD_ERR_OK) return;
        if (!in_array($file['type'], $allowed)) return;
        if ($file['size'] > $maxBytes) return;

        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'avatar_' . $userId . '_' . time() . '.' . $ext;
        $dest     = base_path('storage/uploads/avatars/' . $filename);

        if (!is_dir(dirname($dest))) {
            mkdir(dirname($dest), 0750, true);
        }

        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $path = 'storage/uploads/avatars/' . $filename;
            $db   = Database::getInstance()->getConnection();
            if ($employeeId) {
                $db->prepare("UPDATE employees SET profile_photo=? WHERE id=?")->execute([$path, $employeeId]);
            }
        }
    }
}
