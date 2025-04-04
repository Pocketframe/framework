<?php

namespace Pocketframe\TemplateEngine;

/**
 * The View class handles rendering the compiled templates.
 * It compiles the template (if necessary), extracts the provided data, and includes the compiled file.
 */
class View
{
  /**
   * Renders a template and returns the output.
   *
   * @param string $template The name of the template to render.
   * @param array $data The data to pass to the template.
   * @return string The rendered template.
   */
  public static function render(string $template, array $data = []): string
  {
    $compiler = new TemplateCompiler($template);
    $compiler->with($data);
    $compiler->compile();
    $compiledFile = $compiler->getCompiledPath();

    // Extract variables from the $data array into the current symbol table.
    extract($data);

    $__template = $compiler;

    ob_start();
    include $compiledFile;
    return ob_get_clean();
  }



  /**
   * Renders a raw PHP file (skipping template compilation).
   */
  public static function renderFile(string $filePath, array $data = []): string
  {
    if (!file_exists($filePath)) {
      throw new \RuntimeException("View file not found: {$filePath}");
    }

    extract($data);
    ob_start();
    include $filePath;
    return ob_get_clean();
  }
}
