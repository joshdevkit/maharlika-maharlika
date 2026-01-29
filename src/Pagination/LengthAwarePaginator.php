<?php

namespace Maharlika\Pagination;

use Maharlika\Contracts\View\ViewFactoryInterface;
use Maharlika\Database\Collection;
use Maharlika\Support\HtmlString;

/**
 * Length Aware Paginator
 * 
 * Used for manually paginating collections or arrays where you know the total count.
 * Unlike the Paginator class which is returned by query builder's paginate() method,
 * this can be used independently for custom pagination scenarios.
 * 
 */
class LengthAwarePaginator implements \JsonSerializable, \IteratorAggregate, \Countable, \ArrayAccess
{
    protected Collection $items;
    protected int $total;
    protected int $perPage;
    protected int $currentPage;
    protected int $lastPage;
    protected ?int $from;
    protected ?int $to;
    protected string $path;
    protected array $query = [];
    protected ?string $fragment = null;
    protected int $onEachSide = 3;
    protected string $pageName = 'page';

    /**
     * Create a new length aware paginator instance.
     *
     * @param mixed $items The items for the current page
     * @param int $total Total number of items
     * @param int $perPage Number of items per page
     * @param int|null $currentPage Current page number
     * @param array $options Additional options (path, query, fragment, pageName)
     */
    public function __construct(
        mixed $items,
        int $total,
        int $perPage = 15,
        ?int $currentPage = null,
        array $options = []
    ) {
        $this->items = $items instanceof Collection ? $items : new Collection($items);
        $this->total = $total;
        $this->perPage = $perPage;
        $this->currentPage = $currentPage ?? static::resolveCurrentPage($options['pageName'] ?? 'page');
        $this->lastPage = max((int) ceil($total / $perPage), 1);
        $this->path = $options['path'] ?? static::resolveCurrentPath();
        $this->query = $options['query'] ?? [];
        $this->fragment = $options['fragment'] ?? null;
        $this->pageName = $options['pageName'] ?? 'page';

        if (isset($options['onEachSide'])) {
            $this->onEachSide = $options['onEachSide'];
        }

        $this->setFromTo();
    }

    /**
     * Set the from and to values.
     */
    protected function setFromTo(): void
    {
        if ($this->total === 0) {
            $this->from = null;
            $this->to = null;
        } else {
            $this->from = (($this->currentPage - 1) * $this->perPage) + 1;
            $this->to = min($this->from + $this->items->count() - 1, $this->total);
        }
    }

    /**
     * Resolve the current page.
     */
    public static function resolveCurrentPage(string $pageName = 'page'): int
    {
        $page = $_GET[$pageName] ?? $_POST[$pageName] ?? 1;

        if (filter_var($page, FILTER_VALIDATE_INT) !== false && (int) $page >= 1) {
            return (int) $page;
        }

        return 1;
    }

    /**
     * Resolve the current path.
     */
    public static function resolveCurrentPath(): string
    {
        $url = $_SERVER['REQUEST_URI'] ?? '/';
        return strtok($url, '?');
    }

    /**
     * Set the base path for URLs.
     */
    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    /**
     * Get the base path for URLs.
     */
    public function path(): string
    {
        return $this->path;
    }

    // ============================================
    // GETTERS
    // ============================================

    public function items(): Collection
    {
        return $this->items;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function currentPage(): int
    {
        return $this->currentPage;
    }

    public function lastPage(): int
    {
        return $this->lastPage;
    }

    public function from(): ?int
    {
        return $this->from;
    }

    public function to(): ?int
    {
        return $this->to;
    }

    public function firstItem(): ?int
    {
        return $this->from;
    }

    public function lastItem(): ?int
    {
        return $this->to;
    }

    public function count(): int
    {
        return $this->items->count();
    }

    // ============================================
    // STATE CHECKING METHODS
    // ============================================

    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }

    public function isNotEmpty(): bool
    {
        return $this->items->isNotEmpty();
    }

    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    public function hasPages(): bool
    {
        return $this->lastPage > 1;
    }

    public function onFirstPage(): bool
    {
        return $this->currentPage <= 1;
    }

