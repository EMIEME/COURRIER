#!/usr/bin/env bash
set -euo pipefail

DOMAIN="${DOMAIN:-ddadoc.org}"
WWW_DOMAIN="${WWW_DOMAIN:-www.ddadoc.org}"
LE_EMAIL="${LE_EMAIL:-}"

if [[ "${EUID}" -ne 0 ]]; then
    echo "Run this script with sudo:" >&2
    echo "sudo bash /var/www/gestioncourrier/deploy/enable-https-certbot.sh" >&2
    exit 1
fi

export DEBIAN_FRONTEND=noninteractive

apt-get update
apt-get install -y certbot python3-certbot-nginx

nginx -t

certbot_args=(
    --nginx
    -d "${DOMAIN}" \
    -d "${WWW_DOMAIN}" \
    --redirect \
    --agree-tos \
    --non-interactive
)

if [[ -n "${LE_EMAIL}" ]]; then
    certbot_args+=(--email "${LE_EMAIL}")
else
    certbot_args+=(--register-unsafely-without-email)
fi

certbot "${certbot_args[@]}"

nginx -t
systemctl reload nginx

systemctl status certbot.timer --no-pager || true
certbot certificates
