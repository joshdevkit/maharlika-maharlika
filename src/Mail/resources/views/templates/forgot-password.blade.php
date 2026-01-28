<x-mail.html.layout :title="'Reset Your Password'">
    <h2 style="margin-top: 0;">Hello {{ $user->name ?? 'there' }}!</h2>

    <p>You are receiving this email because we received a password reset request for your account.</p>

    <x-mail.html.button :url="$resetUrl" color="primary">
        Reset Password
    </x-mail.html.button>

    <x-mail.html.panel background="#fff3cd" style="border-color: #ffc107;">
        <p style="margin: 0; color: #856404;">
            <strong>Important:</strong> This password reset link will expire in <strong>60 minutes</strong>.
        </p>
    </x-mail.html.panel>

    <p>If you did not request a password reset, no further action is required. Your password will remain unchanged.</p>

    <hr style="border: none; border-top: 1px solid #e9ecef; margin: 30px 0;">

    <p style="font-size: 13px; color: #6c757d; line-height: 1.5;">
        <strong>Having trouble?</strong> If you're unable to click the button above, copy and paste the URL below into
        your web browser:
    </p>

    <p style="font-size: 12px; color: #868e96; word-break: break-all;">
        <a href="{{ $resetUrl }}" style="color: #007bff;">{{ $resetUrl }}</a>
    </p>

    <hr style="border: none; border-top: 1px solid #e9ecef; margin: 30px 0;">

    <p style="font-size: 12px; color: #868e96;">
        If you're having issues with your account or didn't request this reset, please contact our support team at
        <a href="mailto:{{ config('mail.support_email', 'support@example.com') }}" style="color: #007bff;">
            {{ config('mail.support_email', 'support@example.com') }}
        </a>
    </p>
</x-mail.html.layout>