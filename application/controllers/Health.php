<?php defined('BASEPATH') or exit('No direct script access allowed');

class Health extends CI_Controller
{
    /**
     * Shallow health check — confirms nginx + php-fpm are processing requests.
     * Does not check DB: a DB failure should not prevent the machine from starting.
     * Use /health/db for a deep check after the app is fully provisioned.
     */
    public function index(): void
    {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok']);
    }

    /**
     * Deep health check — confirms DB connectivity.
     * Not used by Fly.io health checks; available for manual verification.
     */
    public function db(): void
    {
        $this->load->database();

        try {
            $this->db->query('SELECT 1');
            $ok = true;
        } catch (Throwable $e) {
            $ok = false;
        }

        http_response_code($ok ? 200 : 503);
        header('Content-Type: application/json');
        echo json_encode(['status' => $ok ? 'ok' : 'error', 'db' => $ok ? 'reachable' : 'unreachable']);
    }
}
