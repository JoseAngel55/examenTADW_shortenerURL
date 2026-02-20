<?php

class UrlResource
{
    private $db;
    private $url;

    public function __construct()
    {
        $database  = new Database();
        $this->db  = $database->getConnection();
        $this->url = new Url($this->db);
    }

    // POST /api/v1/shorten
    public function shorten()
    {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if (!$this->url->checkRateLimit($ip)) {
            Response::error('Demasiadas peticiones. Intenta de nuevo en una hora.', 429);
            return;
        }

        $data        = json_decode(file_get_contents("php://input"));
        $originalUrl = $data->url      ?? null;
        $expiresAt   = $data->expires_at ?? null;
        $maxUses     = isset($data->max_uses) ? (int) $data->max_uses : null;

        if (!$originalUrl) {
            Response::error('El campo url es requerido.', 400);
            return;
        }

        if (!$this->url->isValidUrl($originalUrl)) {
            Response::error('La URL no es válida. Debe comenzar con http:// o https://', 400);
            return;
        }

        if ($this->url->isSelfUrl($originalUrl)) {
            Response::error('No puedes acortar una URL de este mismo servicio.', 400);
            return;
        }

        if ($expiresAt !== null) {
            $timestamp = strtotime($expiresAt);
            if (!$timestamp || $timestamp <= time()) {
                Response::error('La fecha de expiración debe ser una fecha futura válida.', 400);
                return;
            }
            $expiresAt = date('Y-m-d H:i:s', $timestamp);
        }

        $this->url->original_url = $originalUrl;
        $this->url->creator_ip   = $ip;
        $this->url->max_uses     = $maxUses;
        $this->url->expires_at   = $expiresAt;

        if ($this->url->create()) {
            $baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
            Response::json([
                'short_url'    => $baseUrl . '/' . $this->url->code,
                'code'         => $this->url->code,
                'original_url' => $originalUrl,
                'expires_at'   => $expiresAt,
                'max_uses'     => $maxUses,
                'created_at'   => date('Y-m-d H:i:s'),
            ], 201);
        } else {
            Response::error('No se pudo crear la URL corta.', 500);
        }
    }

    // GET /{code}
    public function redirect($code)
    {
        $this->url->code = $code;

        if (!$this->url->findByCode()) {
            Response::error('URL no encontrada.', 404);
            return;
        }

        if ($this->url->isExpired()) {
            Response::error('Esta URL ha expirado.', 410);
            return;
        }

        if ($this->url->reachedMaxUses()) {
            Response::error('Esta URL ha alcanzado su límite de usos.', 410);
            return;
        }

        $visit = new Visit($this->db);
        $visit->register(
            $this->url->id,
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        );

        $this->url->incrementVisits();

        http_response_code(302);
        header("Location: " . $this->url->original_url);
    }
}