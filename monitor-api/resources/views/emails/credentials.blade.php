<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>{{ $subject }}</title>
    <style type="text/css">
        body {
            width: 100% !important;
            height: 100%;
            margin: 0;
            line-height: 1.4;
            background-color: #f8fafc;
            color: #334155;
            -webkit-text-size-adjust: none;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }
        @media only screen and (max-width: 600px) {
            .email-body_inner {
                width: 100% !important;
            }
        }
    </style>
</head>
<body style="width: 100% !important; height: 100%; margin: 0; line-height: 1.6; background-color: #f8fafc; color: #334155; -webkit-text-size-adjust: none; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; padding: 40px 20px;">
    <table class="email-wrapper" width="100%" cellpadding="0" cellspacing="0" style="width: 100%; margin: 0; padding: 0; background-color: #f8fafc;">
        <tr>
            <td align="center">
                <table class="email-content" width="100%" cellpadding="0" cellspacing="0" style="max-width: 570px; margin: 0 auto;">
                    <!-- Logo / Header -->
                    <tr>
                        <td class="email-masthead" style="padding: 25px 0; text-align: center;">
                            <a href="{{ $appUrl }}" style="font-size: 22px; font-weight: 800; color: #0f172a; text-decoration: none; letter-spacing: -0.5px;">
                                ServerWatcher
                            </a>
                        </td>
                    </tr>
                    
                    <!-- Email Body -->
                    <tr>
                        <td class="email-body" width="100%" style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 40px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);">
                            <h1 style="font-size: 24px; font-weight: 700; color: #0f172a; margin-top: 0; margin-bottom: 24px; letter-spacing: -0.5px; line-height: 1.25;">
                                {{ $title }}
                            </h1>
                            
                            <p style="font-size: 16px; line-height: 1.6; color: #334155; margin-top: 0; margin-bottom: 24px;">
                                {{ $greeting }}
                            </p>
                            
                            <p style="font-size: 15px; line-height: 1.6; color: #475569; margin-top: 0; margin-bottom: 24px;">
                                {{ $bodyText }}
                            </p>
                            
                            <!-- Credentials Card -->
                            <table class="credentials-card" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 30px; border-collapse: separate;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <table width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td width="90" style="font-size: 14px; font-weight: 600; color: #64748b; padding-bottom: 12px; vertical-align: middle;">
                                                    Email
                                                </td>
                                                <td style="font-size: 14px; color: #0f172a; padding-bottom: 12px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-weight: 600;">
                                                    {{ $email }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td width="90" style="font-size: 14px; font-weight: 600; color: #64748b; vertical-align: middle;">
                                                    Password
                                                </td>
                                                <td style="font-size: 14px; color: #0f172a; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-weight: 600;">
                                                    <span style="background-color: #e2e8f0; padding: 3px 8px; border-radius: 4px; display: inline-block;">
                                                        {{ $password }}
                                                    </span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Action Button -->
                            <table class="body-action" align="center" width="100%" cellpadding="0" cellspacing="0" style="margin-top: 30px; margin-bottom: 30px; text-align: center;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $appUrl }}" style="background-color: #0f172a; color: #ffffff; text-decoration: none; padding: 12px 30px; border-radius: 8px; font-size: 14px; font-weight: 600; display: inline-block; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1); border: 1px solid #0f172a;" target="_blank">
                                            Go to Dashboard
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <p class="note" style="font-size: 13px; line-height: 1.6; color: #64748b; margin-top: 24px; margin-bottom: 0; font-style: italic;">
                                {{ $note }}
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 30px 0; text-align: center;">
                            <p style="font-size: 12px; line-height: 1.6; color: #94a3b8; margin: 0 0 8px 0;">
                                This is an automated notification. Please do not reply directly.
                            </p>
                            <p style="font-size: 12px; line-height: 1.6; color: #94a3b8; margin: 0;">
                                &copy; {{ date('Y') }} ServerWatcher. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
