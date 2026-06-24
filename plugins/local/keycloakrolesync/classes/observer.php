<?php
// This file is part of Moodle - http://moodle.org/.

namespace local_keycloakrolesync;

defined('MOODLE_INTERNAL') || die();

use core\event\user_loggedin;
use context_system;

/**
 * Syncs trusted Keycloak Moodle client roles after OAuth login.
 */
class observer {
    /** @var string Moodle OAuth client id inside Keycloak access tokens. */
    private const CLIENT_ID = 'moodle';

    /** @var string Plugin component used to mark role assignments managed here. */
    private const COMPONENT = 'local_keycloakrolesync';

    /**
     * Handle Moodle login events.
     *
     * @param user_loggedin $event Login event.
     */
    public static function user_loggedin(user_loggedin $event): void {
        global $DB;

        $user = $DB->get_record('user', ['id' => $event->userid, 'deleted' => 0], '*', IGNORE_MISSING);
        if (!$user || $user->auth !== 'oauth2') {
            return;
        }

        $roles = self::get_event_moodle_roles($event) ?? self::get_keycloak_moodle_roles();
        if ($roles === null) {
            return;
        }

        self::sync_site_admin((int)$user->id, in_array('admin', $roles, true));
        self::sync_system_role((int)$user->id, 'manager', in_array('manager', $roles, true));
        self::sync_system_role((int)$user->id, 'coursecreator', in_array('course_creator', $roles, true));
    }

    /**
     * Read Moodle client roles from the OAuth login event userinfo payload.
     *
     * @param user_loggedin $event Login event.
     * @return string[]|null Role names.
     */
    private static function get_event_moodle_roles(user_loggedin $event): ?array {
        $roles = $event->other['extrauserinfo']['moodle_roles'] ?? null;
        if (is_string($roles)) {
            $roles = [$roles];
        }

        if (!is_array($roles)) {
            return null;
        }

        return array_values(array_unique(array_filter($roles, 'is_string')));
    }

    /**
     * Read Moodle client roles from the Keycloak access token stored by Moodle OAuth.
     *
     * @return string[]|null Role names, or null when the current login has no readable Keycloak token.
     */
    private static function get_keycloak_moodle_roles(): ?array {
        global $SESSION;

        foreach ((array)$SESSION as $name => $value) {
            if (strpos((string)$name, 'core\oauth2\client-') !== 0 && strpos((string)$name, 'core\\oauth2\\client-') !== 0) {
                continue;
            }
            if (empty($value->token) || !is_string($value->token)) {
                continue;
            }

            $payload = self::decode_jwt_payload($value->token);
            if (!$payload) {
                continue;
            }

            $roles = $payload['resource_access'][self::CLIENT_ID]['roles'] ?? null;
            if (is_array($roles)) {
                return array_values(array_unique(array_filter($roles, 'is_string')));
            }
        }

        return null;
    }

    /**
     * Decode a JWT payload without signature verification.
     *
     * Moodle has already completed the OAuth flow with this token before the login event is emitted.
     *
     * @param string $jwt JWT string.
     * @return array|null Decoded payload.
     */
    private static function decode_jwt_payload(string $jwt): ?array {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            return null;
        }

        $payload = strtr($parts[1], '-_', '+/');
        $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
        $json = base64_decode($payload, true);
        if ($json === false) {
            return null;
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Add or remove a synced Moodle site admin entry.
     *
     * @param int $userid Moodle user id.
     * @param bool $shouldbeadmin Whether Keycloak grants moodle:admin.
     */
    private static function sync_site_admin(int $userid, bool $shouldbeadmin): void {
        $admins = array_values(array_filter(array_map('intval', explode(',', get_config('core', 'siteadmins') ?: ''))));
        $markername = 'siteadmin_' . $userid;
        $wasmanaged = (bool)get_config(self::COMPONENT, $markername);

        if ($shouldbeadmin) {
            if (!in_array($userid, $admins, true)) {
                $admins[] = $userid;
                set_config('siteadmins', implode(',', array_unique($admins)));
            }
            set_config($markername, '1', self::COMPONENT);
            return;
        }

        if ($wasmanaged) {
            $admins = array_values(array_filter($admins, static fn($adminid) => $adminid !== $userid));
            set_config('siteadmins', implode(',', $admins));
            unset_config($markername, self::COMPONENT);
        }
    }

    /**
     * Add or remove a synced system-context role assignment.
     *
     * @param int $userid Moodle user id.
     * @param string $roleshortname Moodle role shortname.
     * @param bool $shouldhave Whether Keycloak grants the corresponding role.
     */
    private static function sync_system_role(int $userid, string $roleshortname, bool $shouldhave): void {
        global $DB;

        $roleid = $DB->get_field('role', 'id', ['shortname' => $roleshortname]);
        if (!$roleid) {
            return;
        }

        $context = context_system::instance();
        $managedassignment = $DB->get_record('role_assignments', [
            'userid' => $userid,
            'roleid' => $roleid,
            'contextid' => $context->id,
            'component' => self::COMPONENT,
        ], '*', IGNORE_MISSING);

        if ($shouldhave && !$managedassignment) {
            role_assign((int)$roleid, $userid, $context->id, self::COMPONENT);
            return;
        }

        if (!$shouldhave && $managedassignment) {
            role_unassign((int)$roleid, $userid, $context->id, self::COMPONENT);
        }
    }
}
