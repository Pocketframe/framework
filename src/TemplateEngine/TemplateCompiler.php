<?php

namespace Pocketframe\TemplateEngine;

use Exception;
use Pocketframe\Http\Response\Response;

class TemplateCompiler
{
  protected string $templatePath;
  protected string $compiledPath;

  public function __construct(string $templateName, bool $isFrameworkTemplate = false)
  {
    if ($isFrameworkTemplate) {
      $this->templatePath = __DIR__ . '/../resources/views/errors/' . Response::NOT_FOUND . '.view.php';
    } else {
      // User's views
      $this->templatePath = base_path("resources/views/{$templateName}.view.php");
    }
    // Generate a stable cache filename so the same template doesn't recompile every time.
    $this->compiledPath = base_path("store/framework/views/" . $this->cacheViewName($templateName));
  }

  public function compile(): void
  {
    // check if the environment is local
    // Recompile only if the source template is newer than the compiled file.
    $forceCompile = env('APP_ENV') === 'local';
    $filesToCheck = [$this->templatePath];

    // Check for parent template if extending
    $templateContent = file_get_contents($this->templatePath);
    if (preg_match('/<%\s*extends\s*[\'\"](.+?)[\'\"]\s*%>/', $templateContent, $match)) {
      $parentPath = base_path("resources/views/{$match[1]}.view.php");
      $filesToCheck[] = $parentPath;
    }

    $latestMTime = $this->getLatestModificationTime($filesToCheck);

    if (!$forceCompile && file_exists($this->compiledPath) && filemtime($this->compiledPath) >= $latestMTime) {
      return;
    }


    if (!file_exists($this->templatePath)) {
      throw new Exception("Template file not found: {$this->templatePath}");
    }

    $content = file_get_contents($this->templatePath);

    // Ensure the cache directory exists
    $compiledDir = dirname($this->compiledPath);
    if (!is_dir($compiledDir)) {
      mkdir($compiledDir, 0777, true);
    }

    // Handle template inheritance
    $content = $this->processTemplateInheritance($content);

    // Remove comments (both block and single-line)
    $content = $this->removeComments($content);

    // Convert template syntax to PHP
    $content = $this->convertSyntax($content);

    // Remove any unreplaced yield placeholders (if any remain, replace with empty string)
    $content = preg_replace('/<%\s*yield\s+\w+\s*%>/', '', $content);

    // Write the compiled template to cache
    file_put_contents($this->compiledPath, $content);
  }

  protected function getLatestModificationTime(array $files): int
  {
    $latest = 0;
    foreach ($files as $file) {
      if (file_exists($file)) {
        $latest = max($latest, filemtime($file));
      }
    }
    return $latest;
  }

  protected function removeComments(string $content): string
  {
    // Remove block comments <#-- ... --#> (multiline)
    $content = preg_replace('/<#--[\s\S]*?--#>/', '', $content);
    // Remove single-line comments <%-- ... --%>
    $content = preg_replace('/<%--(.*?)--%>/m', '', $content);
    return $content;
  }

  protected function processTemplateInheritance(string $content): string
  {
    if (preg_match('/<%\s*extends\s*[\'\"](.+?)[\'\"]\s*%>/', $content, $match)) {
      $parentTemplate = $match[1];
      $content = str_replace($match[0], '', $content); // Remove extends tag

      // Extract child blocks
      preg_match_all('/<%\s*block\s+(\w+)\s*%>(.*?)<%\s*endblock\s*%>/s', $content, $childBlockMatches);
      $childBlocks = array_combine($childBlockMatches[1], $childBlockMatches[2]);

      // Load parent template
      $parentPath = base_path("resources/views/{$parentTemplate}.view.php");
      if (!file_exists($parentPath)) {
        throw new Exception("Parent template file not found: {$parentPath}");
      }
      $parentContent = file_get_contents($parentPath);

      // Replace yield placeholders with child block content
      foreach ($childBlocks as $blockName => $blockContent) {
        $parentContent = preg_replace('/<%\s*yield\s*' . preg_quote($blockName, '/') . '\s*%>/', $blockContent, $parentContent);
      }

      return $parentContent;
    }

    return $content;
  }

