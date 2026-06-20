<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class NotificationModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getForUser(int $userId, int $limit = 30, int $offset = 0): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM notifications WHERE user_id = ?
            ORDER BY created_at DESC LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countUnread(int $userId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    public function countForUser(int $userId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=?");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    public function markRead(int $id, int $userId): bool
    {
        return $this->db->prepare(
            "UPDATE notifications SET is_read=1, read_at=NOW() WHERE id=? AND user_id=?"
        )->execute([$id, $userId]);
    }

    public function markAllRead(int $userId): bool
    {
        return $this->db->prepare(
            "UPDATE notifications SET is_read=1, read_at=NOW() WHERE user_id=? AND is_read=0"
        )->execute([$userId]);
    }

    public function delete(int $id, int $userId): bool
    {
        return $this->db->prepare("DELETE FROM notifications WHERE id=? AND user_id=?")->execute([$id, $userId]);
    }

    public function deleteAllRead(int $userId): int
    {
        $stmt = $this->db->prepare("DELETE FROM notifications WHERE user_id=? AND is_read=1");
        $stmt->execute([$userId]);
        return $stmt->rowCount();
    }

    public function send(int $userId, string $type, string $title, string $body, array $data = []): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO notifications (user_id, type, title, body, data)
            VALUES (:uid, :type, :title, :body, :data)
        ");
        $stmt->execute([
            ':uid'   => $userId,
            ':type'  => $type,
            ':title' => $title,
            ':body'  => $body,
            ':data'  => !empty($data) ? json_encode($data) : null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function sendToAll(string $type, string $title, string $body, array $data = []): int
    {
        $users = $this->db->query("SELECT id FROM users WHERE status='active'")->fetchAll(PDO::FETCH_COLUMN);
        $count = 0;
        foreach ($users as $uid) {
            $this->send((int)$uid, $type, $title, $body, $data);
            $count++;
        }
        return $count;
    }

    public function sendToRole(int $roleId, string $type, string $title, string $body, array $data = []): int
    {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE role_id=? AND status='active'");
        $stmt->execute([$roleId]);
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $count = 0;
        foreach ($users as $uid) {
            $this->send((int)$uid, $type, $title, $body, $data);
            $count++;
        }
        return $count;
    }
}
