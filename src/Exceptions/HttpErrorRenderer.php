<?php

namespace Maharlika\Exceptions;

use Maharlika\Contracts\Http\ResponseInterface;
use Maharlika\Http\Response;

class HttpErrorRenderer
{
    /**
     * Render an error page (404, 500, etc.)
     */
    public static function render(int $status, string $message = '', $uri = null, $details = null): ResponseInterface
    {
        $html = '';

        switch ($status) {
            case 404:
                $html = self::renderNotFoundPage($uri ?? '/', $message);
                break;
            case 403:
                $html = self::renderForbiddenPage($message);
                break;
            case 500:
                $html = self::renderServerErrorPage($message, $details);
                break;
            case 503:
                $html = self::renderServiceUnavailablePage($message, $details);
                break;
            default:
                $html = self::renderGenericErrorPage($status, $message, $details);
                break;
        }

        return new Response($html, $status);
    }

    /**
     * Render a security configuration error page.
     */
    public static function renderSecurityError(string $message, array $details = []): ResponseInterface
    {
        $html = self::renderSecurityConfigurationPage($message, $details);
        return new Response($html, 503);
    }

    /**
     * Render the 404 "Not Found" page
     */
    /**
 * Render the 404 "Not Found" page
 */
protected static function renderNotFoundPage(string $uri, string $message): string
{
    $uri = htmlspecialchars($uri, ENT_QUOTES, 'UTF-8');
    $message = $message ?: 'PAGE NOT FOUND'; // Fix: provide default message
    $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); // Also escape the message
    $message = strtoupper($message);
    $styles = self::getSharedStyles();

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 | Page Not Found</title>
    <style>{$styles}</style>
</head>
<body>
    <div class="container">
        <div class="error-line">
            <span class="code">404</span>
            <span class="separator">|</span>
            <span class="message">{$message}</span>
        </div>
    </div>
</body>
</html>
HTML;
}

    /**
     * Render a 403 Forbidden page
     */
    protected static function renderForbiddenPage(string $message): string
    {
        if (empty($message)) {
            $message = 'FORBIDDEN';
        }
        $message = strtoupper(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
        $styles = self::getSharedStyles();

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 | Forbidden</title>
    <style>{$styles}</style>
</head>
<body>
    <div class="container">
        <div class="error-line">
            <span class="code">403</span>
            <span class="separator">|</span>
            <span class="message">{$message}</span>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Render a 500 Internal Server Error page
     */
    protected static function renderServerErrorPage(string $message, $details = null): string
    {
        if (empty($message)) {
            $message = 'INTERNAL SERVER ERROR';
        }
        $message = strtoupper(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
        $detailsHtml = self::renderDetailsSection($details);
        $styles = self::getSharedStyles();

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 | Internal Server Error</title>
    <style>{$styles}</style>
</head>
<body>
    <div class="container">
        <div class="error-line">
            <span class="code">500</span>
            <span class="separator">|</span>
            <span class="message">{$message}</span>
        </div>
        {$detailsHtml}
    </div>
</body>
</html>
HTML;
    }

    /**
     * Render a security configuration error page
     */
    protected static function renderSecurityConfigurationPage(string $message, array $details = []): string
    {
        $message = strtoupper(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
        $env = env('APP_ENV');
        $isDevelopment = $env !== 'production';
        $pageTitle = $isDevelopment ? 'Configuration Error' : 'Server Response Error';
        $detailsHtml = '';
        if ($isDevelopment && !empty($details)) {
            $detailsHtml = '<div class="details">';
            
            if (isset($details['missing_middleware'])) {
                $middleware = htmlspecialchars($details['missing_middleware'], ENT_QUOTES, 'UTF-8');
                $detailsHtml .= "<div class=\"detail-item\"><strong>Middleware:</strong> {$middleware}</div>";
            }

            if (isset($details['acting_middleware'])) {
                $middleware = htmlspecialchars($details['acting_middleware'], ENT_QUOTES, 'UTF-8');
                $detailsHtml .= "<div class=\"detail-item\"><strong>Middleware:</strong> {$middleware}</div>";
            }

            if (isset($details['solution'])) {
                $solution = htmlspecialchars($details['solution'], ENT_QUOTES, 'UTF-8');
                $detailsHtml .= "<div class=\"detail-item\"><strong>Solution:</strong> {$solution}</div>";
            }

            if (isset($details['documentation'])) {
                $doc = htmlspecialchars($details['documentation'], ENT_QUOTES, 'UTF-8');
                $detailsHtml .= "<div class=\"detail-item\"><strong>Documentation:</strong> <a href=\"{$doc}\">{$doc}</a></div>";
            }

            if (isset($details['severity'])) {
                $severity = htmlspecialchars($details['severity'], ENT_QUOTES, 'UTF-8');
                $detailsHtml .= "<div class=\"detail-item\"><strong>Severity:</strong> {$severity}</div>";
            }

            if (isset($details['impact'])) {
                $impact = htmlspecialchars($details['impact'], ENT_QUOTES, 'UTF-8');
                $detailsHtml .= "<div class=\"detail-item\"><strong>Impact:</strong> {$impact}</div>";
            }

            $detailsHtml .= '</div>';
        }

        $styles = self::getSharedStyles();

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>503 | {$pageTitle}</title>
    <style>{$styles}</style>
</head>
<body>
    <div class="container">
        <div class="error-line">
            <span class="code">503</span>
            <span class="separator">|</span>
            <span class="message">{$message}</span>
        </div>
        {$detailsHtml}
    </div>
</body>
</html>
HTML;
    }

    /**
     * Render a 503 Service Unavailable page
     */
    protected static function renderServiceUnavailablePage(string $message, $details = null): string
    {
        if (empty($message)) {
            $message = 'SERVICE UNAVAILABLE';
        }
        $message = strtoupper(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
        $detailsHtml = self::renderDetailsSection($details);
        $styles = self::getSharedStyles();

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>503 | Service Unavailable</title>
    <style>{$styles}</style>
</head>
<body>
    <div class="container">
        <div class="error-line">
            <span class="code">503</span>
            <span class="separator">|</span>
            <span class="message">{$message}</span>
        </div>
        {$detailsHtml}
    </div>
</body>
</html>
HTML;
    }

    /**
     * Fallback for generic error pages
     */
    protected static function renderGenericErrorPage(int $status, string $message, $details = null): string
    {
        $title = self::getStatusTitle($status);
        // if (empty($message)) {
        //     $message = strtoupper($title);
        // } else {
        //     $message = strtoupper($message);
        // }
        $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $detailsHtml = self::renderDetailsSection($details);
        $styles = self::getSharedStyles();

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$status} | {$title}</title>
    <style>{$styles}</style>
</head>
<body>
    <div class="container">
        <div class="error-line">
            <span class="code">{$status}</span>
            <span class="separator">|</span>
            <span class="message">{$message}</span>
        </div>
        {$detailsHtml}
    </div>
</body>
</html>
HTML;
    }

    /**
     * Render details section if provided
     */
    protected static function renderDetailsSection($details): string
    {
        if (empty($details) || !is_array($details)) {
            return '';
        }

        $env = getenv('APP_ENV');
        if ($env === false) {
            $env = $_ENV['APP_ENV'] ?? 'production';
        }

        if ($env === 'production') {
            return '';
        }

        $detailsList = '';
        foreach ($details as $key => $value) {
            $key = htmlspecialchars(ucfirst(str_replace('_', ' ', $key)), ENT_QUOTES, 'UTF-8');
            if (is_array($value)) {
                $value = json_encode($value);
            } else {
                $value = (string)$value;
            }
            $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            $detailsList .= "<div class=\"detail-item\"><strong>{$key}:</strong> {$value}</div>";
        }

        return <<<HTML
<div class="details">
    {$detailsList}
</div>
HTML;
    }

    /**
     * Get status title from status code
     */
    protected static function getStatusTitle(int $status): string
    {
        $titles = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            408 => 'Request Timeout',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            419 => 'Page Expired'
        ];

        return $titles[$status] ?? 'Error';
    }

    /**
     * Get shared CSS styles for all error pages
     */
    protected static function getSharedStyles(): string
    {
        return <<<CSS
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html, body {
    height: 100vh;
    font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background:#11111a;
    color: #a0aec0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.container {
    text-align: center;
    padding: 2rem;
    max-width: 600px;
}

.error-line {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 2rem;
}

.code {
    font-size: 3rem;
    font-weight: 400;
    color: #718096;
}

.separator {
    font-size: 4rem;
    color: #4a5568;
}

.message {
    font-size: 1rem;
    color: #8f969f;
    font-weight: 400;
    letter-spacing: 0.05em;
}

.uri {
    font-size: 0.875rem;
    color: #718096;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    margin-top: 1rem;
    display: block;
}

.details {
    margin-top: 2rem;
    padding: 1.5rem;
    background: #1a202c;
    border-radius: 4px;
    text-align: left;
}

.detail-item {
    padding: 0.5rem 0;
    font-size: 0.875rem;
    color: #a0aec0;
    border-bottom: 1px solid #4a5568;
}

.detail-item:last-child {
    border-bottom: none;
}

.detail-item strong {
    color: #cbd5e0;
    font-weight: 600;
}

.detail-item a {
    color: #63b3ed;
    text-decoration: none;
}

.detail-item a:hover {
    text-decoration: underline;
}

/* --- MOBILE LAYOUT --- */
@media (max-width: 768px) {
    .error-line {
        flex-direction: column;        /* stack */
        gap: 0.75rem;
    }

    .separator {
        width: 100%;                  /* full-width line */
        height: 1px;
        background-color: #4a5568;    /* same separator color */
        font-size: 0;                 /* hide the "|" character */
    }

    .code {
        order: 1;                     /* show first */
    }

    .separator {
        order: 2;                     /* horizontal line */
    }

    .message {
        order: 3;                     /* below */
    }
}

CSS;
    }
}