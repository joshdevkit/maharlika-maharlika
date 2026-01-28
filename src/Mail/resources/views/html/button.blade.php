@php
$colors = [
    'primary' => '#007bff',
    'secondary' => '#6c757d',
    'success' => '#28a745',
    'danger' => '#dc3545',
    'warning' => '#ffc107',
    'info' => '#17a2b8',
    'dark' => '#343a40',
];

$backgroundColor = $colors[$color] ?? $colors['primary'];
@endphp

<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 20px 0;">
    <tr>
        <td style="border-radius: 6px; background-color: {{ $backgroundColor }};">
            <a href="{{ $url }}" 
               target="_blank" 
               style="display: inline-block; 
                      padding: 14px 28px; 
                      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
                      font-size: 16px; 
                      font-weight: 600;
                      color: #ffffff; 
                      text-decoration: none; 
                      border-radius: 6px;">
                {!! $slot !!}
            </a>
        </td>
    </tr>
</table>