    public function onLastPage(): bool
    {
        return $this->currentPage >= $this->lastPage;
    }

    // ============================================
    // QUERY STRING & URL MANIPULATION
    // ============================================

    /**
     * Add query string values to the paginator.
     */
    public function appends(array|string $key, mixed $value = null): self
    {
        if (is_array($key)) {
            return $this->appendArray($key);
        }

        return $this->addQuery($key, $value);
    }

    /**
     * Add an array of query string values.
     */
    protected function appendArray(array $keys): self
    {
        foreach ($keys as $key => $value) {
            $this->addQuery($key, $value);
        }

        return $this;
    }

    /**
     * Add all current query string values to the paginator.
     */
    public function withQueryString(): self
    {
        if (isset($_GET)) {
            $query = $_GET;
            unset($query[$this->pageName]);
            return $this->appends($query);
        }

        return $this;
    }

    /**
     * Add a query string value.
     */
    protected function addQuery(string $key, mixed $value): self
    {
        if ($key !== $this->pageName) {
            $this->query[$key] = $value;
        }

        return $this;
    }

    /**
     * Get / set the URL fragment.
     */
    public function fragment(?string $fragment = null): self|string|null
    {
        if (is_null($fragment)) {
            return $this->fragment;
        }

        $this->fragment = $fragment;
        return $this;
    }

    /**
     * Build the full fragment portion of a URL.
     */
    protected function buildFragment(): string
    {
        return $this->fragment ? '#' . $this->fragment : '';
    }

    /**
     * Set the number of links to display on each side of current page.
     */
    public function onEachSide(int $count): self
    {
        $this->onEachSide = $count;
        return $this;
    }

    // ============================================
    // URL GENERATION
    // ============================================

    public function url(int $page): string
    {
        if ($page <= 0) {
            $page = 1;
        }

        $parameters = array_merge($this->query, [$this->pageName => $page]);

        return $this->path . '?' . http_build_query($parameters) . $this->buildFragment();
    }

    public function nextPageUrl(): ?string
    {
        if ($this->hasMorePages()) {
            return $this->url($this->currentPage + 1);
        }

        return null;
    }

    public function previousPageUrl(): ?string
    {
        if ($this->currentPage > 1) {
            return $this->url($this->currentPage - 1);
        }

        return null;
    }

    public function firstPageUrl(): string
    {
        return $this->url(1);
    }

    public function lastPageUrl(): string
    {
        return $this->url($this->lastPage);
    }

    // ============================================
    // PAGINATION ELEMENTS
    // ============================================

    /**
     * Get the array of elements to pass to the view.
     */
    public function elements(): array
    {
        $window = $this->getUrlWindow();

        return array_filter([
            $window['first'],
            is_array($window['slider']) ? '...' : null,
            $window['slider'],
            is_array($window['last']) ? '...' : null,
            $window['last'],
        ]);
    }

    /**
     * Get the window of URLs to be shown.
     */
    protected function getUrlWindow(): array
    {
        return $this->getPageRange($this->onEachSide);
    }

    /**
     * Get the slider of URLs for pagination.
     * This method is public so it can be accessed from views.
     *
     * @param int|null $onEachSide
     * @return array
     */
    public function getPageRange(?int $onEachSide = null): array
    {
        $onEachSide = $onEachSide ?? $this->onEachSide;
        $window = $onEachSide * 2;

        if ($this->lastPage < $window + 6) {
            return $this->getSmallSlider();
        }

        return $this->getUrlSlider($onEachSide);
    }

    protected function getSmallSlider(): array
    {
        return [
            'first' => $this->getUrlRange(1, $this->lastPage),
            'slider' => null,
            'last' => null,
        ];
    }

    protected function getUrlSlider(int $onEachSide): array
    {
        $window = $onEachSide * 2;

        if ($this->currentPage <= $window) {
            return $this->getSliderTooCloseToBeginning($window, $onEachSide);
        } elseif ($this->currentPage >= $this->lastPage - $window) {
            return $this->getSliderTooCloseToEnding($window, $onEachSide);
        }

        return $this->getFullSlider($onEachSide);
    }

