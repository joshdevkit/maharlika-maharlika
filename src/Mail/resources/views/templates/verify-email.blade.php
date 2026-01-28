<x-mail.html.layout :title="'Verify Your Email Address'">
    <h2 style="margin-top: 0;">Hello {{ $userName ?? 'there' }}!</h2>
    
    <p>Thank you for creating an account with <strong>{{ $app_name }}</strong>. To get started, please verify your email address by clicking the button below:</p>
    
    <x-mail.html.button :url="$verificationUrl" color="primary">
        Verify Email Address
    </x-mail.html.button>
    
    <x-mail.html.panel background="#fff3cd" style="border-color: #ffc107;">
        <p style="margin: 0; color: #856404;">
            <strong>Important:</strong> This verification link will expire in <strong>{{ $expirationMinutes }} minutes</strong>.
        </p>
    </x-mail.html.panel>
    
    <p>If you did not create an account with {{ $app_name }}, no further action is required and you can safely ignore this email.</p>
    
    <hr style="border: none; border-top: 1px solid #e9ecef; margin: 30px 0;">
    
    <p style="font-size: 13px; color: #6c757d; line-height: 1.5;">
        <strong>Having trouble?</strong> If you're unable to click the button above, copy and paste the URL below into your web browser:
    </p>
    
    <p style="font-size: 12px; color: #868e96; word-break: break-all;">
        <a href="{{ $verificationUrl }}" style="color: #007bff;">{{ $verificationUrl }}</a>
    </p>
</x-mail.html.layout>