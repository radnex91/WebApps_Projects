<?php

class NotificationModel extends Model
{
    protected $table = 'notifications';
    protected $primaryKey = 'id';
    protected $fillable = ['user_id', 'title', 'message', 'type', 'link', 'is_read'];

    public function getUnreadByUser($userId)
    {
        return $this->findBy(['user_id' => $userId, 'is_read' => 0]);
    }

    public function markAsRead($id)
    {
        return $this->update($id, ['is_read' => 1]);
    }

    public function markAllAsRead($userId)
    {
        $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0";
        return $this->execute($sql, ['user_id' => $userId]);
    }

    public function countUnread($userId)
    {
        return $this->count(['user_id' => $userId, 'is_read' => 0]);
    }
}