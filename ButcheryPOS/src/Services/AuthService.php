<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Csrf;
use App\Models\User;
use App\Repositories\BaseRepository;

class AuthService
{
    private BaseRepository $repo;
    private User $userModel;

    public function __construct(BaseRepository $repo, User $userModel)
    {
        $this->repo = $repo;
        $this->userModel = $userModel;
    }

    /**
     * Attempt login with rate limiting
     */
    public function attempt(string $username, string $password): array
    {
        if (Auth::isLockedOut()) {
            $minutes = Auth::getLockoutRemaining();
            return ['success' => false, 'message' => t('login_locked', ['minutes' => $minutes])];
        }

        $pdo = $this->repo->getPdo();
        if (Auth::attempt($pdo, $username, $password)) {
            return ['success' => true, 'message' => ''];
        }

        Auth::recordFailedAttempt();
        return ['success' => false, 'message' => t('login_failed')];
    }

    /**
     * Logout current user
     */
    public function logout(): void
    {
        Auth::logout();
    }

    /**
     * Create a new user
     */
    public function createUser(array $data): int
    {
        $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        return $this->userModel->create($data);
    }

    /**
     * Update user password
     */
    public function updatePassword(int $userId, string $newPassword): bool
    {
        return $this->userModel->update($userId, [
            'password' => password_hash($newPassword, PASSWORD_BCRYPT),
        ]);
    }

    /**
     * Switch language for current user
     */
    public function switchLanguage(string $lang): void
    {
        $_SESSION['lang'] = $lang;
        if (Auth::check()) {
            $userId = Auth::get('id');
            $this->userModel->update($userId, ['preferred_language' => $lang]);
            $_SESSION['user']['preferred_language'] = $lang;
        }
    }
}