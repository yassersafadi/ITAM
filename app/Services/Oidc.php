<?php
declare(strict_types=1);

namespace ITAM\Services;

/**
 * Single sign-on with Microsoft Entra ID (or any OpenID Connect provider).
 * Authorization-code flow with PKCE; the ID token signature is verified against the provider's
 * published keys, and issuer, audience, expiry and nonce are all checked before anyone is signed in.
 */
final class Oidc
{
    public static function enabled(): bool
    {
        return (bool) config('sso.enabled') && (string) config('sso.client_id') !== '' && self::authority() !== '';
    }

    public static function authority(): string
    {
        $custom = rtrim((string) config('sso.authority'), '/');
        if ($custom !== '') {
            return $custom;
        }
        $tenant = trim((string) config('sso.tenant'));
        return $tenant === '' ? '' : 'https://login.microsoftonline.com/' . rawurlencode($tenant) . '/v2.0';
    }

    public static function redirectUri(): string
    {
        return rtrim(Notifier::absolute('/') ?? '', '/') . url('auth/sso/callback');
    }

    /** URL to send the browser to; remembers state, nonce and the PKCE verifier in the session. */
    public static function authorizeUrl(): string
    {
        $meta = self::metadata();
        $state = bin2hex(random_bytes(16));
        $nonce = bin2hex(random_bytes(16));
        $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $_SESSION['sso'] = ['state' => $state, 'nonce' => $nonce, 'verifier' => $verifier, 'at' => time()];
        return $meta['authorization_endpoint'] . (str_contains($meta['authorization_endpoint'], '?') ? '&' : '?') . http_build_query([
            'client_id' => config('sso.client_id'), 'response_type' => 'code', 'redirect_uri' => self::redirectUri(),
            'response_mode' => 'query', 'scope' => 'openid profile email', 'state' => $state, 'nonce' => $nonce,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256', 'prompt' => 'select_account',
        ]);
    }

    /**
     * Complete the flow and return the verified identity.
     * @return array{sub: string, email: string, name: string, tid: ?string}
     */
    public static function callback(array $query): array
    {
        $pending = $_SESSION['sso'] ?? null;
        unset($_SESSION['sso']);
        if (isset($query['error'])) {
            throw new \RuntimeException((string) ($query['error_description'] ?? $query['error']));
        }
        if (!$pending || time() - $pending['at'] > 600 || !hash_equals($pending['state'], (string) ($query['state'] ?? ''))) {
            throw new \RuntimeException('Invalid or expired sign-in state.');
        }
        $meta = self::metadata();
        $token = self::post($meta['token_endpoint'], [
            'grant_type' => 'authorization_code', 'code' => (string) ($query['code'] ?? ''), 'redirect_uri' => self::redirectUri(),
            'client_id' => config('sso.client_id'), 'client_secret' => config('sso.client_secret'), 'code_verifier' => $pending['verifier'],
        ]);
        if (empty($token['id_token'])) {
            throw new \RuntimeException('The identity provider returned no ID token.');
        }
        $claims = self::verify((string) $token['id_token'], $meta, $pending['nonce']);
        $email = strtolower((string) ($claims['email'] ?? $claims['preferred_username'] ?? $claims['upn'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('The account has no e-mail address.');
        }
        $domains = PlatformSettings::emailList(str_replace(['@'], '', (string) config('sso.domains')));
        if ($domains && !in_array(substr(strrchr($email, '@'), 1), array_map('strtolower', $domains), true)) {
            throw new \RuntimeException('This e-mail domain is not allowed.');
        }
        return [
            'sub' => (string) ($claims['oid'] ?? $claims['sub']), 'email' => $email,
            'name' => (string) ($claims['name'] ?? $email), 'tid' => $claims['tid'] ?? null,
        ];
    }

    /** Verify an RS256 JWT against the provider keys and check its claims. */
    public static function verify(string $jwt, array $meta, string $nonce): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new \RuntimeException('Malformed ID token.');
        }
        [$h, $p, $s] = $parts;
        $header = json_decode(self::b64($h), true) ?: [];
        $claims = json_decode(self::b64($p), true) ?: [];
        if (($header['alg'] ?? '') !== 'RS256') {
            throw new \RuntimeException('Unsupported token algorithm.');
        }
        $key = self::key($meta, (string) ($header['kid'] ?? ''));
        if (openssl_verify("$h.$p", self::b64($s), $key, OPENSSL_ALGO_SHA256) !== 1) {
            throw new \RuntimeException('ID token signature is not valid.');
        }
        // Entra's multi-tenant metadata uses a {tenantid} placeholder in the issuer.
        $issuer = str_replace('{tenantid}', (string) ($claims['tid'] ?? ''), (string) $meta['issuer']);
        $now = time();
        $checks = [
            'issuer'   => ($claims['iss'] ?? '') === $issuer,
            'audience' => in_array((string) config('sso.client_id'), (array) ($claims['aud'] ?? []), true),
            'expiry'   => (int) ($claims['exp'] ?? 0) > $now - 60,
            'not yet'  => (int) ($claims['nbf'] ?? 0) <= $now + 60,
            'nonce'    => hash_equals($nonce, (string) ($claims['nonce'] ?? '')),
        ];
        foreach ($checks as $what => $ok) {
            if (!$ok) {
                throw new \RuntimeException("ID token rejected ($what).");
            }
        }
        // A multi-tenant authority (common / organizations) accepts accounts of every Microsoft organisation, whose
        // e-mail claims they control themselves: only the organisations listed in Settings may sign in.
        if (str_contains((string) $meta['issuer'], '{tenantid}')) {
            $allowed = array_map('strtolower', array_filter(array_map('trim', preg_split('/[\s,;]+/', (string) config('sso.allowed_tenants')) ?: [])));
            if (!in_array(strtolower((string) ($claims['tid'] ?? '')), $allowed, true)) {
                throw new \RuntimeException('This organisation is not allowed to sign in here.');
            }
        }
        return $claims;
    }

