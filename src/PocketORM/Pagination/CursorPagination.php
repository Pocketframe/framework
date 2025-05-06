<?php

namespace Pocketframe\PocketORM\Pagination;

use IteratorAggregate;
use ArrayIterator;
use Traversable;

class CursorPagination implements IteratorAggregate
{
  protected array   $data;
  protected ?string $nextCursor;
  protected ?string $previousCursor;
  protected bool    $hasNext;
  protected bool    $hasPrevious;
  protected int     $perPage;
  protected string  $baseUrl;
  protected string  $pageParam;

  /** the active framework name */
  protected string  $framework;

  /** processed styles for the active framework */
  protected array   $styles;

  /**
   * @param array       $data       The current page of items
   * @param string|null $nextCursor Cursor for the next page (or null)
   * @param string|null $prevCursor Cursor for the previous page (or null)
   * @param int         $perPage    Items per page
   * @param string      $pageParam  The query-string key (default: 'cursor')
   * @param string|null $baseUrl    Override base URL (otherwise detected)
   */
  public function __construct(
    array   $data,
    ?string $nextCursor,
    ?string $previousCursor,
    int     $perPage,
    string  $pageParam = 'cursor',
    ?string $baseUrl  = null
  ) {
    $this->data           = $data;
    $this->nextCursor     = $nextCursor;
    $this->previousCursor = $previousCursor;
    $this->hasNext        = $nextCursor !== null;
    $this->hasPrevious    = $previousCursor !== null;
    $this->perPage        = $perPage;
    $this->pageParam      = $pageParam;
    $this->baseUrl        = $baseUrl   ?? $this->detectBaseUrl();

    // Load framework + processed styles
    $this->framework = config('pagination.framework', 'tailwind');
    $this->styles    = $this->processStyles($this->framework);
  }

  /**
   * Detect the base URL of the current request.
   *
   * Detects the base URL of the current request by analyzing the
   * `REQUEST_URI` server variable. If the `REQUEST_URI` is not set
   * (e.g. when using a reverse proxy), the method returns an empty string.
   *
   * The method will also trim any query string parameters from the
   * detected base URL.
   *
   * @return string
   */
  private function detectBaseUrl(): string
  {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $pos = strpos($uri, '?');
    return $pos === false
      ? $uri
      : substr($uri, 0, $pos);
  }

  /**
   * Process styles for a given framework.
   *
   * Processes the styles for a given framework by replacing
   * color placeholders with actual color values.
   *
   * @param string $framework The framework name
   * @return array
   */
  private function processStyles(string $framework): array
  {
    $raw   = config("pagination.styles.{$framework}", []);
    $color = config('pagination.colors', []);
    return array_map(
      fn(string $class) => preg_replace_callback(
        '/\{\{(\w+)\}\}/',
        fn(array $map) => $color[$map[1]] ?? '',
        $class
      ),
      $raw
    );
  }

  /**
   * Get the current page data.
   *
   * Returns the current page data.
   *
   * @return array
   */
  public function data(): array
  {
    return $this->data;
  }

  /**
   * Get the cursor for the next page.
   *
   * Returns the cursor for the next page.
   *
   * @return ?string
   */
  public function nextCursor(): ?string
  {
    return $this->nextCursor;
  }

  /**
   * Get the cursor for the previous page.
   *
   * Returns the cursor for the previous page.
   *
   * @return ?string
   */
  public function previousCursor(): ?string
  {
    return $this->previousCursor;
  }

  /**
   * Check if there is a next page.
   *
   * Returns true if there is a next page, false otherwise.
   *
   * @return bool
   */
  public function hasNext(): bool
  {
    return $this->hasNext;
  }

  /**
   * Check if there is a previous page.
   *
   * Returns true if there is a previous page, false otherwise.
   *
   * @return bool
   */
  public function hasPrevious(): bool
  {
    return $this->hasPrevious;
  }

  /**
   * Get the number of items per page.
   *
   * Returns the number of items per page.
   *
   * @return int
   */
  public function perPage(): int
  {
    return $this->perPage;
  }
  public function getIterator(): Traversable
  {
    return new ArrayIterator($this->data);
  }

