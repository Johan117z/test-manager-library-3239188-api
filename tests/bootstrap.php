<?php

/*
 * Test bootstrap: make sure the dedicated test database exists on the infra
 * PostgreSQL engine, so tests never touch the development database.
 * Only a database is created here; every table comes from the migrations.
 */

require __DIR__.'/../vendor/autoload.php';

$env = fn (string $key, string $default) => getenv($key) !== false ? getenv($key) : ($_ENV[$key] ?? $default);

(function () use ($env) {
    $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->safeLoad();

    $host = $env('DB_HOST', '127.0.0.1');
    $port = $env('DB_PORT', '5432');
    $user = $env('DB_USERNAME', 'library');
    $pass = $env('DB_PASSWORD', '');
    $name = $env('DB_DATABASE', 'library_test');

    if (! preg_match('/^[a-z_][a-z0-9_]*$/', $name)) {
        throw new RuntimeException("Invalid test database name: {$name}");
    }

    $pdo = new PDO("pgsql:host={$host};port={$port};dbname=postgres", $user, $pass);
    $exists = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = ?');
    $exists->execute([$name]);
    if (! $exists->fetchColumn()) {
        $pdo->exec("CREATE DATABASE {$name}");
    }
})();