    private static function metadata(): array
    {
        $cacheKey = 'sso_meta_' . md5(self::authority());
        $cached = json_decode((string) db()->setting($cacheKey, ''), true);
        if (is_array($cached) && ($cached['_at'] ?? 0) > time() - 86400) {
            return $cached;
        }
        $meta = self::get(self::authority() . '/.well-known/openid-configuration');
        foreach (['authorization_endpoint', 'token_endpoint', 'jwks_uri', 'issuer'] as $k) {
            if (empty($meta[$k])) {
                throw new \RuntimeException("Provider metadata is missing $k.");
            }
        }
        $meta['_at'] = time();
        db()->setSetting($cacheKey, json_encode($meta));
        return $meta;
    }

    /** Public key for a key id, refreshing the key set once if the id is unknown (key rotation). */
    private static function key(array $meta, string $kid): \OpenSSLAsymmetricKey
    {
        foreach ([false, true] as $refresh) {
            $cacheKey = 'sso_jwks_' . md5($meta['jwks_uri']);
            $jwks = $refresh ? null : json_decode((string) db()->setting($cacheKey, ''), true);
            if (!is_array($jwks)) {
                $jwks = self::get($meta['jwks_uri']);
                db()->setSetting($cacheKey, json_encode($jwks));
            }
            foreach ($jwks['keys'] ?? [] as $jwk) {
                if (($jwk['kid'] ?? '') !== $kid || ($jwk['kty'] ?? '') !== 'RSA') {
                    continue;
                }
                $pem = !empty($jwk['x5c'][0])
                    ? "-----BEGIN CERTIFICATE-----\n" . chunk_split($jwk['x5c'][0], 64, "\n") . "-----END CERTIFICATE-----\n"
                    : self::rsaPem(self::b64($jwk['n']), self::b64($jwk['e']));
                $key = openssl_pkey_get_public($pem);
                if ($key) {
                    return $key;
                }
            }
        }
        throw new \RuntimeException('Signing key not found.');
    }

    /** SubjectPublicKeyInfo PEM from an RSA modulus and exponent. */
    private static function rsaPem(string $n, string $e): string
    {
        $int = fn (string $v) => self::der(0x02, (ord($v[0]) & 0x80) ? "\0" . $v : $v);
        $rsa = self::der(0x30, $int($n) . $int($e));
        $algo = self::der(0x30, self::der(0x06, "\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01") . "\x05\x00");
        $spki = self::der(0x30, $algo . self::der(0x03, "\0" . $rsa));
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private static function der(int $tag, string $value): string
    {
        $len = strlen($value);
        $l = $len < 128 ? chr($len) : (($b = ltrim(pack('N', $len), "\0")) !== '' ? chr(0x80 | strlen($b)) . $b : "\0");
        return chr($tag) . $l . $value;
    }

    private static function b64(string $v): string
    {
        return (string) base64_decode(strtr($v, '-_', '+/') . str_repeat('=', (4 - strlen($v) % 4) % 4));
    }

    private static function get(string $url): array
    {
        return self::http($url, null);
    }

    private static function post(string $url, array $fields): array
    {
        return self::http($url, http_build_query($fields));
    }

    private static function http(string $url, ?string $body): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ] + ($body !== null ? [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body] : []));
        $res = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        $json = is_string($res) ? json_decode($res, true) : null;
        if ($res === false || $code >= 400 || !is_array($json)) {
            $detail = is_array($json) ? ($json['error_description'] ?? $json['error'] ?? '') : $err;
            throw new \RuntimeException('Identity provider: ' . ($detail ?: "HTTP $code"));
        }
        return $json;
    }
}