  /**
   * Render full Prev/Next + info
   *
   * Renders the full Prev/Next + info for the pagination.
   *
   * @param string|null $framework The framework to use for rendering
   * @return string The rendered pagination
   */
  public function renderCursor(?string $framework = null): string
  {
    $framework = $framework ?? $this->framework;
    if ($framework !== $this->framework) {
      $this->framework = $framework;
      $this->styles    = $this->processStyles($framework);
    }

    $info = sprintf(
      '<p class="%s">Showing %d items per page</p>',
      $this->styles['info_text'] ?? '',
      $this->perPage
    );

    return <<<HTML
<div class="ajax-pagination-container">
  {$this->buildFrameworkControls($framework)}
  {$info}
  {$this->getAjaxScript()}
</div>
HTML;
  }

  /**
   * Build the Prev/Next controls for a given framework.
   *
   * Builds the Prev/Next controls for a given framework.
   *
   * @param string $framework The framework to use for rendering
   * @return string The rendered controls
   */
  protected function buildFrameworkControls(string $framework): string
  {
    $previous = $this->buildLink(
      $this->previousCursor,
      '« Previous',
      $framework,
      '',
      true,   // previous?
      false   // next?
    );
    $next = $this->buildLink(
      $this->nextCursor,
      'Next »',
      $framework,
      '',
      false,
      true
    );
    return "<div class=\"flex justify-center gap-2\">{$previous}{$next}</div>";
  }

  /**
   * Build a single Prev/Next link (or disabled span).
   *
   * Builds a single Prev/Next link (or disabled span) for a given cursor.
   *
   * @param ?string $cursor The cursor for the link
   * @param string $text The text for the link
   * @param string $framework The framework to use for rendering
   * @param string $extraClasses Additional classes to add to the link
   * @param bool $previous Whether the link is for the previous page
   * @param bool $next Whether the link is for the next page
   * @return string The rendered link or span
   */
  protected function buildLink(
    ?string $cursor,
    string  $text,
    string  $framework,
    string  $extraClasses = '',
    bool    $previous     = false,
    bool    $next         = false
  ): string {
    $base = $this->styles['base'] ?? '';
    $key  = $previous ? 'previous' : ($next ? 'next' : 'page');
    $seg  = trim(($this->styles[$key] ?? '') . ' ' . $extraClasses);

    // disabled state
    if ($cursor === null) {
      if ($framework === 'bootstrap') {
        return "<li class=\"page-item disabled\"><span class=\"page-link {$seg}\">{$text}</span></li>";
      }
      return "<span class=\"{$base} {$seg} opacity-50 cursor-not-allowed\">{$text}</span>";
    }

    // build URL with both cursor + direction
    $params = $_GET;
    $params['cursor']    = $cursor;
    $params['direction'] = $next ? 'desc' : 'asc';
    $url = $this->baseUrl . '?' . http_build_query($params);

    if ($framework === 'bootstrap') {
      return "<li class=\"page-item\"><a class=\"page-link {$seg}\" href=\"{$url}\">{$text}</a></li>";
    }
    return "<a href=\"{$url}\" class=\"{$base} {$seg} ajax-pagination\">{$text}</a>";
  }

  /**
   * Get the Ajax script.
   *
   * Returns the Ajax script.
   *
   * @return string The Ajax script
   */
  private function getAjaxScript(): string
  {
    return <<<'JS'
<script>
if (!window.CursorPaginationAjax) {
  window.CursorPaginationAjax = true;
  document.addEventListener('click', e => {
    const link     = e.target.closest('a.ajax-pagination');
    if (!link) return e;
    e.preventDefault();

    const url      = link.href;
    const controls = link.closest('.ajax-pagination-container');
    const oldTable = controls?.previousElementSibling;

    history.pushState(null, '', url);

    fetch(url, {
      method: 'GET',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.text())
    .then(html => {
      const doc    = new DOMParser().parseFromString(html, 'text/html');
      const newCtr = doc.querySelector('.ajax-pagination-container');
      let newTbl   = newCtr?.previousElementSibling ?? null;

      if (oldTable && newTbl) oldTable.replaceWith(newTbl);
      if (controls && newCtr) controls.replaceWith(newCtr);
    })
    .catch(console.error);
  });
}
</script>
JS;
  }
}
