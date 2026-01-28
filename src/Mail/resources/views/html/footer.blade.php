<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f8f9fa;">
    <tr>
        <td style="padding: 30px 40px; text-align: center;">
            @if(!empty($slot))
                <div style="margin-bottom: 16px; font-size: 14px; color: #6c757d;">
                    {!! $slot !!}
                </div>
            @endif
            
            <p style="margin: 0 0 8px 0; font-size: 14px; color: #6c757d;">
                &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
            </p>
            
            <p style="margin: 0; font-size: 12px; color: #868e96;">
                <a href="{{ config('app.url') }}" style="color: #868e96;">{{ config('app.url') }}</a>
            </p>
        </td>
    </tr>
</table>