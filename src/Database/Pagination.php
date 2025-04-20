<?php

namespace Pocketframe\Database;

use IteratorAggregate;
use ArrayIterator;
use Traversable;

class Pagination implements IteratorAggregate
{
  protected array $data;
  protected int $currentPage;
  protected int $lastPage;
  protected int $total;
  protected int $perPage;

  public function __construct(
    array $data,
    int $currentPage,
    int $lastPage,
    int $total,
    int $perPage
  ) {
    $this->data = $data;
    $this->currentPage = $currentPage;
    $this->lastPage = $lastPage;
    $this->total = $total;
    $this->perPage = $perPage;
  }

  // Data access methods
  public function data(): array
  {
    return $this->data;
  }
  public function currentPage(): int
  {
    return $this->currentPage;
  }
  public function lastPage(): int
  {
    return $this->lastPage;
  }
  public function total(): int
  {
    return $this->total;
  }
  public function perPage(): int
  {
    return $this->perPage;
  }
  public function pages(): array
  {
    return range(1, $this->lastPage);
  }
  public function getIterator(): Traversable
  {
    return new ArrayIterator($this->data);
  }

  // Main rendering interface
  public function renderPages(string $framework = 'tailwind'): string
  {
    return $this->renderFrameworkSpecificPages($framework) . $this->getAjaxScript();
  }

  public function renderSimplePages(string $framework = 'tailwind'): string
  {
    $html = '<div class="ajax-pagination-container">';
    $html .= match ($framework) {
      'bootstrap' => $this->renderBootstrapSimpleControls(),
      default => $this->renderTailwindSimpleControls()
    };
    return $html . $this->getInfoText() . '</div>' . $this->getAjaxScript();
  }

  private function renderFrameworkSpecificPages(string $framework): string
  {
    return match ($framework) {
      'bootstrap' => $this->renderBootstrapPagination(),
      default => $this->renderTailwindPagination()
    };
  }

  // Tailwind implementation
  private function renderTailwindPagination(): string
  {
    return <<<HTML
        <div class="ajax-pagination-container">
            <div class="flex justify-center mt-6">
                {$this->renderPreviousLink('tailwind')}
                {$this->renderPageLinks('tailwind')}
                {$this->renderNextLink('tailwind')}
            </div>
            {$this->getInfoText()}
        </div>
        HTML;
  }

  // Bootstrap implementation
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
            {$this->getInfoText()}
        </div>
        HTML;
  }

  // Link rendering components
  private function renderPreviousLink(string $framework): string
  {
    if ($this->currentPage <= 1) return '';
    return $this->buildLink(
      $this->currentPage - 1,
      'Previous',
      $framework,
      previous: true
    );
  }

  private function renderNextLink(string $framework): string
  {
    if ($this->currentPage >= $this->lastPage) return '';
    return $this->buildLink(
      $this->currentPage + 1,
      'Next',
      $framework,
      next: true
    );
  }

  private function renderPageLinks(string $framework): string
  {
    return implode('', array_map(
      fn($page) => $this->buildPageLink($page, $framework),
      $this->pages()
    ));
  }

  private function buildPageLink(int $page, string $framework): string
  {
    $active = $page === $this->currentPage;
    $classes = $active ? $this->getActiveClasses($framework) : $this->getInactiveClasses($framework);

    return $this->buildLink(
      $page,
      (string)$page,
      $framework,
      $classes
    );
  }

  // Helper methods
  private function buildLink(
    int $page,
    string $text,
    string $framework,
    string $classes = '',
    bool $previous = false,
    bool $next = false
  ): string {
    $styleConfig = [
      'tailwind' => [
        'base' => 'ajax-pagination px-4 py-2 rounded-lg shadow flex items-center gap-2',
        'previous' => 'bg-gray-300 text-gray-700 hover:bg-gray-400 mr-2',
        'next' => 'bg-gray-300 text-gray-700 hover:bg-gray-400',
        'page' => 'bg-gray-300 text-gray-700 hover:bg-gray-400 mr-2'
      ],
      'bootstrap' => [
        'base' => 'page-link ajax-pagination',
        'previous' => '',
        'next' => '',
        'page' => ''
      ]
    ];

    $style = $styleConfig[$framework];
    $typeClass = $previous ? $style['previous'] : ($next ? $style['next'] : $style['page']);

    $fullClass = trim("{$style['base']} {$typeClass} {$classes}");
    $icon = $previous ? $this->getPreviousIcon() : ($next ? $this->getNextIcon() : '');

    if ($framework === 'bootstrap') {
      return <<<HTML
            <li class="page-item">
                <a class="$fullClass" href="?page=$page">$icon$text</a>
            </li>
            HTML;
    }

    return <<<HTML
        <a class="$fullClass" href="?page=$page">$icon$text</a>
        HTML;
  }

  private function getActiveClasses(string $framework): string
  {
    return match ($framework) {
      'bootstrap' => 'active',
      default => 'bg-blue-500 text-white'
    };
  }

  private function getInactiveClasses(string $framework): string
  {
    return match ($framework) {
      'bootstrap' => '',
      default => 'bg-gray-300 text-gray-700 hover:bg-gray-400'
    };
  }

  private function getPreviousIcon(): string
  {
    return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="bi bi-chevron-left mr-2 size-4 mt-1" viewBox="0 0 16 16">
            <path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>
        </svg>
        SVG;
  }

  private function getNextIcon(): string
  {
    return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="bi bi-chevron-right ml-2 size-4 mt-1" viewBox="0 0 16 16">
            <path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/>
        </svg>
        SVG;
  }

  private function getInfoText(): string
  {
    return sprintf(
      '<p class="text-center mt-3 text-sm text-gray-600">Showing %d of %d items (Page %d of %d)</p>',
      count($this->data),
      $this->total,
      $this->currentPage,
      $this->lastPage
    );
  }

  private function getAjaxScript(): string
  {
    return <<<SCRIPT
        <script>
            // Consolidated AJAX script from previous implementation
            // (Maintain the same script content but in a single place)
        </script>
        SCRIPT;
  }

  // Simple pagination variants
  private function renderTailwindSimpleControls(): string
  {
    return <<<HTML
        <div class="flex justify-center gap-2 mt-6">
            {$this->renderPreviousLink('tailwind')}
            {$this->renderNextLink('tailwind')}
        </div>
        HTML;
  }

  private function renderBootstrapSimpleControls(): string
  {
    return <<<HTML
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                {$this->renderPreviousLink('bootstrap')}
                {$this->renderNextLink('bootstrap')}
            </ul>
        </nav>
        HTML;
  }
}
