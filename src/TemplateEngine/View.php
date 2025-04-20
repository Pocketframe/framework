<?php

namespace Pocketframe\TemplateEngine;

class View
{
  /**
   * Global variables shared with every view.
   *
   * @var array
   */
  protected static array $shared = [];

  /**
   * Optionally injected TemplateCompiler instance.
   *
   * @var TemplateCompiler|null
   */
  protected static ?TemplateCompiler $compilerInstance = null;

  /**
   * Share a global variable with all templates.
   *
   * @param string $key
   * @param mixed $value
   * @return void
   */
  public static function share(string $key, $value): void
  {
    self::$shared[$key] = $value;
  }

  /**
   * Optionally set a custom compiler instance.
   *
   * This allows you to inject a pre-configured TemplateCompiler (or a factory object)
   * for easier testing or customization.
   *
   * @param TemplateCompiler $compiler
   * @return void
   */
  public static function setCompilerInstance(TemplateCompiler $compiler): void
  {
    self::$compilerInstance = $compiler;
  }

  /**
   * Retrieves a compiler instance.
   *
   * Returns an injected instance if available, otherwise a new one is created.
   *
   * @param string $template The name of the template.
   * @return TemplateCompiler
   */
  protected static function getCompilerInstance(string $template): TemplateCompiler
  {
    if (self::$compilerInstance) {
      // Optionally, reset or clone if needed.
      return self::$compilerInstance;
    }
    return new TemplateCompiler($template);
  }

  /**
   * Renders a template (from file) and returns the output.
   *
   * Merges global shared data with view-specific data.
   * Instead of using extract() directly, the data is assigned to $__data
   * so compiled templates can safely reference this container.
   *
   * @param string $template The name of the template (e.g. "home.index").
   * @param array $data The data specific to this view.
   * @return string The rendered HTML output.
   */
  public static function render(string $template, array $data = []): string
  {
    // Merge global shared data.
    $data = array_merge(self::$shared, $data);

    // Get a compiler instance.
    $compiler = static::getCompilerInstance($template);
    $compiler->with($data);
    $compiler->compile();
    $compiledFile = $compiler->getCompiledPath();

    // Extract data into variables to make them accessible in the template.
    extract($data, EXTR_SKIP);

    $__template = $compiler;

    ob_start();
    include $compiledFile;
    return ob_get_clean();
  }

  /**
   * Renders a raw PHP file (skipping template compilation).
   *
   * Global shared data is merged into view-specific data.
   *
   * @param string $filePath The file path to the view.
   * @param array $data The data specific to this view.
   * @return string The rendered output.
   * @throws \RuntimeException If the view file is not found.
   */
  public static function renderFile(string $filePath, array $data = []): string
  {
    if (!file_exists($filePath)) {
      throw new \RuntimeException("View file not found: {$filePath}");
    }

    // Merge global shared data.
    $data = array_merge(self::$shared, $data);

    // Extract variables for use in the view.
    extract($data, EXTR_SKIP);

    ob_start();
    include $filePath;
    return ob_get_clean();
  }
}
