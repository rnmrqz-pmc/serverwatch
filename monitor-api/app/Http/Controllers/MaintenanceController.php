<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Mail;

class MaintenanceController extends Controller
{
    /**
     * Get the current SMTP configurations.
     */
    public function getSmtpSettings(): JsonResponse
    {
        if (!request()->user()->hasPermission('maintenance', 'view')) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to view maintenance settings.'], 403);
        }

        return response()->json([
            'mail_host'         => Setting::get('mail_host', config('mail.mailers.smtp.host')),
            'mail_port'         => (int) Setting::get('mail_port', config('mail.mailers.smtp.port') ?: 587),
            'mail_encryption'   => Setting::get('mail_encryption', config('mail.mailers.smtp.encryption') ?: 'none'),
            'mail_username'     => Setting::get('mail_username', config('mail.mailers.smtp.username')),
            'mail_from_address' => Setting::get('mail_from_address', config('mail.from.address')),
            'mail_from_name'    => Setting::get('mail_from_name', config('mail.from.name')),
            'has_password'      => !empty(Setting::get('mail_password')),
        ]);
    }

    /**
     * Update the SMTP configurations.
     */
    public function updateSmtpSettings(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('maintenance', 'update')) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to update maintenance settings.'], 403);
        }

        $validated = $request->validate([
            'mail_host'         => 'required|string',
            'mail_port'         => 'required|integer|min:1|max:65535',
            'mail_encryption'   => 'required|string|in:none,tls,ssl',
            'mail_username'     => 'nullable|string',
            'mail_password'     => 'nullable|string|max:1024',
            'mail_from_address' => 'required|email',
            'mail_from_name'    => 'required|string',
        ]);

        Setting::set('mail_host', $validated['mail_host']);
        Setting::set('mail_port', $validated['mail_port']);
        Setting::set('mail_encryption', $validated['mail_encryption']);
        Setting::set('mail_username', $validated['mail_username']);
        Setting::set('mail_from_address', $validated['mail_from_address']);
        Setting::set('mail_from_name', $validated['mail_from_name']);

        if ($request->has('mail_password')) {
            $pass = $request->input('mail_password');
            if ($pass === null || $pass === '') {
                Setting::set('mail_password', null);
            } else {
                Setting::set('mail_password', encrypt($pass));
            }
        }

        return response()->json([
            'message' => 'SMTP Configuration updated successfully!'
        ]);
    }

    /**
     * Test the SMTP configurations.
     */
    public function testSmtpSettings(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('maintenance', 'update')) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to update maintenance settings.'], 403);
        }

        $validated = $request->validate([
            'email'             => 'required|email',
            'mail_host'         => 'required|string',
            'mail_port'         => 'required|integer|min:1|max:65535',
            'mail_encryption'   => 'required|string|in:none,tls,ssl',
            'mail_username'     => 'nullable|string',
            'mail_password'     => 'nullable|string|max:1024',
            'mail_from_address' => 'required|email',
            'mail_from_name'    => 'required|string',
        ]);

        $recipient = $validated['email'];

        // Determine password to use
        $password = $validated['mail_password'];
        if ($password === null || $password === '') {
            $savedEncrypted = Setting::get('mail_password');
            if (!empty($savedEncrypted)) {
                try {
                    $password = decrypt($savedEncrypted);
                } catch (\Exception $e) {
                    return response()->json([
                        'message' => 'Failed to decrypt saved SMTP password for test.'
                    ], 400);
                }
            }
        }

        try {
            // Overwrite configuration with a temporary custom mailer
            config([
                'mail.mailers.custom_smtp' => [
                    'transport'  => 'smtp',
                    'host'       => $validated['mail_host'],
                    'port'       => $validated['mail_port'],
                    'encryption' => $validated['mail_encryption'] === 'none' ? null : $validated['mail_encryption'],
                    'username'   => $validated['mail_username'],
                    'password'   => $password,
                    'timeout'    => 10,
                ],
                'mail.from.address' => $validated['mail_from_address'],
                'mail.from.name'    => $validated['mail_from_name'],
            ]);

            Mail::mailer('custom_smtp')->raw(
                "Hello! This is a test email sent from the BIT DevOps ServerWatcher monitoring panel to verify your SMTP configuration. If you received this, your outgoing email configurations are fully functional!",
                function ($message) use ($recipient) {
                    $message->to($recipient)->subject('ServerWatcher SMTP Test Mail');
                }
            );

            return response()->json([
                'message' => 'Test email sent successfully! Please check ' . $recipient . '.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to send email: ' . $e->getMessage()
            ], 500);
        }
    }
}
