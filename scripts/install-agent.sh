#!/bin/bash
# scripts/install-agent.sh
# Usage: sudo bash install-agent.sh

set -e

NE_VERSION="1.8.1"
MONITOR_SERVER_IP="YOUR_MONITOR_SERVER_IP"

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
