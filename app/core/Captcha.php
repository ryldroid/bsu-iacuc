<?php
class Captcha
{
    // ===== VERIFY TURNSTILE TOKEN =====
    public static function verify(string $token): bool
    {
        if ($token === '' || TURNSTILE_SECRET_KEY === '') {
            return false;
        }

        $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_POSTFIELDS     => http_build_query([
                'secret'   => TURNSTILE_SECRET_KEY,
                'response' => $token,
            ]),
        ]);

        if ($ca = PdfConverter::caBundle()) {
            curl_setopt($ch, CURLOPT_CAINFO, $ca);
        }

        $result = curl_exec($ch);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($error || !is_string($result)) {
            error_log('Turnstile verify failed: ' . ($error ?: 'no response'));
            return false;
        }

        $data = json_decode($result, true);
        return !empty($data['success']);
    }
}
