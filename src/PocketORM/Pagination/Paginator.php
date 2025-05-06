<?php

namespace Pocketframe\PocketORM\Pagination;

use IteratorAggregate;
use ArrayIterator;
use Traversable;

class Paginator implements IteratorAggregate
{
  /**
   * the dataset to paginate
   * @var iterable
   */
  protected iterable $data;

  /**
   * the current page number
   * @var int
   */
  protected int      $currentPage;

  /**
   * the last page number
   * @var int
   */
  protected int      $lastPage;

  /**
   * the total number of items
   * @var int
   */
  protected int      $total;

  /**
   * the number of items per page
   * @var int
   */
  protected int      $perPage;

  /**
   * the base URL for the pagination links
   * @var string
   */
  protected string   $baseUrl;

  /**
   * the page parameter name
   * @var string
   */
  protected string   $pageParam;

  /**
   * the active framework name
   * @var string
   */
  protected string   $framework;

  /**
   * processed styles for the active framework
   * @var array
   */
  protected array    $styles;

  /**
   * constructor
   *
   * The constructor of the Pagination class.
   * Sets the styles for the active framework.
   *
   * @param iterable $data The data to be paginated
   * @param int $currentPage The current page
   * @param int $lastPage The last page
   * @param int $total The total number of items
   * @param int $perPage The number of items per page
   * @param string $pageParam The page parameter name
   * @param ?string $baseUrl The base URL for the pagination links
   */
  public function __construct(
    iterable $data,
    int      $currentPage,
    int      $lastPage,
    int      $total,
    int      $perPage,
    string   $pageParam = 'page',
    ?string  $baseUrl   = null
  ) {
    $this->data        = $data;
    $this->currentPage = $currentPage;
    $this->lastPage    = $lastPage;
    $this->total       = $total;
    $this->perPage     = $perPage;
    $this->baseUrl     = $baseUrl   ?? $this->detectBaseUrl();
    $this->pageParam   = $pageParam;

    // 1) grab the default framework from config
    $this->framework = config('pagination.framework', 'tailwind');

    // 2) process its style map + color tokens
    $this->styles = $this->processStyles($this->framework);
  }

  /**
   * Detect the base URL for the pagination links
   *
   * Detects the base URL for the pagination links based on the current
   * request URI. If the URI contains a query string, the query string
   * is removed from the URI and the resulting string is returned as
   * the base URL. If the URI does not contain a query string, the URI
   * is returned as the base URL.
   *
   * @return string The base URL
   */
  private function detectBaseUrl(): string
  {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    return false === ($pos = strpos($uri, '?'))
      ? $uri
      : substr($uri, 0, $pos);
  }

  /**
   * Get the paginated data
   *
   * Returns the paginated data as an array. If the data is already an array,
   * it is returned as is. Otherwise, it is converted to an array.
   *
   * @return array The paginated data
   */
  public function data(): array
  {
    return is_array($this->data) ? $this->data : iterator_to_array($this->data);
  }

  /**
   * Get the current page number
   *
   * Returns the current page number.
   *
   * @return int The current page number
   */
  public function currentPage(): int
  {
    return $this->currentPage;
  }

  /**
   * Get the last page number
   *
   * Returns the last page number.
   *
   * @return int The last page number
   */
  public function lastPage(): int
  {
    return $this->lastPage;
  }

  /**
   * Get the total number of items
   *
   * Returns the total number of items.
   *
   * @return int The total number of items
   */
  public function total(): int
  {
    return $this->total;
  }

  /**
   * Get the number of items per page
   *
   * Returns the number of items per page.
   *
   * @return int The number of items per page
   */
  public function perPage(): int
  {
    return $this->perPage;
  }

  /**
   * Get the pages.
   *
   * Returns the pages as an array.
   *
   * @return array The pages
   */
  public function pages(): array
  {
    return range(1, $this->lastPage);
  }

  /**
   * Get the iterator
   *
   * Returns the iterator.
   *
   * @return Traversable The iterator
   */
  public function getIterator(): Traversable
  {
    return new ArrayIterator($this->data);
  }

