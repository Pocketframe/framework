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

  public function __construct(array $data, int $currentPage, int $lastPage, int $total, int $perPage)
  {
    $this->data = $data;
    $this->currentPage = $currentPage;
    $this->lastPage = $lastPage;
    $this->total = $total;
    $this->perPage = $perPage;
  }

  /**
   * Get the paginated data.
   *
   * @return array
   */
  public function data(): array
  {
    return $this->data;
  }

  /**
   * Get the current page number.
   *
   * @return int
   */
  public function currentPage(): int
  {
    return $this->currentPage;
  }

  /**
   * Get the last page number.
   *
   * @return int
   */
  public function lastPage(): int
  {
    return $this->lastPage;
  }

  /**
   * Get the total number of records.
   *
   * @return int
   */
  public function total(): int
  {
    return $this->total;
  }

  /**
   * Get the number of items per page.
   *
   * @return int
   */
  public function perPage(): int
  {
    return $this->perPage;
  }

  /**
   * Get an array of page numbers.
   *
   * @return array
   */
  public function pages(): array
  {
    return range(1, $this->lastPage);
  }

  /**
   * Render the pagination links.
   *
   * @return string
   */
  public function renderPages(string $cssFramework = 'bootstrap'): string
  {
    $html = '<div>';

    // Render pagination controls
    if ($cssFramework !== 'bootstrap') {
      // Tailwind version
      $html .= '<div class="flex justify-center mt-6">';
      if ($this->currentPage() > 1) {
        $html .= '<a href="?page=' . ($this->currentPage() - 1) . '" class="ajax-pagination px-4 py-2 bg-gray-300 text-gray-700 rounded-lg shadow hover:bg-gray-400 flex mr-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="bi bi-chevron-left mr-2 size-4 mt-1" viewBox="0 0 16 16">
                          <path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>
                        </svg>
                        Previous
                      </a>';
      }
      foreach ($this->pages() as $page) {
        $activeClass = $page == $this->currentPage() ? 'bg-blue-500 text-white' : 'bg-gray-300 text-gray-700';
        $html .= '<a href="?page=' . $page . '" class="ajax-pagination px-4 py-2 ' . $activeClass . ' rounded-lg shadow hover:bg-gray-400 flex mr-2">' . $page . '</a>';
      }
      if ($this->currentPage() < $this->lastPage()) {
        $html .= '<a href="?page=' . ($this->currentPage() + 1) . '" class="ajax-pagination px-4 py-2 bg-gray-300 text-gray-700 rounded-lg shadow hover:bg-gray-400 flex">
                        Next
                        <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="bi bi-chevron-right ml-2 size-4 mt-1" viewBox="0 0 16 16">
                          <path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/>
                        </svg>
                      </a>';
      }
      $html .= '</div>';
      $html .= '<p class="text-center">
                    Showing ' . count($this->data()) . ' of ' . $this->total() . ' items.
                    (Page ' . $this->currentPage() . ' of ' . $this->lastPage() . ')
                  </p>';
    } else {
      // Bootstrap version
      $html .= '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
      if ($this->currentPage() > 1) {
        $html .= '<li class="page-item">
                        <a class="page-link ajax-pagination" href="?page=' . ($this->currentPage() - 1) . '">
                          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-chevron-left" viewBox="0 0 16 16">
                            <path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>
                          </svg>
                          Previous
                        </a>
                      </li>';
      }
      foreach ($this->pages() as $page) {
        $activeClass = $page == $this->currentPage() ? ' active' : '';
        $html .= '<li class="page-item' . $activeClass . '">
                        <a class="page-link ajax-pagination" href="?page=' . $page . '">' . $page . '</a>
                      </li>';
      }
      if ($this->currentPage() < $this->lastPage()) {
        $html .= '<li class="page-item">
                        <a class="page-link ajax-pagination" href="?page=' . ($this->currentPage() + 1) . '">
                          Next
                          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-chevron-right" viewBox="0 0 16 16">
                            <path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/>
                          </svg>
                        </a>
                      </li>';
      }
      $html .= '</ul></nav>';
      $html .= '<p class="text-center mt-3">
                    Showing ' . count($this->data()) . ' of ' . $this->total() . ' items.
                    (Page ' . $this->currentPage() . ' of ' . $this->lastPage() . ')
                  </p>';
    }
    $html .= '</div>'; // End of .ajax-pagination-container

    // Inline AJAX JavaScript to update the inner HTML of the container.
    $html .= '<script>
        document.addEventListener("DOMContentLoaded", function() {
    function attachAjaxPagination() {
        document.querySelectorAll(".ajax-pagination").forEach(function(link) {
            link.addEventListener("click", function(e) {
                e.preventDefault();
                var url = this.getAttribute("href");
                var container = this.closest(".ajax-pagination-container");
                if (!container) {
                    console.warn("No parent container found for AJAX pagination.");
                    return;
                }
                // Optional: append a timestamp to bypass cache
                url += (url.indexOf("?") !== -1 ? "&" : "?") + "t=" + new Date().getTime();
                fetch(url, {
                    headers: { "X-Requested-With": "XMLHttpRequest" }
                })
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error("Network response was not ok");
                    }
                    return response.text();
                })
                .then(function(data) {
                    // Debug: log the returned data
                    console.log("AJAX response:", data);
                    // Create a temporary element to parse the returned HTML
                    var tempDiv = document.createElement("div");
                    tempDiv.innerHTML = data;
                    var newContainer = tempDiv.querySelector(".ajax-pagination-container");
                    if (newContainer) {
                        // Replace the inner HTML of the current container with new containers inner HTML
                        container.innerHTML = newContainer.innerHTML;
                        console.log("Updated container content:", container.innerHTML);
                    } else {
                        // Fallback: update container with entire response
                        container.innerHTML = data;
                        console.warn("No .ajax-pagination-container found in the response.");
                    }
                    // Update browser URL
                    history.pushState(null, "", url);
                    // Reattach event handlers
                    attachAjaxPagination();
                })
                .catch(function(error) {
                    console.error("Error fetching AJAX content:", error);
                });
            });
        });
    }
    attachAjaxPagination();
});
    </script>';

    return $html;
  }


  /**
   * Make the Pagination object iterable.
   *
   * @return Traversable
   */
  public function getIterator(): Traversable
  {
    return new ArrayIterator($this->data);
  }
}
