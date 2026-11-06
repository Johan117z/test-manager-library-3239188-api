<?php

/*
 * RNF-2 / T-13 evidence: two librarians lend the LAST copy at the same time -> exactly one 201 and one 409.
 *
 * Runs real parallel HTTP requests against the running API (php artisan serve with PHP_CLI_SERVER_WORKERS > 1):
 *   docker compose run --rm api php tests/Concurrency/last_copy.php
 * Uses ADMIN_USERNAME / ADMIN_PASSWORD from .env. It creates its own book and members in the dev database.
 */

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
Dotenv\Dotenv::createImmutable($root)->safeLoad();

$base = getenv('API_URL') ?: 'http://manager-library-api:8000/api/v1';
$rounds = (int) (getenv('ROUNDS') ?: 5);

function request(string $method, string $url, ?array $body, ?string $token): CurlHandle
{
    $ch = curl_init($url);
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($token) {
        $headers[] = "Authorization: Bearer {$token}";
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => $body === null ? null : json_encode($body),
    ]);

    return $ch;
}

function call(string $method, string $url, ?array $body = null, ?string $token = null): array
{
    $ch = request($method, $url, $body, $token);
    $out = curl_exec($ch);

    return [curl_getinfo($ch, CURLINFO_HTTP_CODE), json_decode((string) $out, true)];
}

[$code, $login] = call('POST', "{$base}/auth/login", ['username' => $_ENV['ADMIN_USERNAME'], 'password' => $_ENV['ADMIN_PASSWORD']]);
if ($code !== 200) {
    fwrite(STDERR, "Login failed ({$code}).\n");
    exit(1);
}
$token = $login['access_token'];
$failures = 0;

for ($round = 1; $round <= $rounds; $round++) {
    $tag = bin2hex(random_bytes(4));
    [, $book] = call('POST', "{$base}/books", [
        'title' => "Último ejemplar {$tag}", 'author' => 'Prueba', 'isbn' => '979'.str_pad((string) random_int(0, 9_999_999_999), 10, '0', STR_PAD_LEFT),
        'category_id' => '11111111-1111-4111-8111-111111111111', 'total_copies' => 1,
    ], $token);
    $members = [];
    foreach (['a', 'b'] as $m) {
        [, $member] = call('POST', "{$base}/members", ['full_name' => "Socio {$m} {$tag}", 'document_number' => "C-{$tag}-{$m}"], $token);
        $members[] = $member['id'];
    }

    // Fire both loans at the same time.
    $multi = curl_multi_init();
    $handles = [];
    foreach ($members as $memberId) {
        $h = request('POST', "{$base}/loans", ['member_id' => $memberId, 'items' => [['book_id' => $book['id']]]], $token);
        curl_multi_add_handle($multi, $h);
        $handles[] = $h;
    }
    do {
        curl_multi_exec($multi, $running);
        curl_multi_select($multi);
    } while ($running > 0);

    $codes = array_map(fn ($h) => curl_getinfo($h, CURLINFO_HTTP_CODE), $handles);
    $bodies = array_map(fn ($h) => json_decode((string) curl_multi_getcontent($h), true), $handles);
    sort($codes);
    [, $after] = call('GET', "{$base}/books/{$book['id']}", null, $token);

    $loser = array_values(array_filter($bodies, fn ($b) => isset($b['code'])))[0]['code'] ?? '-';
    $ok = $codes === [201, 409] && $loser === 'BOOK_NOT_AVAILABLE' && $after['available_copies'] === 0;
    $failures += $ok ? 0 : 1;
    printf("round %d: %s, loser=%s, available_copies=%d -> %s\n", $round, implode('/', $codes), $loser, $after['available_copies'], $ok ? 'OK' : 'FAIL');
}

exit($failures === 0 ? 0 : 1);
