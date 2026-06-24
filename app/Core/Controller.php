<?php
declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected Auth $auth;
    private array  $validationErrors = [];

    public function __construct()
    {
        $this->auth = new Auth();
    }

    // ─── Auth Guards ──────────────────────────────────────────────────────────

    protected function requireAuth(): void
    {
        if (!Auth::check()) {
            if (Request::isAjax() || Request::isJson()) {
                Response::json(['error' => 'Authentication required.', 'code' => 401], 401);
            }
            Session::flash('error', 'Please log in to continue.');
            Response::redirect('/login');
        }
    }

    protected function requireGuest(): void
    {
        if (Auth::check()) {
            Response::redirect('/dashboard');
        }
    }

    protected function requirePermission(string $permission): void
    {
        Auth::authorize($permission);
    }

    // ─── View Rendering ───────────────────────────────────────────────────────

    protected function view(string $view, array $data = []): void
    {
        // Convert dot notation to path separator
        $view = str_replace('.', '/', $view);

        // Pass route to views; auth/flash/csrf accessed directly by static
        // methods inside the layout (Auth::user(), CSRF::generate(), etc.)
        // Flash is NOT pre-consumed here — layouts/main.php reads and
        // unsets $_SESSION['_flash'] directly so messages are not lost.
        $data['_route'] = Request::path();

        // View::make() binds $this to a View instance so that
        // $this->layout(), $this->start(), $this->stop(), $this->section()
        // all work correctly inside view templates.
        echo \App\Core\View::make($view, $data);
    }

    // ─── Validation ───────────────────────────────────────────────────────────

    protected function validate(array $data, array $rules): bool
    {
        $this->validationErrors = [];

        foreach ($rules as $field => $ruleStr) {
            $value     = $data[$field] ?? null;
            $ruleList  = explode('|', $ruleStr);

            foreach ($ruleList as $rule) {
                $error = $this->applyRule($field, $value, $rule, $data);
                if ($error) {
                    $this->validationErrors[$field][] = $error;
                    break; // One error per field
                }
            }
        }

        return empty($this->validationErrors);
    }

    protected function getValidationErrors(): string|array
    {
        $messages = [];
        foreach ($this->validationErrors as $field => $errors) {
            foreach ($errors as $e) {
                $messages[] = $e;
            }
        }
        return implode(' ', $messages);
    }

    protected function getValidationErrorArray(): array
    {
        return $this->validationErrors;
    }

    private function applyRule(string $field, mixed $value, string $rule, array $data): ?string
    {
        $label = ucwords(str_replace('_', ' ', $field));

        if (str_starts_with($rule, 'min:')) {
            $min = (int)substr($rule, 4);
            if (is_string($value) && mb_strlen($value) < $min) return "$label must be at least $min characters.";
            if (is_numeric($value) && (float)$value < $min) return "$label must be at least $min.";
            return null;
        }
        if (str_starts_with($rule, 'max:')) {
            $max = (int)substr($rule, 4);
            if (is_string($value) && mb_strlen($value) > $max) return "$label must be at most $max characters.";
            if (is_numeric($value) && (float)$value > $max) return "$label must be at most $max.";
            return null;
        }
        if (str_starts_with($rule, 'in:')) {
            $allowed = explode(',', substr($rule, 3));
            if ($value !== null && $value !== '' && !in_array((string)$value, $allowed, true)) {
                return "$label must be one of: " . implode(', ', $allowed) . '.';
            }
            return null;
        }

        return match ($rule) {
            'required'  => ($value === null || $value === '' || (is_array($value) && empty($value)))
                            ? "$label is required." : null,
            'integer'   => ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_INT))
                            ? "$label must be an integer." : null,
            'numeric'   => ($value !== null && $value !== '' && !is_numeric($value))
                            ? "$label must be a number." : null,
            'email'     => ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL))
                            ? "$label must be a valid email address." : null,
            'url'       => ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_URL))
                            ? "$label must be a valid URL." : null,
            'date'      => ($value !== null && $value !== '' && !\DateTime::createFromFormat('Y-m-d', $value))
                            ? "$label must be a valid date (YYYY-MM-DD)." : null,
            'datetime'  => ($value !== null && $value !== '' && !\DateTime::createFromFormat('Y-m-d H:i', $value) && !\DateTime::createFromFormat('Y-m-d\TH:i', $value))
                            ? "$label must be a valid date/time." : null,
            'time'      => ($value !== null && $value !== '' && !\DateTime::createFromFormat('H:i', $value))
                            ? "$label must be a valid time (HH:MM)." : null,
            'array'     => ($value !== null && !is_array($value))
                            ? "$label must be an array." : null,
            'boolean'   => null, // accept any truthy/falsy
            default     => null,
        };
    }

    // ─── Redirect Helpers ─────────────────────────────────────────────────────

    protected function redirectBack(string $fallback = '/dashboard'): void
    {
        Response::redirect($_SERVER['HTTP_REFERER'] ?? $fallback);
    }


}
