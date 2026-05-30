#!/bin/bash
# scripts/install-agent.sh
# Usage: sudo bash install-agent.sh

set -e

NE_VERSION="1.8.1"
POSTGRES_EXPORTER_VERSION="0.15.0"
MYSQL_EXPORTER_VERSION="0.16.0"
MONITOR_SERVER_IP="YOUR_MONITOR_SERVER_IP"
AUTO_ENABLE_DB_EXPORTERS="${AUTO_ENABLE_DB_EXPORTERS:-true}"
POSTGRES_EXPORTER_DSN="${POSTGRES_EXPORTER_DSN:-postgresql:///postgres?host=/var/run/postgresql&sslmode=disable}"
MYSQL_EXPORTER_CONFIG="${MYSQL_EXPORTER_CONFIG:-/etc/mysqld_exporter/.my.cnf}"

install_postgres_exporter() {
  if ! systemctl list-unit-files 'postgresql*.service' --no-legend 2>/dev/null | grep -q . \
    && ! pgrep -x postgres >/dev/null 2>&1; then
    return
  fi

  echo "PostgreSQL detected; installing postgres_exporter ${POSTGRES_EXPORTER_VERSION}..."

  useradd --no-create-home --shell /bin/false postgres_exporter 2>/dev/null || true

  wget -q https://github.com/prometheus-community/postgres_exporter/releases/download/v${POSTGRES_EXPORTER_VERSION}/postgres_exporter-${POSTGRES_EXPORTER_VERSION}.linux-amd64.tar.gz
  tar xvf postgres_exporter-${POSTGRES_EXPORTER_VERSION}.linux-amd64.tar.gz
  cp postgres_exporter-${POSTGRES_EXPORTER_VERSION}.linux-amd64/postgres_exporter /usr/local/bin/
  chown postgres_exporter:postgres_exporter /usr/local/bin/postgres_exporter

  tee /etc/systemd/system/postgres_exporter.service > /dev/null <<EOF
[Unit]
Description=PostgreSQL Exporter
After=network.target postgresql.service

[Service]
User=postgres
Environment=DATA_SOURCE_NAME=${POSTGRES_EXPORTER_DSN}
ExecStart=/usr/local/bin/postgres_exporter --web.listen-address=:9187
Restart=always

[Install]
WantedBy=multi-user.target
EOF

  systemctl daemon-reload
  systemctl enable --now postgres_exporter

  if command -v ufw >/dev/null 2>&1; then
    ufw allow from ${MONITOR_SERVER_IP} to any port 9187
    ufw deny 9187
    echo "Port 9187 restricted to ${MONITOR_SERVER_IP} via UFW"
  fi

  echo "postgres_exporter auto-enabled on :9187"
}

install_mysql_exporter() {
  if ! systemctl list-unit-files 'mysql.service' 'mysqld.service' 'mariadb.service' --no-legend 2>/dev/null | grep -q . \
    && ! pgrep -x mysqld >/dev/null 2>&1; then
    return
  fi

  if [ -z "${MYSQL_EXPORTER_USER:-}" ] || [ -z "${MYSQL_EXPORTER_PASSWORD:-}" ]; then
    echo "MySQL/MariaDB detected, but MYSQL_EXPORTER_USER and MYSQL_EXPORTER_PASSWORD are not set; skipped mysqld_exporter."
    echo "Create a low-privilege MySQL user, then rerun with MYSQL_EXPORTER_USER and MYSQL_EXPORTER_PASSWORD to auto-enable :9104."
    return
  fi

  echo "MySQL/MariaDB detected; installing mysqld_exporter ${MYSQL_EXPORTER_VERSION}..."

  useradd --no-create-home --shell /bin/false mysqld_exporter 2>/dev/null || true

  wget -q https://github.com/prometheus/mysqld_exporter/releases/download/v${MYSQL_EXPORTER_VERSION}/mysqld_exporter-${MYSQL_EXPORTER_VERSION}.linux-amd64.tar.gz
  tar xvf mysqld_exporter-${MYSQL_EXPORTER_VERSION}.linux-amd64.tar.gz
  cp mysqld_exporter-${MYSQL_EXPORTER_VERSION}.linux-amd64/mysqld_exporter /usr/local/bin/
  chown mysqld_exporter:mysqld_exporter /usr/local/bin/mysqld_exporter

  install -d -m 0750 -o mysqld_exporter -g mysqld_exporter "$(dirname "${MYSQL_EXPORTER_CONFIG}")"
  tee "${MYSQL_EXPORTER_CONFIG}" > /dev/null <<EOF
[client]
user=${MYSQL_EXPORTER_USER}
password=${MYSQL_EXPORTER_PASSWORD}
host=${MYSQL_EXPORTER_HOST:-localhost}
port=${MYSQL_EXPORTER_PORT:-3306}
EOF
  chown mysqld_exporter:mysqld_exporter "${MYSQL_EXPORTER_CONFIG}"
  chmod 0640 "${MYSQL_EXPORTER_CONFIG}"

  tee /etc/systemd/system/mysqld_exporter.service > /dev/null <<EOF
[Unit]
Description=MySQL Server Exporter
After=network.target mysql.service mysqld.service mariadb.service

[Service]
User=mysqld_exporter
ExecStart=/usr/local/bin/mysqld_exporter --config.my-cnf=${MYSQL_EXPORTER_CONFIG} --web.listen-address=:9104
Restart=always

[Install]
WantedBy=multi-user.target
EOF

  systemctl daemon-reload
  systemctl enable --now mysqld_exporter

  if command -v ufw >/dev/null 2>&1; then
    ufw allow from ${MONITOR_SERVER_IP} to any port 9104
    ufw deny 9104
    echo "Port 9104 restricted to ${MONITOR_SERVER_IP} via UFW"
  fi

  echo "mysqld_exporter auto-enabled on :9104"
}

echo "Installing Node Exporter ${NE_VERSION}..."

useradd --no-create-home --shell /bin/false node_exporter 2>/dev/null || true

wget -q https://github.com/prometheus/node_exporter/releases/download/v${NE_VERSION}/node_exporter-${NE_VERSION}.linux-amd64.tar.gz
tar xvf node_exporter-${NE_VERSION}.linux-amd64.tar.gz
cp node_exporter-${NE_VERSION}.linux-amd64/node_exporter /usr/local/bin/
chown node_exporter:node_exporter /usr/local/bin/node_exporter

tee /etc/systemd/system/node_exporter.service > /dev/null <<EOF
[Unit]
Description=Node Exporter
After=network.target

[Service]
User=node_exporter
ExecStart=/usr/local/bin/node_exporter \
  --collector.systemd \
  --collector.processes
Restart=always

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable --now node_exporter

# Restrict port 9100 to monitoring server only
if command -v ufw >/dev/null 2>&1; then
  ufw allow from ${MONITOR_SERVER_IP} to any port 9100
  ufw deny 9100
  echo "Port 9100 restricted to ${MONITOR_SERVER_IP} via UFW"
else
  echo "UFW not found, skipped port restriction. Please configure your firewall manually."
fi

echo "Node Exporter installed and running on :9100"

if [ "${AUTO_ENABLE_DB_EXPORTERS}" = "true" ]; then
  install_postgres_exporter
  install_mysql_exporter
else
  echo "Database exporter auto-enable disabled (AUTO_ENABLE_DB_EXPORTERS=${AUTO_ENABLE_DB_EXPORTERS})."
fi
