<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <title>{{ $title ?? config('app.name') }}</title>
    
    @if(isset($preheader) && $preheader)
    <div style="display: none; max-height: 0px; overflow: hidden;">
        {{ $preheader }}
    </div>
    <div style="display: none; max-height: 0px; overflow: hidden;">
        &nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;
    </div>
    @endif
    
    <style>
        html, body {
            margin: 0 auto !important;
            padding: 0 !important;
            height: 100% !important;
            width: 100% !important;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        * {
            -ms-text-size-adjust: 100%;
            -webkit-text-size-adjust: 100%;
        }
        
        table, td {
            mso-table-lspace: 0pt !important;
            mso-table-rspace: 0pt !important;
        }
        
        table {
            border-spacing: 0 !important;
            border-collapse: collapse !important;
            table-layout: fixed !important;
            margin: 0 auto !important;
        }
        
        a {
            text-decoration: none;
            color: #007bff;
        }
        
        a:hover {
            text-decoration: underline;
        }
        
        img {
            -ms-interpolation-mode: bicubic;
            max-width: 100%;
            height: auto;
        }
        
        .email-container {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .content {
            font-size: 16px;
            line-height: 1.6;
            color: #333333;
        }
        
        h1, h2, h3, h4, h5, h6 {
            margin: 0 0 20px 0;
            font-weight: 600;
            line-height: 1.3;
            color: #1a1a1a;
        }
        
        h1 { font-size: 28px; }
        h2 { font-size: 24px; }
        h3 { font-size: 20px; }
        
        p {
            margin: 0 0 16px 0;
        }
        
        .button {
            display: inline-block;
            padding: 14px 28px;
            background-color: #007bff;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 16px;
        }
        
        .button:hover {
            background-color: #0056b3;
            text-decoration: none;
        }
        
        @media only screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
                margin: auto !important;
            }
            
            .content {
                padding: 20px !important;
            }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f6f9fc;">
    <center style="width: 100%; background-color: #f6f9fc; padding: 40px 0;">
        <div class="email-container">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                <!-- Header -->
                <tr>
                    <td style="padding: 30px 40px; text-align: center; background-color: #ffffff; border-bottom: 1px solid #e9ecef;">
                        <h1 style="margin: 0; color: #1a1a1a; font-size: 24px;">{{ config('app.name') }}</h1>
                    </td>
                </tr>
                
                <!-- Content -->
                <tr>
                    <td class="content" style="padding: 40px;">
                        {!! $slot !!}
                    </td>
                </tr>
                
                <!-- Footer -->
                <tr>
                    <td style="padding: 30px 40px; background-color: #f8f9fa; text-align: center; border-top: 1px solid #e9ecef;">
                        <p style="margin: 0 0 8px 0; font-size: 14px; color: #6c757d;">
                            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                        </p>
                        <p style="margin: 0; font-size: 12px; color: #868e96;">
                            {{ config('app.url') }}
                        </p>
                    </td>
                </tr>
            </table>
        </div>
    </center>
</body>
</html>