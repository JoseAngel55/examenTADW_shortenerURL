<?php

class Visit
{
    private $conn;
    private $table = "visits";

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function register(int $urlId, string $ip, string $userAgent)
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO {$this->table} (url_id, ip, user_agent) VALUES (:url_id, :ip, :user_agent)"
        );
        $stmt->bindParam(':url_id',     $urlId);
        $stmt->bindParam(':ip',         $ip);
        $stmt->bindParam(':user_agent', $userAgent);
        return $stmt->execute();
    }

    public function countByUrl(int $urlId): int
    {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) AS total FROM {$this->table} WHERE url_id = :url_id"
        );
        $stmt->execute([':url_id' => $urlId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) $row['total'];
    }

    public function perDay(int $urlId): array
    {
        $stmt = $this->conn->prepare(
            "SELECT DATE(visited_at) AS day, COUNT(*) AS visits
             FROM {$this->table}
             WHERE url_id = :url_id
             GROUP BY DATE(visited_at)
             ORDER BY day DESC"
        );
        $stmt->execute([':url_id' => $urlId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    
    public function recent(int $urlId, int $limit = 10): array
    {
        $stmt = $this->conn->prepare(
            "SELECT ip, user_agent, visited_at
             FROM {$this->table}
             WHERE url_id = :url_id
             ORDER BY visited_at DESC
             LIMIT :limit"
        );
        $stmt->bindParam(':url_id', $urlId, PDO::PARAM_INT);
        $stmt->bindParam(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}