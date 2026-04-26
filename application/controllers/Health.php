<?php defined('BASEPATH') or exit('No direct script access allowed');

class Health extends CI_Controller
{
    public function index(): void
    {
        $this->load->database();

        try {
            $this->db->query('SELECT 1');
            $db_ok = true;
        } catch (Throwable $e) {
            $db_ok = false;
        }

        if (!$db_ok) {
            http_response_code(503);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'db' => 'unreachable']);
            return;
        }

        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok']);
    }
}