  protected function convertSyntax(string $content): string
  {
    // Convert plain PHP blocks: <% php %> ... <% endphp %>
    $content = preg_replace_callback('/<%\s*php\s*%>(.*?)<%\s*endphp\s*%>/s', function ($matches) {
      return '<?php ' . $matches[1] . ' ?>';
    }, $content);

    // Convert echo shorthand using a callback
    $content = preg_replace_callback('/<%=\s*(.+?)\s*%>/', function ($matches) {
      $expr = trim($matches[1]);
      // If the expression starts with "yield", output nothing
      if (stripos($expr, 'yield') === 0) {
        return '';
      }
      // If the expression starts with "route(", output it raw (unescaped)
      if (stripos($expr, 'route(') === 0) {
        return '<?php echo ' . $expr . '; ?>';
      }
      // Otherwise, safely echo the expression, defaulting to empty string if null\n
      return '<?php echo htmlspecialchars((' . $expr . ') ?? \'\', ENT_QUOTES, "UTF-8"); ?>';
    }, $content);

    // Convert raw echo without escaping
    $content = preg_replace('/<%!\s*(.+?)\s*%>/', '<?php echo $1; ?>', $content);

    // Convert control structures
    $patterns = [
      '/<%\s*if\s*\((.+?)\)\s*%>/' => '<?php if ($1): ?>',
      '/<%\s*elseif\s*\((.+?)\)\s*%>/' => '<?php elseif ($1): ?>',
      '/<%\s*else\s*%>/' => '<?php else: ?>',
      '/<%\s*endif\s*%>/' => '<?php endif; ?>',
      '/<%\s*foreach\s*\((.+?)\)\s*%>/' => '<?php foreach ($1): ?>',
      '/<%\s*endforeach\s*%>/' => '<?php endforeach; ?>',
      '/<%\s*for\s*\((.+?)\)\s*%>/' => '<?php for ($1): ?>',
      '/<%\s*endfor\s*%>/' => '<?php endfor; ?>',
      '/<%\s*while\s*\((.+?)\)\s*%>/' => '<?php while ($1): ?>',
      '/<%\s*endwhile\s*%>/' => '<?php endwhile; ?>',
      '/<%\s*switch\s*\((.+?)\)\s*%>/' => '<?php switch ($1): ?>',
      '/<%\s*case\s+(.+?)\s*%>/' => '<?php case $1: ?>',
      '/<%\s*break\s*%>/' => '<?php break; ?>',
      '/<%\s*endswitch\s*%>/' => '<?php endswitch; ?>',
    ];


    foreach ($patterns as $pattern => $replacement) {
      $content = preg_replace($pattern, $replacement, $content);
    }

    // CSRF and method spoofing: support both with and without '='\n
    $content = str_replace('<% csrf_token %>', '<?php echo csrf_token(); ?>', $content);
    $content = str_replace('<%= csrf_token %>', '<?php echo csrf_token(); ?>', $content);
    $content = preg_replace('/<% method\s*(.+?)\s*%>/', '<?php echo method($1); ?>', $content);

    // Route with parameters for tags written without '=' (if any remain)\n
    $content = preg_replace('/<%\s*route\s*\((.+?)\)\s*%>/', '<?php echo route($1); ?>', $content);

    // Optionally remove extra whitespace between HTML tags\n
    $content = preg_replace('/>\s+</', '><', $content);

    return $content;
  }


  public function getCompiledPath(): string
  {
    return $this->compiledPath;
  }

  function cacheViewName(string $viewPath): string
  {
    // Generate a stable cache filename based solely on the view name
    return md5($viewPath) . '.php';
  }
}
