<?php
define('CLI_SCRIPT', true);

require_once('/bitnami/moodle/config.php');
require_once($CFG->libdir . '/clilib.php');

$host = getenv('MIDDLEWARE_API_HOST') ?: 'host.docker.internal';
$port = (int)(getenv('MIDDLEWARE_API_PORT') ?: 38000);
$apikey = getenv('MIDDLEWARE_API_KEY') ?: '';

if ($apikey === '') {
    cli_error('Set MIDDLEWARE_API_KEY in .env before configuring the JupyterHub NRW block.');
}

set_config('api_host', $host, 'block_jupyterhubnrw');
set_config('api_port', $port, 'block_jupyterhubnrw');
set_config('api_key', $apikey, 'block_jupyterhubnrw');
purge_all_caches();

cli_writeln("Configured JupyterHub NRW Middleware API at {$host}:{$port}");
