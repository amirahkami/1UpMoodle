# Dockerfile

# FROM bitnami/moodle:latest not working anymore
FROM bitnamilegacy/moodle:latest

RUN apt-get update \
 && apt-get install -y --no-install-recommends curl ca-certificates gnupg \
 && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
 && apt-get install -y --no-install-recommends nodejs \
 && npm install -g grunt-cli \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /bitnami/moodle