  /**
   * Process the styles for the active framework
   *
   * Processes the styles for the active framework.
   *
   * @param string $framework The framework name
   * @return array The processed styles
   */
  protected function processStyles(string $framework): array
  {
    // pull the raw style map for this framework
    $raw   = config("pagination.styles.{$framework}", []);
    // pull your color tokens
    $color = config('pagination.colors', []);

    // replace all `{{token}}` placeholders with their env value
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
   * Render the pagination links
   *
   * Renders the pagination links for the active framework.
   *
   * @param ?string $framework The framework name
   * @return string The rendered pagination links
   */
  public function renderPages(?string $framework = null): string
  {
    $framework = $framework ?? $this->framework;

    // if caller overrides framework, recompute styles on the fly:
    if ($framework !== $this->framework) {
      $this->styles    = $this->processStyles($framework);
      $this->framework = $framework;
    }

    return $this->renderFrameworkSpecificPages($framework)
      . $this->getAjaxScript();
  }

  /**
   * Render the pagination links for the active framework
   *
   * Renders the pagination links for the active framework.
   *
   * @param string $framework The framework name
   * @return string The rendered pagination links
   */
  private function renderFrameworkSpecificPages(string $framework): string
  {
    return match ($framework) {
      'bootstrap' => $this->renderBootstrapPagination(),
      default     => $this->renderTailwindPagination(),
    };
  }

  /**
   * Render the pagination links for the Tailwind CSS framework
   *
   * Renders the pagination links for the Tailwind CSS framework.
   *
   * @return string The rendered pagination links
   */
  private function renderTailwindPagination(): string
  {
    return <<<HTML
<div class="ajax-pagination-container">
  <div class="flex justify-center mt-6">
    {$this->renderPreviousLink('tailwind')}
    {$this->renderPageLinks('tailwind')}
    {$this->renderNextLink('tailwind')}
  </div>
  {$this->getInfoText('tailwind')}
</div>
HTML;
  }

  /**
   * Render the pagination links for the Bootstrap framework
   *
   * Renders the pagination links for the Bootstrap framework.
   *
   * @return string The rendered pagination links
   */
  private function renderBootstrapPagination(): string
  {
    return <<<HTML
<div class="ajax-pagination-container">
  <nav aria-label="Page navigation">
    <ul class="pagination justify-content-center">
      {$this->renderPreviousLink('bootstrap')}
      {$this->renderPageLinks('bootstrap')}
      {$this->renderNextLink('bootstrap')}
    </ul>
  </nav>
  {$this->getInfoText('bootstrap')}
</div>
HTML;
  }

  /**
   * Render the previous link
   *
   * Renders the previous link for the active framework.
   *
   * @param string $framework The framework name
   * @return string The rendered previous link
   */
  private function renderPreviousLink(string $framework): string
  {
    $disabled = $this->currentPage <= 1;
    return $this->buildLink(
      $this->currentPage - 1,
      '« Previous',
      $framework,
      previous: true,
      disabled: $disabled
    );
  }

  /**
   * Render the next link
   *
   * Renders the next link for the active framework.
   *
   * @param string $framework The framework name
   * @return string The rendered next link
   */
  private function renderNextLink(string $framework): string
  {
    $disabled = $this->currentPage >= $this->lastPage;
    return $this->buildLink(
      $this->currentPage + 1,
      'Next »',
      $framework,
      next: true,
      disabled: $disabled
    );
  }

  /**
   * Render the page links
   *
   * Renders the page links for the active framework.
   *
   * @param string $framework The framework name
   * @return string The rendered page links
   */
  private function renderPageLinks(string $framework): string
  {
    return implode('', array_map(
      fn(int $page) => $this->buildPageLink($page, $framework),
      $this->pages()
    ));
  }

  /**
   * Build the page link
   *
   * Builds the page link for the active framework.
   *
   * @param int    $page     The page number
   * @param string $framework The framework name
   * @return string The built page link
   */
  private function buildPageLink(int $page, string $framework): string
  {
    $active = $page === $this->currentPage;
    $class  = $active
      ? ($this->styles[$framework]['active'] ?? '')
      : ($this->styles[$framework]['page']   ?? '');

    return $this->buildLink(
      $page,
      (string)$page,
      $framework,
      $class
    );
  }

  /**
   * Build the link
   *
   * Builds the link for the active framework.
   *
   * @param int    $page     The page number
   * @param string $text     The link text
   * @param string $framework The framework name
   * @param string $typeClasses The type classes
   * @param bool   $previous Whether the link is previous
   * @param bool   $next     Whether the link is next
   * @param bool   $disabled Whether the link is disabled
   * @return string The built link
   */
  private function buildLink(
    int    $page,
    string $text,
    string $framework,
    string $typeClasses = '',
    bool   $previous    = false,
    bool   $next        = false,
    bool   $disabled    = false
  ): string {
    $map    = $this->styles;
    $base   = $map['base'] ?? '';
    $key    = $previous ? 'previous' : ($next ? 'next' : 'page');
    $seg    = $map[$key] ?? '';
    if ($page === $this->currentPage) {
      $seg = $map['active'] ?? $seg;
    }

    // pull a “disabled” style if you defined one, otherwise fall back
    $disabledClass = $map['disabled']
      ?? 'opacity-50 cursor-not-allowed pointer-events-none';

    // if disabled, append that and wipe out $href
    $fullClass = trim(
      $base
        . ' ' . ($disabled ? $disabledClass : $seg)
        . ' ' . $typeClasses
    );

    $href = $disabled
      ? 'javascript:void(0)'
      : ($this->baseUrl . '?' . http_build_query([
        ...$_GET,
        $this->pageParam => $page,
      ]));

    if ($framework === 'bootstrap') {
      // bootstrap wants <li class="disabled page-item"> and <a tabindex="-1" aria-disabled="true">
      $liClass  = $disabled ? 'page-item disabled' : 'page-item';
      $aAttrs = $disabled
        ? ' tabindex="-1" aria-disabled="true"'
        : '';
      return "<li class=\"{$liClass}\">"
        . "<a class=\"page-link {$fullClass}\" href=\"{$href}\"{$aAttrs}>{$text}</a>"
        . "</li>";
    }

    // Tailwind (or default)
    $disabledAttr = $disabled ? ' aria-disabled="true"' : '';
    return "<a class=\"{$fullClass} ajax-pagination\" href=\"{$href}\"{$disabledAttr}>{$text}</a>";
  }

  /**
   * Get the info text
   *
   * Gets the info text for the active framework.
   *
   * @return string The info text
   */
  private function getInfoText(): string
  {
    $class = $this->styles['info_text'] ?? '';
    return sprintf(
      '<p class="%s">Showing %d of %d items (Page %d of %d)</p>',
      $class,
      count($this->data()),
      $this->total,
      $this->currentPage,
      $this->lastPage
    );
  }


  /**
   * Get the ajax script
   *
   * Gets the ajax script for the active framework.
   *
   * @return string The ajax script
   */
  private function getAjaxScript(): string
  {
    return <<<'JS'
<script>
if (!window.PaginationAjaxInitialized) {
  window.PaginationAjaxInitialized = true;

  document.addEventListener('click', function(e) {
    const link = e.target.closest('a.ajax-pagination');
    if (!link) return;
    e.preventDefault();

    const url = link.href;
    history.pushState(null, '', url);

    fetch(url, {
      method: 'GET',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.text())
    .then(html => {
      const parser = new DOMParser();
      const doc    = parser.parseFromString(html, 'text/html');

      // 1) Find the old pagination container & its preceding table
      const oldPag = document.querySelector('.ajax-pagination-container');
      let oldTable = oldPag;
      while(oldTable && oldTable.tagName !== 'TABLE') {
        oldTable = oldTable.previousElementSibling;
      }

      // 2) Find the new ones in the fresh HTML
      const newPag = doc.querySelector('.ajax-pagination-container');
      let newTable = newPag;
      while(newTable && newTable.tagName !== 'TABLE') {
        newTable = newTable.previousElementSibling;
      }

      // 3) Swap them in-place
      if (newTable && oldTable) {
        oldTable.parentNode.replaceChild(newTable, oldTable);
      }
      if (newPag && oldPag) {
        oldPag.parentNode.replaceChild(newPag, oldPag);
      }
    })
    .catch(err => console.error('Pagination AJAX error:', err));
  });
}
</script>
JS;
  }
}
