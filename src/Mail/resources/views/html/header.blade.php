<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #ffffff;">
    <tr>
        <td style="padding: 30px 40px; text-align: center;">
            @if(isset($logo) && $logo)
                <img src="{{ $logo }}" alt="{{ config('app.name') }}" style="max-width: 200px; height: auto;">
            @else
                <h1 style="margin: 0; color: #1a1a1a; font-size: 24px;">{{ config('app.name') }}</h1>
            @endif
            
            @if(!empty($slot))
                <div style="margin-top: 16px; font-size: 14px; color: #6c757d;">
                    {!! $slot !!}
                </div>
            @endif
        </td>
    </tr>
</table>