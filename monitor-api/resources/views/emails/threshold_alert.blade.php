<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ServerWatch Notification</title>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }
        .container {
            max-width: 580px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        .header {
            padding: 32px 32px 24px;
            border-bottom: 1px solid #f1f5f9;
        }
        .logo {
            font-weight: 700;
            font-size: 18px;
            color: #aa3bff;
            letter-spacing: -0.02em;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            border-radius: 30px;
            letter-spacing: 0.05em;
            margin-top: 16px;
        }
        /* Severity Colors */
        .badge-critical { color: #ef4444; background-color: #fef2f2; border: 1px solid #fee2e2; }
        .badge-warning { color: #f97316; background-color: #fff7ed; border: 1px solid #ffedd5; }
        .badge-info { color: #3b82f6; background-color: #eff6ff; border: 1px solid #dbeafe; }
        .badge-resolved { color: #10b981; background-color: #ecfdf5; border: 1px solid #d1fae5; }

        .content {
            padding: 32px;
        }
        .title {
            font-size: 20px;
            font-weight: 700;
            margin-top: 0;
            margin-bottom: 8px;
            color: #0f172a;
            letter-spacing: -0.01em;
        }
        .description {
            font-size: 15px;
            color: #64748b;
            line-height: 1.5;
            margin-top: 0;
            margin-bottom: 24px;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            background-color: #f8fafc;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 32px;
        }
        .details-table td {
            padding: 14px 16px;
            font-size: 14px;
            border-bottom: 1px solid #f1f5f9;
        }
        .details-table tr:last-child td {
            border-bottom: none;
        }
        .label {
            color: #64748b;
            font-weight: 500;
            width: 35%;
        }
        .value {
            color: #1e293b;
            font-weight: 600;
        }
        .btn-container {
            text-align: center;
        }
        .btn {
            display: inline-block;
            background-color: #aa3bff;
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 24px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 8px;
            transition: background-color 0.2s;
        }
        .footer {
            padding: 24px 32px;
            background-color: #f8fafc;
            border-top: 1px solid #f1f5f9;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
        }
        .footer a {
            color: #aa3bff;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">ServerWatch</div>
            @if($isResolution)
                <span class="status-badge badge-resolved">RESOLVED</span>
            @else
                <span class="status-badge badge-{{ $severity }}">BREACH: {{ $severity }}</span>
            @endif
        </div>
        <div class="content">
            <h1 class="title">
                @if($isResolution)
                    {{ $metric }} Utilization Normal
                @else
                    {{ $metric }} Threshold Breach
                @endif
            </h1>
            <p class="description">
                @if($isResolution)
                    The alert threshold for {{ $metric }} on server <strong>{{ $serverName }}</strong> is no longer breached. Usage has returned to normal.
                @else
                    A threshold breach was detected for {{ $metric }} on server <strong>{{ $serverName }}</strong>.
                @endif
            </p>
            
            <table class="details-table">
                <tr>
                    <td class="label">Server</td>
                    <td class="value">{{ $serverName }} ({{ $serverIp }})</td>
                </tr>
                <tr>
                    <td class="label">Metric</td>
                    <td class="value">{{ $metric }}</td>
                </tr>
                <tr>
                    <td class="label">Current Value</td>
                    <td class="value" style="color: {{ $isResolution ? '#10b981' : ($severity === 'critical' ? '#ef4444' : '#f97316') }}">{{ $value }}%</td>
                </tr>
                @if(!$isResolution)
                <tr>
                    <td class="label">Threshold Value</td>
                    <td class="value">{{ $thresholdVal }}%</td>
                </tr>
                @endif
                <tr>
                    <td class="label">Time Reported</td>
                    <td class="value">{{ $time }}</td>
                </tr>
            </table>

            <div class="btn-container">
                <a href="{{ $appUrl }}" class="btn" target="_blank">View Dashboard</a>
            </div>
        </div>
        <div class="footer">
            Sent by <a href="{{ $appUrl }}">ServerWatch Monitoring System</a>.
        </div>
    </div>
</body>
</html>
