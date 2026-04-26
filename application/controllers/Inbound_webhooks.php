<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Inbound webhooks controller.
 *
 * Receives and validates webhooks from Asaas (payments) and Meta (WhatsApp).
 * All endpoints read the raw body BEFORE any framework parsing to ensure
 * signature verification uses the exact bytes received.
 */
class Inbound_webhooks extends CI_Controller
{
    // ─── Asaas ───────────────────────────────────────────────────────────────

    /**
     * POST /webhooks/asaas
     *
     * Asaas does not issue HMAC signatures. Authentication is done via a
     * shared bearer token that Asaas sends as the `asaas-access-token` header,
     * configured in the Asaas dashboard under Integrações → Webhooks → access_token.
     * Comparison uses hash_equals() to prevent timing attacks.
     */
    public function asaas(): void
    {
        $expected_token = env('ASAAS_WEBHOOK_TOKEN', '');

        if (empty($expected_token)) {
            $this->_reject(500, 'ASAAS_WEBHOOK_TOKEN not configured.');
            return;
        }

        $received_token = $_SERVER['HTTP_ASAAS_ACCESS_TOKEN'] ?? '';

        if (!hash_equals($expected_token, $received_token)) {
            $this->_reject(401, 'Invalid token.');
            return;
        }

        $raw = file_get_contents('php://input');
        $event = json_decode($raw, true);

        if (!isset($event['event'], $event['payment'])) {
            $this->_reject(400, 'Unexpected payload structure.');
            return;
        }

        try {
            $this->_handle_asaas_event($event);
        } catch (Throwable $e) {
            log_message('error', '[Asaas webhook] ' . $e->getMessage());
            $this->_reject(500, 'Internal error.');
            return;
        }

        http_response_code(200);
    }

    private function _handle_asaas_event(array $event): void
    {
        $payment = $event['payment'];
        $external_ref = $payment['externalReference'] ?? '';

        switch ($event['event']) {
            case 'PAYMENT_RECEIVED':
            case 'PAYMENT_CONFIRMED':
                if (preg_match('/^appt_(\d+)$/', $external_ref, $m)) {
                    $this->load->model('appointments_model');
                    $this->appointments_model->save([
                        'id' => (int) $m[1],
                        'payment_status' => 'paid',
                    ]);
                }
                break;

            case 'PAYMENT_OVERDUE':
                log_message('info', '[Asaas] Payment overdue: ' . ($payment['id'] ?? ''));
                break;
        }
    }

    // ─── Meta / WhatsApp ─────────────────────────────────────────────────────

    /**
     * GET /webhooks/meta — Meta webhook verification handshake.
     */
    public function meta_verify(): void
    {
        $mode      = $_GET['hub_mode']          ?? '';
        $token     = $_GET['hub_verify_token']  ?? '';
        $challenge = $_GET['hub_challenge']      ?? '';

        $expected = env('WHATSAPP_WEBHOOK_VERIFY_TOKEN', '');

        if ($mode === 'subscribe' && hash_equals($expected, $token)) {
            http_response_code(200);
            echo $challenge;
            return;
        }

        $this->_reject(403, 'Verification failed.');
    }

    /**
     * POST /webhooks/meta
     *
     * Meta signs the payload with the App Secret using HMAC-SHA256.
     * The signature arrives in the `X-Hub-Signature-256` header as `sha256=<hex>`.
     */
    public function meta(): void
    {
        $raw = file_get_contents('php://input');

        $app_secret = env('WHATSAPP_APP_SECRET', '');

        if (empty($app_secret)) {
            $this->_reject(500, 'WHATSAPP_APP_SECRET not configured.');
            return;
        }

        $expected_sig = 'sha256=' . hash_hmac('sha256', $raw, $app_secret);
        $received_sig = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

        if (!hash_equals($expected_sig, $received_sig)) {
            $this->_reject(401, 'Invalid signature.');
            return;
        }

        $payload = json_decode($raw, true);

        if (!isset($payload['object'])) {
            $this->_reject(400, 'Unexpected payload structure.');
            return;
        }

        // Statuses e mensagens chegam aqui — tratamento expandido no D8
        log_message('info', '[Meta webhook] object=' . $payload['object']);

        http_response_code(200);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function _reject(int $code, string $reason): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['error' => $reason]);
        exit;
    }
}
