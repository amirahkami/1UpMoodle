<?php
define('CLI_SCRIPT', true);

require_once('/bitnami/moodle/config.php');
require_once($CFG->libdir . '/clilib.php');

global $DB, $CFG;

$issuerbase = getenv('KEYCLOAK_ISSUER') ?: 'http://1up-keycloak.localhost:28080/realms/university-dev';
$clientid = getenv('KEYCLOAK_CLIENT_ID') ?: 'moodle';
$clientsecret = getenv('KEYCLOAK_CLIENT_SECRET') ?: 'moodle-dev-secret';
$now = time();
$adminid = (int)($DB->get_field('user', 'id', ['username' => 'admin']) ?: 2);

function upsert_record(string $table, array $match, array $values): int {
    global $DB, $now, $adminid;

    $record = $DB->get_record($table, $match);
    if ($record) {
        foreach ($values as $key => $value) {
            $record->{$key} = $value;
        }
        $record->timemodified = $now;
        $record->usermodified = $adminid;
        $DB->update_record($table, $record);
        return (int)$record->id;
    }

    $record = (object)array_merge($match, $values, [
        'timecreated' => $now,
        'timemodified' => $now,
        'usermodified' => $adminid,
    ]);

    return (int)$DB->insert_record($table, $record);
}

$issuerid = upsert_record('oauth2_issuer', ['name' => 'Unreal University Keycloak'], [
    'image' => '',
    'baseurl' => $issuerbase,
    'clientid' => $clientid,
    'clientsecret' => $clientsecret,
    'loginscopes' => 'openid profile email roles',
    'loginscopesoffline' => 'openid profile email roles',
    'loginparams' => '',
    'loginparamsoffline' => '',
    'alloweddomains' => '',
    'scopessupported' => 'openid profile email roles',
    'enabled' => 1,
    'showonloginpage' => 1,
    'basicauth' => 0,
    'sortorder' => 0,
    'requireconfirmation' => 0,
    'servicetype' => null,
    'loginpagename' => 'Unreal University Login',
    'systememail' => null,
]);

$endpoints = [
    'authorization_endpoint' => "$issuerbase/protocol/openid-connect/auth",
    'token_endpoint' => "$issuerbase/protocol/openid-connect/token",
    'userinfo_endpoint' => "$issuerbase/protocol/openid-connect/userinfo",
];

foreach ($endpoints as $name => $url) {
    upsert_record('oauth2_endpoint', ['issuerid' => $issuerid, 'name' => $name], [
        'url' => $url,
    ]);
}

$mappings = [
    'preferred_username' => 'username',
    'given_name' => 'firstname',
    'family_name' => 'lastname',
    'email' => 'email',
];

foreach ($mappings as $external => $internal) {
    upsert_record('oauth2_user_field_mapping', ['issuerid' => $issuerid, 'internalfield' => $internal], [
        'externalfield' => $external,
    ]);
}

$enabledauth = array_filter(array_map('trim', explode(',', get_config('core', 'auth') ?: '')));
if (!in_array('oauth2', $enabledauth, true)) {
    $enabledauth[] = 'oauth2';
    set_config('auth', implode(',', $enabledauth));
}

// Local Keycloak and Middleware run on the Docker host gateway. Moodle's
// default cURL security policy blocks those private-network targets.
$issuerport = (int)(parse_url($issuerbase, PHP_URL_PORT) ?: 443);
$middlewareport = (int)(getenv('MIDDLEWARE_API_PORT') ?: 38000);
set_config('curlsecurityallowedport', implode("\n", array_unique([
    '443',
    '80',
    (string)$issuerport,
    (string)$middlewareport,
])));
set_config('curlsecurityblockedhosts', implode("\n", [
    '127.0.0.0/8',
    '10.0.0.0/8',
    '172.16.0.0/12',
    '0.0.0.0',
    'localhost',
    '169.254.169.254',
    '0000::1',
]));

purge_all_caches();

if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
    exec('chown -R daemon:daemon ' . escapeshellarg($CFG->dataroot));
}

cli_writeln("Configured Keycloak OAuth2 issuer #{$issuerid} for {$issuerbase}");
