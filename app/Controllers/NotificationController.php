<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Auth;
use App\Core\CSRF;
use App\Models\NotificationModel;

class NotificationController extends Controller
{
    private NotificationModel $notif;

    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
        $this->notif = new NotificationModel();
    }

    public function index(): void
    {
        $page    = max(1, (int)Request::get('page', 1));
        $perPage = 20;
        $userId  = Auth::id();

        $notifications = $this->notif->getForUser($userId, $perPage, ($page - 1) * $perPage);
        $total         = $this->notif->countForUser($userId);
        $unread        = $this->notif->countUnread($userId);

        $this->view('notifications/index', [
            'title'         => 'Notifications',
            'notifications' => $notifications,
            'total'         => $total,
            'unread'        => $unread,
            'page'          => $page,
            'perPage'       => $perPage,
            'csrf_token'    => CSRF::generate(),
        ]);
    }

    public function markRead(int $id): void
    {
        if (!Request::isPost()) { Response::json(['success' => false], 405); return; }
        CSRF::verify();
        $this->notif->markRead($id, Auth::id());
        Response::json(['success' => true]);
    }

    public function markAllRead(): void
    {
        if (!Request::isPost()) { Response::json(['success' => false], 405); return; }
        CSRF::verify();
        $this->notif->markAllRead(Auth::id());
        Response::json(['success' => true]);
    }

    public function delete(int $id): void
    {
        if (!Request::isPost()) { Response::json(['success' => false], 405); return; }
        CSRF::verify();
        $this->notif->delete($id, Auth::id());
        Response::json(['success' => true]);
    }

    public function deleteAllRead(): void
    {
        if (!Request::isPost()) { Response::json(['success' => false], 405); return; }
        CSRF::verify();
        $deleted = $this->notif->deleteAllRead(Auth::id());
        Response::json(['success' => true, 'deleted' => $deleted]);
    }
}
