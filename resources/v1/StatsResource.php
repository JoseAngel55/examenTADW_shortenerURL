<?php

class StatsResource
{
    private $db;
    private $url;
    private $visit;

    public function __construct()
    {
        $database    = new Database();
        $this->db    = $database->getConnection();
        $this->url   = new Url($this->db);
        $this->visit = new Visit($this->db);
    }

    // GET /api/v1/stats/{code}
    public function show($code)
    {
        $this->url->code = $code;

        if (!$this->url->findByCode()) {
            Response::error('URL no encontrada.', 404);
            return;
        }

        $baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];

        Response::json([
            'code'           => $this->url->code,
            'short_url'      => $baseUrl . '/' . $this->url->code,
            'original_url'   => $this->url->original_url,
            'created_at'     => $this->url->created_at,
            'expires_at'     => $this->url->expires_at,
            'max_uses'       => $this->url->max_uses,
            'is_expired'     => $this->url->isExpired(),
            'total_visits'   => $this->visit->countByUrl($this->url->id),
            'visits_per_day' => $this->visit->perDay($this->url->id),
            'recent_visits'  => $this->visit->recent($this->url->id),
        ]);
    }
}