    protected function getSliderTooCloseToBeginning(int $window, int $onEachSide): array
    {
        return [
            'first' => $this->getUrlRange(1, $window + $onEachSide),
            'slider' => null,
            'last' => $this->getFinish(),
        ];
    }

    protected function getSliderTooCloseToEnding(int $window, int $onEachSide): array
    {
        $last = $this->getUrlRange(
            $this->lastPage - ($window + $onEachSide - 1),
            $this->lastPage
        );

        return [
            'first' => $this->getStart(),
            'slider' => null,
            'last' => $last,
        ];
    }

    protected function getFullSlider(int $onEachSide): array
    {
        return [
            'first' => $this->getStart(),
            'slider' => $this->getAdjacentUrlRange($onEachSide),
            'last' => $this->getFinish(),
        ];
    }

    protected function getStart(): array
    {
        return $this->getUrlRange(1, 2);
    }

    protected function getFinish(): array
    {
        return $this->getUrlRange($this->lastPage - 1, $this->lastPage);
    }

    protected function getAdjacentUrlRange(int $onEachSide): array
    {
        return $this->getUrlRange(
            $this->currentPage - $onEachSide,
            $this->currentPage + $onEachSide
        );
    }

    protected function getUrlRange(int $start, int $end): array
    {
        $urls = [];

        for ($page = $start; $page <= $end; $page++) {
            $urls[$page] = $this->url($page);
        }

        return $urls;
    }

    // ============================================
    // RENDERING
    // ============================================

    /**
     * Render pagination links.
     */
    public function links(?string $view = null): HtmlString
    {
        if ($this->lastPage <= 1) {
            return new HtmlString('');
        }

        $view = app(ViewFactoryInterface::class)->make(
            $view ?? 'pagination::length-awareness',
            ['paginator' => $this]
        );

        return new HtmlString($view->render());
    }

    /**
     * Render simple pagination links (previous/next only).
     */
    public function simple(?string $view = null): HtmlString
    {
        if ($this->lastPage <= 1) {
            return new HtmlString('');
        }

        $view = app(ViewFactoryInterface::class)->make(
           'pagination::simple',
            ['paginator' => $this]
        );

        return new HtmlString($view->render());
    }

    // ============================================
    // ITERATOR & ARRAY ACCESS
    // ============================================

    public function getIterator(): \Traversable
    {
        return $this->items->getIterator();
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->items->offsetExists($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items->offsetGet($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->items->offsetSet($offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->items->offsetUnset($offset);
    }

    // ============================================
    // SERIALIZATION
    // ============================================

    public function toArray(): array
    {
        return [
            'current_page' => $this->currentPage,
            'data' => $this->items->toArray(),
            'first_page_url' => $this->firstPageUrl(),
            'from' => $this->from,
            'last_page' => $this->lastPage,
            'last_page_url' => $this->lastPageUrl(),
            'links' => $this->linkCollection()->toArray(),
            'next_page_url' => $this->nextPageUrl(),
            'path' => $this->path,
            'per_page' => $this->perPage,
            'prev_page_url' => $this->previousPageUrl(),
            'to' => $this->to,
            'total' => $this->total,
        ];
    }

    /**
     * Get the paginator links as a collection.
     */
    protected function linkCollection(): Collection
    {
        $elements = $this->elements();
        $links = new Collection();

        // Add previous link
        $links->push([
            'url' => $this->previousPageUrl(),
            'label' => '&laquo; Previous',
            'active' => false,
        ]);

        // Add page links
        foreach ($elements as $element) {
            if (is_string($element)) {
                $links->push([
                    'url' => null,
                    'label' => $element,
                    'active' => false,
                ]);
            } elseif (is_array($element)) {
                foreach ($element as $page => $url) {
                    $links->push([
                        'url' => $url,
                        'label' => (string) $page,
                        'active' => $page === $this->currentPage,
                    ]);
                }
            }
        }

        // Add next link
        $links->push([
            'url' => $this->nextPageUrl(),
            'label' => 'Next &raquo;',
            'active' => false,
        ]);

        return $links;
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        return $this->toJson();
    }
}