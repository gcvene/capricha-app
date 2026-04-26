<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Encrypts a string using AES-256-CBC with a random IV.
 * Returns hex(iv) + ':' + base64(ciphertext), safe for DB storage.
 *
 * @throws RuntimeException if CRYPTO_KEY is missing or invalid.
 */
function crypto_encrypt(string $plaintext): string
{
    $key = _crypto_key();
    $iv = random_bytes(16);
    $ciphertext = openssl_encrypt($plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

    if ($ciphertext === false) {
        throw new RuntimeException('Encryption failed.');
    }

    return bin2hex($iv) . ':' . base64_encode($ciphertext);
}

/**
 * Decrypts a string produced by crypto_encrypt().
 *
 * @throws RuntimeException if the payload is malformed or CRYPTO_KEY is invalid.
 */
function crypto_decrypt(string $payload): string
{
    $parts = explode(':', $payload, 2);

    if (count($parts) !== 2) {
        throw new RuntimeException('Malformed encrypted payload.');
    }

    $key = _crypto_key();
    $iv = hex2bin($parts[0]);
    $plaintext = openssl_decrypt(base64_decode($parts[1]), 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

    if ($plaintext === false) {
        throw new RuntimeException('Decryption failed — wrong key or corrupted payload.');
    }

    return $plaintext;
}

function _crypto_key(): string
{
    $hex = env('CRYPTO_KEY', '');

    if (strlen($hex) !== 64 || !ctype_xdigit($hex)) {
        throw new RuntimeException(
            'CRYPTO_KEY must be a 64-character hex string (32 bytes). ' .
            'Generate one with: php -r "echo bin2hex(random_bytes(32));"'
        );
    }

    return hex2bin($hex);
}
