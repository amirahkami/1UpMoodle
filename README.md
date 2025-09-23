# Moodle Development Stack

This Docker setup provides a local development environment for Moodle.

## Prerequisites

* Docker and Docker **Compose** installed on your host machine.

## Quick Start

1. Copy and adjust credentials in your `.env` file.
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

* Mailpit UI: [http://localhost:8025/](http://localhost:8025/)

## Enable Debugging

Set Moodle to the most verbose level to capture errors and warnings during development:

1. Go to **Site administration → Development → Debugging**.
2. Set **Debug messages** to **Developer** (shows all debug info).
3. Enable **Display debug messages**.

## See also

* [https://github.com/moodlehq/moodle-docker](https://github.com/moodlehq/moodle-docker)