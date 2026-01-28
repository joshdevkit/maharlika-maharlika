<?php

namespace Maharlika\Http;

use Maharlika\Contracts\Http\ResponseInterface;
use Maharlika\Contracts\View\ViewInterface;
use Maharlika\Contracts\Session\SessionInterface;
use Maharlika\Contracts\View\ViewFactoryInterface;
use Maharlika\Exceptions\HttpErrorRenderer;
use Maharlika\Support\ErrorBag;

class Response implements ResponseInterface
{
    protected int $statusCode = 200;
    protected array $headers = [];
    protected string $content = '';
    protected ?SessionInterface $session = null;
    protected bool $isFile = false;
    protected ?string $filePath = null;

    /** @var array<string, string> */
    protected static array $statusTexts = [
        // 2xx Success
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        204 => 'No Content',

        // 3xx Redirection
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',

        // 4xx Client Errors
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        413 => 'Payload Too Large',
        415 => 'Unsupported Media Type',
        422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',

        // 5xx Server Errors
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
    ];

    public function __construct(mixed $content = '', int $statusCode = 200, array $headers = [])
    {
        $this->setContent($content)
            ->setStatusCode($statusCode);

        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }
    }

    /**
     * Set the session instance for flash data
     */
    public function setSession(SessionInterface $session)
    {
        $this->session = $session;
        return $this;
    }

    // ───────────────────────────────────────────────
    // Maharlika setup
    // ───────────────────────────────────────────────

    public function setStatusCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function header(string $name, string $value): self
    {
        return $this->setHeader($name, $value);
    }

    /**
     * Set a single header (fluent alias)
     */
    public function withHeader(string $name, string $value): self
    {
        return $this->setHeader($name, $value);
    }

    /**
     * Set multiple headers at once
     */
    public function withHeaders(array $headers): self
    {
        foreach ($headers as $name => $value) {
            $this->setHeader($name, (string) $value);
        }
        return $this;
    }

    /**
     * Remove a header
     */
    public function withoutHeader(string $name): self
    {
        unset($this->headers[$name]);
        return $this;
    }

    /**
     * Check if header exists
     */
    public function hasHeader(string $name): bool
    {
        return isset($this->headers[$name]);
    }

    /**
     * Get a specific header value
     */
    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    /**
     * Get all headers
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setContent(mixed $content): self
    {
        if ($content instanceof ViewInterface) {
            $this->content = $content->render();
            $this->setHeader('Content-Type', 'text/html; charset=UTF-8');
        } elseif (is_array($content) || is_object($content)) {
            $this->content = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $this->setHeader('Content-Type', 'application/json');
        } else {
            $this->content = (string) $content;
        }

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    // ───────────────────────────────────────────────
    // Output
    // ───────────────────────────────────────────────

    public function send(): void
    {
        $this->sendHeaders();
        $this->sendContent();

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }

    protected function sendHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        $statusText = static::$statusTexts[$this->statusCode] ?? 'Unknown';
        header(sprintf('HTTP/1.1 %d %s', $this->statusCode, $statusText));

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}", false);
        }
    }

    protected function sendContent(): void
    {
        if ($this->isFile && $this->filePath) {
            $this->sendFile();
        } else {
            echo $this->content;
        }
    }

    /**
     * Send file content.
     */
    protected function sendFile(): void
    {
        if (!file_exists($this->filePath)) {
            throw new \RuntimeException("File not found: {$this->filePath}");
        }

        // Clear output buffer to prevent corruption
        if (ob_get_level()) {
            ob_end_clean();
        }

        $fileSize = filesize($this->filePath);
        $chunkSize = 8192;

        // Open file for reading
        $handle = fopen($this->filePath, 'rb');

        if ($handle === false) {
            throw new \RuntimeException("Cannot open file: {$this->filePath}");
        }

        // Send file in chunks
        while (!feof($handle)) {
            $buffer = fread($handle, $chunkSize);
            if ($buffer === false) {
                break;
            }
            echo $buffer;
            flush(); // Flush output buffer
        }

        fclose($handle);
    }

    /**
     * Redirect to the intended URL or fallback
     * 
     * This is useful after authentication - redirects to the page the user
     * was trying to access before being redirected to login
     * 
     * @param string $default Default URL if no intended URL exists
     * @param int $status HTTP status code
     * @return self
     */
    public static function intended(string $default = '/', int $status = 302): self
    {
        $session = app('session');

        // Get the intended URL from session
        $intended = $session->get('url.intended', $default);

        // Clear the intended URL from session
        $session->forget('url.intended');

        return static::redirect($intended, $status);
    }

    /**
     * Set the intended URL in session (called by auth middleware)
     * 
     * @param string $url The URL to redirect to after authentication
     * @return void
     */
    public static function setIntendedUrl(string $url): void
    {
        $session = app('session');

        // Only store if it's not a login/register/logout URL
        $excludedPaths = ['/login', '/register', '/logout', '/password'];

        foreach ($excludedPaths as $path) {
            if (str_starts_with($url, $path)) {
                return;
            }
        }

        $session->put('url.intended', $url);
    }

    /**
     * Get the intended URL without clearing it
     * 
     * @param string $default Default URL if no intended URL exists
     * @return string
     */
    public static function getIntendedUrl(string $default = '/'): string
    {
        $session = app('session');
        return $session->get('url.intended', $default);
    }

    // ───────────────────────────────────────────────
    // Factory helpers
    // ───────────────────────────────────────────────

    /**
     * Flash input data to session
     */
    public function withInput(array $input = []): self
    {
        $session = app('session');

        // If no input provided, use $_POST
        $input = $input ?: $_POST;

        // Remove sensitive fields
        $filtered = array_diff_key($input, array_flip([
            'password',
            'password_confirmation',
            'token',
            '_token',
            '_csrf_token',
        ]));

        $session->flash('old_input', $filtered);

        return $this;
    }

    public static function view(string $view, array $data = [], int $status = 200, array $headers = []): self
    {
        if (function_exists('view')) {
            $content = view($view, $data);
        } else {
            // Fallback if view() helper not defined
            $viewFactory = app(ViewFactoryInterface::class);
            $content = $viewFactory->make($view, $data);
        }

        return new static($content, $status, $headers);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new static($data, $status, ['Content-Type' => 'application/json']);
    }

    public static function redirect(?string $url = null, int $status = 302): self
    {
        if (empty($url)) {
            $url = $_SERVER['HTTP_REFERER'] ?? '/';
        }

        return new static('', $status, ['Location' => $url]);
    }

    /**
     * FIXED: Redirect back to previous page
     * 
     * @param int $status HTTP status code (default 302)
     * @param string $fallback Fallback URL if no referer (default '/')
     * @return self
     */
    public static function back(int $status = 302, string $fallback = '/'): self
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? $fallback;
        return static::redirect($referer, $status);
    }

    /**
     * Fluent redirect helper for chaining.
     *
     */
    public static function to(?string $url = null, mixed $paramsOrStatus = 302): self
    {
        // If second argument is an array, turn it into /value segments
        if (is_array($paramsOrStatus)) {
            foreach ($paramsOrStatus as $value) {
                $url = rtrim($url, '/') . '/' . $value;
            }

            $status = 302; // default status
        } else {
            // It's an integer status code
            $status = $paramsOrStatus;
        }

        return static::redirect($url, $status);
    }

    /**
     * Redirect to a named route/action with optional parameters
     * 
     */
    public static function toAction(string $action, mixed $params = null, int $status = 302): self
    {
        // Get the router to resolve the route
        $router = app('router');

        // Convert single param to array with proper key
        if ($params !== null && !is_array($params)) {
            // Get the route to find parameter names
            $route = $router->getByName($action);

            if ($route) {
                // Extract parameter names from URI
                preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $route['uri'], $matches);
                $paramNames = $matches[1] ?? [];

                // If there's one parameter, use its name
                if (!empty($paramNames)) {
                    $params = [$paramNames[0] => $params];
                }
            } else {
                // Fallback to generic array
                $params = [$params];
            }
        }

        // Generate URL from route name
        $url = $router->route($action, $params ?? []);

        return static::redirect($url, $status);
    }

    /**
     * Generate URL from route name
     * 
     * @param string $name Route name
     * @param array $params Route parameters
     * @return string Generated URL
     */
    public function url(string $name, array $params = []): string
    {
        // Find the route by name
        $route = $this->getRouteByName($name);

        if (!$route) {
            throw new \RuntimeException("Route '{$name}' not found");
        }

        $pattern = $route['pattern'];

        // Replace parameters in pattern
        if (!empty($params)) {
            // If params is indexed array, match by order
            if (array_keys($params) === range(0, count($params) - 1)) {
                // Extract parameter names from pattern
                preg_match_all('/\{(\w+)\}/', $pattern, $matches);
                $paramNames = $matches[1];

                // Map values to names
                $namedParams = [];
                foreach ($paramNames as $index => $paramName) {
                    if (isset($params[$index])) {
                        $namedParams[$paramName] = $params[$index];
                    }
                }
                $params = $namedParams;
            }

            // Replace {param} with values
            foreach ($params as $key => $value) {
                $pattern = str_replace('{' . $key . '}', $value, $pattern);
            }
        }

        // Remove any remaining unreplaced parameters
        $pattern = preg_replace('/\{\w+\}/', '', $pattern);

        return $pattern;
    }

    /**
     * Get route by name
     */
    protected function getRouteByName(string $name): ?array
    {
        // Check common property names
        $routesProperty = null;

        if (property_exists($this, 'routes')) {
            $routesProperty = 'routes';
        } elseif (property_exists($this, 'namedRoutes')) {
            $routesProperty = 'namedRoutes';
        } elseif (property_exists($this, 'routeCollection')) {
            $routesProperty = 'routeCollection';
        }

        if ($routesProperty === null) {
            throw new \RuntimeException('Cannot find routes property in Router');
        }

        $routes = $this->$routesProperty;

        // If it's a direct name => route mapping
        if (isset($routes[$name])) {
            return is_array($routes[$name]) ? $routes[$name] : ['pattern' => $routes[$name]];
        }

        // If it's an array of routes, search through them
        foreach ($routes as $route) {
            if (is_array($route) && isset($route['name']) && $route['name'] === $name) {
                return $route;
            }
        }

        return null;
    }

    /**
     * Render an error response using HttpErrorRenderer
     * 
     * @param int $status HTTP status code
     * @param string $message Error message
     * @param mixed $uri Request URI (optional)
     * @param mixed $details Additional details (optional)
     * @return ResponseInterface
     */
    public static function abort(int $status, string $message = '', $uri = null, $details = null): never
    {
        $response = HttpErrorRenderer::render($status, $message, $uri, $details);
        $response->send();
        exit;
    }

    /**
     * Create a download response for a file.
     *
     * @param string $filePath Path to the file
     * @param string|null $filename Custom filename for download (optional)
     * @param array $headers Additional headers
     * @return static
     */
    public static function download(string $filePath, ?string $filename = null, array $headers = []): self
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        if (!is_readable($filePath)) {
            throw new \RuntimeException("File is not readable: {$filePath}");
        }

        $filename = $filename ?? basename($filePath);

        $response = new static('', 200, $headers);
        $response->isFile = true;
        $response->filePath = $filePath;

        // Set download headers
        $response->withHeaders([
            'Content-Type' => static::getMimeType($filePath),
            'Content-Length' => (string) filesize($filePath),
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Transfer-Encoding' => 'binary',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Pragma' => 'public',
            'Expires' => '0',
        ]);

        return $response;
    }

    /**
     * Create a response to display file inline (in browser).
     *
     * @param string $filePath Path to the file
     * @param string|null $filename Custom filename (optional)
     * @param array $headers Additional headers
     * @return static
     */
    public static function file(string $filePath, ?string $filename = null, array $headers = []): self
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        if (!is_readable($filePath)) {
            throw new \RuntimeException("File is not readable: {$filePath}");
        }

        $filename = $filename ?? basename($filePath);

        $response = new static('', 200, $headers);
        $response->isFile = true;
        $response->filePath = $filePath;

        // Set inline display headers
        $response->withHeaders([
            'Content-Type' => static::getMimeType($filePath),
            'Content-Length' => (string) filesize($filePath),
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);

        return $response;
    }

    /**
     * Stream a file download (for large files).
     *
     * @param string $filePath Path to the file
     * @param string|null $filename Custom filename for download (optional)
     * @return static
     */
    public static function streamDownload(string $filePath, ?string $filename = null): self
    {
        $response = static::download($filePath, $filename);
        $response->withHeader('X-Accel-Buffering', 'no'); // Disable buffering for nginx
        return $response;
    }

    /**
     * Get MIME type of a file.
     *
     * @param string $filePath
     * @return string
     */
    protected static function getMimeType(string $filePath): string
    {
        $mimeType = mime_content_type($filePath);

        if ($mimeType === false) {
            // Fallback to extension-based detection
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

            $mimeTypes = [
                'txt' => 'text/plain',
                'htm' => 'text/html',
                'html' => 'text/html',
                'php' => 'text/html',
                'css' => 'text/css',
                'js' => 'application/javascript',
                'json' => 'application/json',
                'xml' => 'application/xml',
                'swf' => 'application/x-shockwave-flash',
                'flv' => 'video/x-flv',

                // images
                'png' => 'image/png',
                'jpe' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'jpg' => 'image/jpeg',
                'gif' => 'image/gif',
                'bmp' => 'image/bmp',
                'ico' => 'image/vnd.microsoft.icon',
                'tiff' => 'image/tiff',
                'tif' => 'image/tiff',
                'svg' => 'image/svg+xml',
                'svgz' => 'image/svg+xml',
                'webp' => 'image/webp',

                // archives
                'zip' => 'application/zip',
                'rar' => 'application/x-rar-compressed',
                'exe' => 'application/x-msdownload',
                'msi' => 'application/x-msdownload',
                'cab' => 'application/vnd.ms-cab-compressed',
                '7z' => 'application/x-7z-compressed',

                // audio/video
                'mp3' => 'audio/mpeg',
                'wav' => 'audio/wav',
                'qt' => 'video/quicktime',
                'mov' => 'video/quicktime',
                'mp4' => 'video/mp4',
                'avi' => 'video/x-msvideo',
                'wmv' => 'video/x-ms-wmv',
                'webm' => 'video/webm',

                // documents
                'pdf' => 'application/pdf',
                'psd' => 'image/vnd.adobe.photoshop',
                'ai' => 'application/postscript',
                'eps' => 'application/postscript',
                'ps' => 'application/postscript',

                // ms office
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'rtf' => 'application/rtf',
                'xls' => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'ppt' => 'application/vnd.ms-powerpoint',
                'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',

                // open office
                'odt' => 'application/vnd.oasis.opendocument.text',
                'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
            ];

            return $mimeTypes[$extension] ?? 'application/octet-stream';
        }

        return $mimeType;
    }

    // ───────────────────────────────────────────────
    // Flash messaging
    // ───────────────────────────────────────────────

    /**
     * Attach a success flash message.
     */
    public function withSuccess(string $keyOrMessage, ?string $message = null): self
    {
        $session = app('session');

        // If only one argument, use it as message with default 'success' key
        if ($message === null) {
            $session->flash('success', $keyOrMessage);
        } else {
            // Two arguments: first is key, second is message
            $session->flash($keyOrMessage, $message);
        }

        return $this;
    }

    /**
     * Attach an info flash message.
     * 
     * Usage:
     *   ->withInfo('Please verify your email')
     *   ->withInfo('notification', 'Please verify your email')
     */
    public function withInfo(string $keyOrMessage, ?string $message = null): self
    {
        $session = app('session');

        if ($message === null) {
            $session->flash('info', $keyOrMessage);
        } else {
            $session->flash($keyOrMessage, $message);
        }

        return $this;
    }

    /**
     * Attach a warning flash message.
     * 
     * Usage:
     *   ->withWarning('Your session will expire soon')
     *   ->withWarning('alert', 'Your session will expire soon')
     */
    public function withWarning(string $keyOrMessage, ?string $message = null): self
    {
        $session = app('session');

        if ($message === null) {
            $session->flash('warning', $keyOrMessage);
        } else {
            $session->flash($keyOrMessage, $message);
        }

        return $this;
    }

    /**
     * Attach an error flash message (different from validation errors).
     * 
     * Usage:
     *   ->withError('Something went wrong')
     *   ->withError('system_error', 'Something went wrong')
     */
    public function withError(string $keyOrMessage, ?string $message = null): self
    {
        $session = app('session');

        if ($message === null) {
            $session->flash('error', $keyOrMessage);
        } else {
            $session->flash($keyOrMessage, $message);
        }

        return $this;
    }

    /**
     * Attach arbitrary flash data
     */
    public function with(string $key, mixed $value): self
    {
        $session = app('session');
        $session->flash($key, $value);
        return $this;
    }

    /**
     * Attach validation errors to the response.
     */
    public function withErrors(ErrorBag|array|string $errors): self
    {
        $session = app('session');

        $bag = $errors instanceof ErrorBag ? $errors : new ErrorBag();

        if (is_string($errors)) {
            $bag->add('general', $errors);
        } elseif (is_array($errors)) {
            foreach ($errors as $field => $messages) {
                if (is_array($messages)) {
                    foreach ($messages as $message) {
                        $bag->add($field, $message);
                    }
                } else {
                    $bag->add(is_string($field) ? $field : 'general', (string) $messages);
                }
            }
        }

        $session->flash('errors', $bag);
        return $this;
    }

    // ───────────────────────────────────────────────
    // Common header helpers
    // ───────────────────────────────────────────────

    /**
     * Set cache control headers
     */
    public function withCache(int $seconds): self
    {
        return $this->withHeaders([
            'Cache-Control' => "public, max-age={$seconds}",
            'Expires' => gmdate('D, d M Y H:i:s', time() + $seconds) . ' GMT',
        ]);
    }

    /**
     * Disable caching
     */
    public function withoutCache(): self
    {
        return $this->withHeaders([
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Set CORS headers
     */
    public function withCors(
        string|array $origins = '*',
        array $methods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        array $headers = ['Content-Type', 'Authorization']
    ): self {
        $origin = is_array($origins) ? implode(', ', $origins) : $origins;

        return $this->withHeaders([
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Allow-Methods' => implode(', ', $methods),
            'Access-Control-Allow-Headers' => implode(', ', $headers),
        ]);
    }

    /**
     * Set content disposition for downloads
     */
    public function withDownload(string $filename): self
    {
        return $this->withHeader(
            'Content-Disposition',
            'attachment; filename="' . $filename . '"'
        );
    }

    /**
     * Set content type
     */
    public function withContentType(string $type, string $charset = 'UTF-8'): self
    {
        return $this->withHeader('Content-Type', "{$type}; charset={$charset}");
    }

    /**
     * Add security headers
     */
    public function withSecurityHeaders(): self
    {
        return $this->withHeaders([
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ]);
    }

    /**
     * Set HTTP Basic Auth challenge
     */
    public function withAuthChallenge(string $realm = 'Restricted Area'): self
    {
        return $this->withHeader('WWW-Authenticate', "Basic realm=\"{$realm}\"")
            ->setStatusCode(401);
    }

    /**
     * Add ETag header
     */
    public function withETag(string $etag): self
    {
        return $this->withHeader('ETag', "\"{$etag}\"");
    }

    /**
     * Set refresh header (meta refresh)
     */
    public function withRefresh(int $seconds, ?string $url = null): self
    {
        $value = (string) $seconds;
        if ($url) {
            $value .= "; url={$url}";
        }
        return $this->withHeader('Refresh', $value);
    }

    // ───────────────────────────────────────────────
    // Transform helpers
    // ───────────────────────────────────────────────

    public function toJson(): self
    {
        $this->content = json_encode(
            ['data' => $this->content, 'status' => $this->statusCode],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        $this->setHeader('Content-Type', 'application/json');
        return $this;
    }

    public function toHtml(): self
    {
        $this->setHeader('Content-Type', 'text/html; charset=UTF-8');
        return $this;
    }

    /**
     * Convert to XML response
     */
    public function toXml(): self
    {
        $this->setHeader('Content-Type', 'application/xml; charset=UTF-8');
        return $this;
    }

    /**
     * Convert to plain text
     */
    public function toText(): self
    {
        $this->setHeader('Content-Type', 'text/plain; charset=UTF-8');
        return $this;
    }

    // ───────────────────────────────────────────────
    // Status code helpers
    // ───────────────────────────────────────────────

    public function ok(): self
    {
        return $this->setStatusCode(200);
    }

    public function created(): self
    {
        return $this->setStatusCode(201);
    }

    public function accepted(): self
    {
        return $this->setStatusCode(202);
    }

    public function noContent(): self
    {
        return $this->setStatusCode(204);
    }

    public function movedPermanently(): self
    {
        return $this->setStatusCode(301);
    }

    public function found(): self
    {
        return $this->setStatusCode(302);
    }

    public function notModified(): self
    {
        return $this->setStatusCode(304);
    }

    public function badRequest(): self
    {
        return $this->setStatusCode(400);
    }

    public function unauthorized(): self
    {
        return $this->setStatusCode(401);
    }

    public function forbidden(): self
    {
        return $this->setStatusCode(403);
    }

    public function notFound(): self
    {
        return $this->setStatusCode(404);
    }

    public function methodNotAllowed(): self
    {
        return $this->setStatusCode(405);
    }

    public function conflict(): self
    {
        return $this->setStatusCode(409);
    }

    public function unprocessableEntity(): self
    {
        return $this->setStatusCode(422);
    }

    public function tooManyRequests(): self
    {
        return $this->setStatusCode(429);
    }

    public function serverError(): self
    {
        return $this->setStatusCode(500);
    }

    public function serviceUnavailable(): self
    {
        return $this->setStatusCode(503);
    }
}
