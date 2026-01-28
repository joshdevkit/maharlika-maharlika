<?php

namespace Maharlika\Pagination;

use Maharlika\Contracts\View\ViewFactoryInterface;
use Maharlika\Database\Collection;
use Maharlika\Support\HtmlString;

class Paginator implements \JsonSerializable, \IteratorAggregate, \Countable
{
    protected Collection $items;
    protected int $total;
    public int $perPage;
    public int $currentPage;
    public int $lastPage;
    public ?int $from;
    public ?int $to;
    public string $path;

    public function __construct(
        Collection $items,
        int $total,
        int $perPage,
        int $currentPage,
        array $options = []
    ) {
        $this->items = $items;
        $this->total = $total;
        $this->perPage = $perPage;
        $this->currentPage = $currentPage;
        $this->lastPage = max((int) ceil($total / $perPage), 1);
        $this->path = $options['path'] ?? $this->getCurrentPath();

        $this->setFromTo();
    }

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

    protected function getCurrentPath(): string
    {
        $url = $_SERVER['REQUEST_URI'] ?? '/';
        return strtok($url, '?');
    }

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

    /**
     * Get the number of the first item in the slice.
     *
     * @return int|null
     */
    public function firstItem(): ?int
    {
        return $this->from;
    }

    /**
     * Get the number of the last item in the slice.
     *
     * @return int|null
     */
    public function lastItem(): ?int
    {
        return $this->to;
    }

    public function count(): int
    {
        return $this->items->count();
    }

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

    public function url(int $page): string
    {
        if ($page <= 0) {
            $page = 1;
        }

        $parameters = ['page' => $page];

        return $this->path . '?' . http_build_query($parameters);
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

    public function getPageRange(int $onEachSide = 3): array
    {
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
            return $this->getSliderTooCloseToBeginning($window);
        } elseif ($this->currentPage >= $this->lastPage - $window) {
            return $this->getSliderTooCloseToEnding($window);
        }

        return $this->getFullSlider($onEachSide);
    }

    protected function getSliderTooCloseToBeginning(int $window): array
    {
        return [
            'first' => $this->getUrlRange(1, $window + 2),
            'slider' => null,
            'last' => $this->getUrlRange($this->lastPage - 1, $this->lastPage),
        ];
    }

    protected function getSliderTooCloseToEnding(int $window): array
    {
        return [
            'first' => $this->getUrlRange(1, 2),
            'slider' => null,
            'last' => $this->getUrlRange($this->lastPage - ($window + 2), $this->lastPage),
        ];
    }

    protected function getFullSlider(int $onEachSide): array
    {
        return [
            'first' => $this->getUrlRange(1, 2),
            'slider' => $this->getUrlRange(
                $this->currentPage - $onEachSide,
                $this->currentPage + $onEachSide
            ),
            'last' => $this->getUrlRange($this->lastPage - 1, $this->lastPage),
        ];
    }

    protected function getUrlRange(int $start, int $end): array
    {
        $urls = [];

        for ($page = $start; $page <= $end; $page++) {
            $urls[$page] = $this->url($page);
        }

        return $urls;
    }

    /**
     * Get an iterator for the items.
     */
    public function getIterator(): \Traversable
    {
        return $this->items->getIterator();
    }

    /**
     * Render pagination links using a view component.
     *
     * @param string|null $view The view to use for rendering
     * @return \Maharlika\Support\HtmlString
     */
    public function links(?string $view = null): \Maharlika\Support\HtmlString
    {
        if ($this->lastPage <= 1) {
            return new \Maharlika\Support\HtmlString('');
        }

        $view = app(ViewFactoryInterface::class)->make(
            'pagination::default',
            ['paginator' => $this]
        );

        return new HtmlString($view->render());
    }

    /**
     * Render simple pagination links (previous/next only).
     *
     * @param string|null $view
     * @return \Maharlika\Support\HtmlString
     */
    public function simple(?string $view = null): \Maharlika\Support\HtmlString
    {
        if ($this->lastPage <= 1) {
            return new \Maharlika\Support\HtmlString('');
        }

        $view = app('view')->make(
           'pagination::simple',
            ['paginator' => $this]
        );

        return new HtmlString($view->render());
    }

    public function toArray(): array
    {
        return [
            'data' => $this->items->toArray(),
            'current_page' => $this->currentPage,
            'first_page_url' => $this->firstPageUrl(),
            'from' => $this->from,
            'last_page' => $this->lastPage,
            'last_page_url' => $this->lastPageUrl(),
            'next_page_url' => $this->nextPageUrl(),
            'path' => $this->path,
            'per_page' => $this->perPage,
            'prev_page_url' => $this->previousPageUrl(),
            'to' => $this->to,
            'total' => $this->total,
        ];
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
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
