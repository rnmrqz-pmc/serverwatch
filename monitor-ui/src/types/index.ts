export type ServerStatus = 'up' | 'down' | 'degraded' | 'unknown';
export type UptimeBarStatus = 'up' | 'down' | 'degraded' | 'no-data';

export interface Server {
  name: string;
  instance: string;
  role: string;
  env: 'production' | 'staging';
  status: ServerStatus;
  uptime_pct: number;
  metrics: ServerMetrics | null;
  history: UptimeDay[];
  incidents?: Incident[];
}

export interface ServerMetrics {
  instance: string;
  cpu: number;
  memory: {
    total: number;
    used: number;
    percent: number;
  };
  disk: {
    total: number;
    used: number;
    percent: number;
  };
  uptime: number;
  load: number;
}

export interface UptimeDay {
  date: string;
  status: UptimeBarStatus;
  value: number;
}

export interface UptimeHistory {
  instance: string;
  days: number;
  uptime_pct: number;
  history: UptimeDay[];
  incidents?: Incident[];
}

export interface Incident {
  date: string;
  duration: string;
  type: string;
  message: string;
}

export interface Alert {
  id: string;
  name: string;
  instance: string;
  severity: 'critical' | 'warning' | 'info';
  state: 'firing' | 'resolved';
  summary: string;
  started_at: string;
  resolved_at: string | null;
}
