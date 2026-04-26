<?php defined('BASEPATH') or exit('No direct script access allowed');

function asaas_request(string $method, string $path, array $body = null): array
{
    $ch = curl_init('https://api.asaas.com/v3' . $path);

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => [
            'access_token: ' . env('ASAAS_API_KEY'),
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => 15,
    ];

    if ($body !== null) {
        $options[CURLOPT_POSTFIELDS] = json_encode($body);
    }

    curl_setopt_array($ch, $options);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException("Asaas API request failed: $path");
    }

    $decoded = json_decode($response, true);

    if ($http_code >= 400) {
        $errors = $decoded['errors'][0]['description'] ?? $response;
        throw new RuntimeException("Asaas API error ($http_code): $errors");
    }

    return $decoded;
}

/**
 * Creates an Asaas subconta for a barbeiro.
 * Stores the returned apiKey encrypted (AES-256-CBC) — never plain text.
 */
function criar_subconta_barbeiro(array $barbeiro): array
{
    $response = asaas_request('POST', '/accounts', [
        'name'          => $barbeiro['business_name'],
        'email'         => $barbeiro['email'],
        'cpfCnpj'       => $barbeiro['cpf_cnpj'],
        'companyType'   => 'MEI',
        'mobilePhone'   => $barbeiro['phone'],
        'address'       => $barbeiro['address'],
        'addressNumber' => $barbeiro['address_number'],
        'province'      => $barbeiro['neighborhood'],
        'postalCode'    => $barbeiro['cep'],
    ]);

    // apiKey is returned only once — encrypt before any persistence
    $encrypted_api_key = crypto_encrypt($response['apiKey']);

    $CI = &get_instance();
    $CI->db->where('id', $barbeiro['id']);
    $CI->db->update('providers', [
        'asaas_account_id' => $response['id'],
        'asaas_api_key'    => $encrypted_api_key,
        'asaas_wallet_id'  => $response['walletId'],
    ]);

    unset($response['apiKey']); // never leak the plain key past this function
    return $response;
}

function gerar_pix_agendamento(array $appointment, float $valor_reais, string $barbeiro_wallet_id, float $fee_reais = 2.00): string
{
    $customer = asaas_request('POST', '/customers', [
        'name'        => $appointment['customer_name'],
        'mobilePhone' => $appointment['customer_phone'],
    ]);

    $payment = asaas_request('POST', '/payments', [
        'customer'          => $customer['id'],
        'billingType'       => 'PIX',
        'value'             => $valor_reais,
        'dueDate'           => date('Y-m-d', strtotime('+1 day')),
        'description'       => "Agendamento #{$appointment['id']} — {$appointment['service_name']}",
        'externalReference' => 'appt_' . $appointment['id'],
        'split'             => [[
            'walletId'   => $barbeiro_wallet_id,
            'fixedValue' => $valor_reais - $fee_reais,
        ]],
    ]);

    $pix = asaas_request('GET', '/payments/' . $payment['id'] . '/pixQrCode');

    $CI = &get_instance();
    $CI->db->where('id', $appointment['id']);
    $CI->db->update('appointments', [
        'asaas_payment_id' => $payment['id'],
        'pix_copia_cola'   => $pix['payload'],
        'pix_qr_base64'    => $pix['encodedImage'],
        'pix_expires_at'   => $pix['expirationDate'],
    ]);

    return $pix['payload'];
}
