<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 20px 0;">
    <tr>
        <td style="padding: 24px; background-color: {{ $background }}; border-radius: 6px; border: 1px solid #dee2e6;">
            @if(isset($title) && $title)
                <h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 600; color: #1a1a1a;">
                    {{ $title }}
                </h3>
            @endif
            
            <div style="font-size: 14px; line-height: 1.6; color: #495057;">
                {!! $slot !!}
            </div>
        </td>
    </tr>
</table>