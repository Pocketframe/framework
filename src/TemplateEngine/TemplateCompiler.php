<?php

namespace Pocketframe\TemplateEngine;

use Exception;

class TemplateCompiler
{
  protected string $templatePath;
  protected string $compiledPath;

  public function __construct(string $templateName, bool $isFrameworkTemplate = false)
  {
    if ($isFrameworkTemplate) {
      // Points to vendor pocketframe’s error views
      // Adjust if your real path differs
      $this->templatePath = __DIR__ . '/../../resources/views/' . $templateName . '.view.php';
    } else {
      // Existing user path
      $this->templatePath = base_path("resources/views/{$templateName}.view.php");
    }
    $this->compiledPath = base_path("store/framework/views/" . $this->cacheViewName($templateName));
  }

  public function compile(): void
  {
    if (file_exists($this->compiledPath) && filemtime($this->compiledPath) >= filemtime($this->templatePath)) {
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

    // Remove comments
    $content = $this->removeComments($content);

    // Convert template syntax to PHP
    $content = $this->convertSyntax($content);

    // Ensure all yield placeholders have been replaced
    if (preg_match('/<%\s*yield\s+(\w+)\s*%>/', $content, $unreplaced)) {
      throw new Exception("Unreplaced yield block found in compiled template: {$this->compiledPath}. Block: {$unreplaced[1]}");
    }

    // Write the compiled template to cache
    file_put_contents($this->compiledPath, $content);
  }

  protected function removeComments(string $content): string
  {
    // Remove block comments <#-- ... --#>
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

      // Replace yield placeholders with child blocks
      foreach ($childBlocks as $blockName => $blockContent) {
        $parentContent = preg_replace('/<%\s*yield\s*' . preg_quote($blockName, '/') . '\s*%>/', $blockContent, $parentContent);
      }

      return $parentContent;
    }

    return $content;
  }

  protected function convertSyntax(string $content): string
  {
    // Echoing values safely
    $content = preg_replace('/<%=\s*(.+?)\s*%>/', '<?php echo htmlspecialchars($1, ENT_QUOTES, "UTF-8"); ?>', $content);
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
      '/<%\s*endswitch\s*%>/' => '<?php endswitch; ?>'
    ];

    foreach ($patterns as $pattern => $replacement) {
      $content = preg_replace($pattern, $replacement, $content);
    }

    // CSRF and method spoofing
    $content = str_replace('<%= csrf_token %>', '<?php echo csrf_token(); ?>', $content);
    $content = preg_replace('/<% method\s*(.+?)\s*%>/', '<?php echo method($1); ?>', $content);

    // Route with parameters
    $content = preg_replace('/<%\s*route\s*\((.+?)\)\s*%>/', '<?php echo route($1); ?>', $content);

    $content = preg_replace_callback(
      '/<%\s*(if|foreach|block|method)\s*(\(.*?\))?\s*%>/',
      function ($matches) {
        $directive = $matches[1];
        $condition = $matches[2] ?? '';
        return "<?php {$directive}{$condition}: ?>";
      },
      $content
    );

    $patterns = [
      '/>\s+</',             // Remove whitespace between HTML tags
      '/<(\w+)([^>]*)\/>/'   // Self-closing tags pattern
    ];

    $replacements = [
      '><',                   // Replacement for whitespace
      '<$1$2></$1>'           // Replacement for self-closing tags
    ];

    $content = preg_replace($patterns, $replacements, $content);


    return $content;
  }

  public function getCompiledPath(): string
  {
    return $this->compiledPath;
  }

  function cacheViewName(string $viewPath): string
  {
    return md5($viewPath . microtime(true)) . '.php';
  }
}
