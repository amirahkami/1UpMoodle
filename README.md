# Moodle Development Stack

This Docker setup provides a local development environment for Moodle.

## Prerequisites

* Docker and Docker **Compose** installed on your host machine.

## Quick Start

1. Copy and adjust credentials in your `.env` file.

```
cp .env.example .env
```

2. Build and start the containers:

```
docker compose build --no-cache
docker compose up -d
```

3. Before your first login, install frontend dependencies inside the Moodle container:

```
# Inside the container (Moodle root):
cd /bitnami/moodle
npm install
```

You can now proceed to use moodle and install plugins

Moodle is available at [http://localhost:28082/](http://localhost:28082/).

For a new local database, install Moodle before opening the site:

```bash
./scripts/install-fresh.sh
```

## Keycloak Development Login

This stack is prepared to connect to the sibling `1UpKeyCloak` development realm.
For the cross-repo overview, see `1UpKeyCloak/docs/local-apps.md`.

Start Keycloak first from the `1UpKeyCloak` repository:

```
cp .env.example .env
docker compose up -d
```

The expected Keycloak realm and Moodle OIDC client are:

```text
Issuer: http://1up-keycloak.localhost:28080/realms/university-dev
Client ID: moodle
Client secret: moodle-dev-secret
Redirect base: http://localhost:28082
```

`1up-keycloak.localhost` resolves to the local machine in browsers. The Moodle
container maps it to the Docker host through `extra_hosts`.

Install local plugins and configure Moodle's OAuth 2 issuer:

```
docker compose exec v53 php /bitnami/moodle/admin/cli/upgrade.php --non-interactive
docker compose exec v53 php /opt/1upmoodle/scripts/configure-keycloak-oauth.php
docker compose exec v53 php /opt/1upmoodle/scripts/configure-jupyterhubnrw.php
```

The script enables Moodle's core OAuth 2 authentication plugin and creates a custom OAuth 2 service with these endpoints:

```text
Authorization endpoint: http://1up-keycloak.localhost:28080/realms/university-dev/protocol/openid-connect/auth
Token endpoint: http://1up-keycloak.localhost:28080/realms/university-dev/protocol/openid-connect/token
Userinfo endpoint: http://1up-keycloak.localhost:28080/realms/university-dev/protocol/openid-connect/userinfo
Scopes: openid profile email roles
Username claim: preferred_username
Email claim: email
First name claim: given_name
Last name claim: family_name
```

The stack also mounts `plugins/local/keycloakrolesync` into Moodle. On every OAuth login it reads
Keycloak's `moodle_roles` claim and syncs these global Moodle permissions:

```text
moodle_roles: admin          -> Moodle site admin
moodle_roles: manager        -> Moodle system manager
moodle_roles: course_creator -> Moodle system course creator
moodle_roles: student        -> normal authenticated user
moodle_roles: guest          -> no extra global role
moodle_roles: teacher        -> skipped until course enrolments are defined
```

Seeded Keycloak test user:

```text
maya.chen / MayaChen@unreal
anna.meyer / AnnaMeyer@unreal
```

## Running Grunt

Moodle uses **Grunt** (a JavaScript task runner) to automate common JS/CSS tasks. Grunt builds, minifies, and lints assets for production.

Run Grunt from the directory that contains the plugin’s amd folder.

Examples:

```
# Local plugin
cd /bitnami/moodle/local/<your_plugin>/amd
grunt

# Block plugin
cd /bitnami/moodle/blocks/<your_plugin>/amd
grunt
```

## SMTP (Mailpit)

All outgoing mail is routed to the SMTP server. By default, **Mailpit** captures messages for viewing in its UI and does not deliver them to the internet.

* Mailpit UI: [http://localhost:28026/](http://localhost:28026/)

## Enable Debugging

Set Moodle to the most verbose level to capture errors and warnings during development:

1. Go to **Site administration → Development → Debugging**.
2. Set **Debug messages** to **Developer** (shows all debug info).
3. Enable **Display debug messages**.

## See also

* [https://github.com/moodlehq/moodle-docker](https://github.com/moodlehq/moodle-docker)
