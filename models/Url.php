<?php

class Url
{
    private $conn;
    private $table = "urls";

    public $id;
    public $code;
    public $original_url;
    public $creator_ip;
    public $visit_count;
    public $max_uses;
    public $expires_at;
    public $created_at;


    const CODE_LENGTH = 6;
    const RATE_LIMIT_MAX     = 20;
    const RATE_LIMIT_WINDOW  = 3600; 

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function create()
    {
        do {
            $this->code = $this->generateCode();
        } while ($this->codeExists($this->code));

        $stmt = $this->conn->prepare(
            "INSERT INTO {$this->table} (code, original_url, creator_ip, max_uses, expires_at)
             VALUES (:code, :original_url, :creator_ip, :max_uses, :expires_at)"
        );

        $stmt->bindParam(':code',         $this->code);
        $stmt->bindParam(':original_url', $this->original_url);
        $stmt->bindParam(':creator_ip',   $this->creator_ip);
        $stmt->bindParam(':max_uses',     $this->max_uses);
        $stmt->bindParam(':expires_at',   $this->expires_at);

        return $stmt->execute();
    }

    public function findByCode()
    {
        $stmt = $this->conn->prepare(
            "SELECT * FROM {$this->table} WHERE code = :code LIMIT 1"
        );
        $stmt->bindParam(':code', $this->code);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return false;

        $this->id           = $row['id'];
        $this->original_url = $row['original_url'];
        $this->creator_ip   = $row['creator_ip'];
        $this->visit_count  = $row['visit_count'];
        $this->max_uses     = $row['max_uses'];
        $this->expires_at   = $row['expires_at'];
        $this->created_at   = $row['created_at'];

        return true;
    }

    public function incrementVisits()
    {
        $stmt = $this->conn->prepare(
            "UPDATE {$this->table} SET visit_count = visit_count + 1 WHERE id = :id"
        );
        $stmt->bindParam(':id', $this->id);
        return $stmt->execute();
    }

    public function isExpired()
    {
        if ($this->expires_at === null) return false;
        return strtotime($this->expires_at) < time();
    }

    public function reachedMaxUses()
    {
        if ($this->max_uses === null) return false;
        return $this->visit_count >= $this->max_uses;
    }

    public function isValidUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) return false;

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'])) return false;

        return true;
    }

    public function isSelfUrl(string $url): bool
    {
        $host        = parse_url($url, PHP_URL_HOST);
        $currentHost = $_SERVER['HTTP_HOST'] ?? '';
        return $host === $currentHost;
    }

    public function checkRateLimit(string $ip): bool
    {
        $this->conn->prepare(
            "DELETE FROM rate_limit
             WHERE TIMESTAMPDIFF(SECOND, window_start, NOW()) > :window"
        )->execute([':window' => self::RATE_LIMIT_WINDOW]);

        $stmt = $this->conn->prepare(
            "SELECT requests FROM rate_limit WHERE ip = :ip"
        );
        $stmt->execute([':ip' => $ip]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $this->conn->prepare(
                "INSERT INTO rate_limit (ip, requests) VALUES (:ip, 1)"
            )->execute([':ip' => $ip]);
            return true;
        }

        if ($row['requests'] >= self::RATE_LIMIT_MAX) {
            return false; 
        }

        $this->conn->prepare(
            "UPDATE rate_limit SET requests = requests + 1 WHERE ip = :ip"
        )->execute([':ip' => $ip]);

        return true;
    }

    private function generateCode(): string
    {
        $chars  = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code   = '';
        $max    = strlen($chars) - 1;
        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= $chars[random_int(0, $max)];
        }
        return $code;
    }

    private function codeExists(string $code): bool
    {
        $stmt = $this->conn->prepare(
            "SELECT id FROM {$this->table} WHERE code = :code LIMIT 1"
        );
        $stmt->execute([':code' => $code]);
        return $stmt->fetch() !== false;
    }
}