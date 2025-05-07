<?php

namespace Pocketframe\TemplateEngine;

use Exception;
use Pocketframe\Cache\Mask\Cache;
use Pocketframe\Http\Response\Response;

/**
 * Class TemplateCompiler
 *
 * Compiles custom template syntax into executable PHP code.
 * Handles template inheritance, caching, components, and more.
 *
 * When including the compiled file, assign the TemplateCompiler instance
 * to the variable $__template. For example:
 *
 *   $__template = $compiler;
 *   include $compiler->getCompiledPath();
 */
class TemplateCompiler
{
  /** @var string Path to the source template file. */
  protected string $templatePath;

  /** @var string Path to the compiled template file. */
  protected string $compiledPath;

  /** @var array Stores block content for template inheritance. */
  protected array $blocks = [];

  /** @var array Stores reusable component content. */
  protected array $components = [];

  /** @var array Tracks cache blocks and their parameters. */
  protected array $cacheStack = [];

  /** @var array Tracks error blocks and their fields. */
  protected array $errorStack = [];

  /** @var array Tracks nested block and component stacks. */
  protected array $stacks = [];

  /** @var array Data passed to the template for rendering. */
  protected array $data = [];

  /** @var array Tracks sublayout hierarchy for template inheritance. */
  protected array $sublayoutStack = [];

  /** @var array Stores validation errors. */
  protected array $errors = [];

  /**
   * Used for unique naming in each/endeach directives.
   * @var int
   */
  protected int $eachCount = 0;

  /**
   * Stack for storing unique empty output variable names for each loops.
   * @var array
   */
  protected array $eachVarStack = [];

  protected array $slots = [];

protected array $slotStack = [];

  /**
   * TemplateCompiler constructor.
   *
   * @param string $templateName The name of the template to compile.
   * @param bool $isFrameworkTemplate Whether the template is a framework template.
   */
  public function __construct(string $templateName, bool $isFrameworkTemplate = false)
  {
    if ($isFrameworkTemplate) {
      $templateName = str_replace('.', '/', $templateName);
      $this->templatePath = __DIR__ . '/../../resources/views/' . $templateName . '.view.php';
    } else {
      $templateName = str_replace('.', '/', $templateName);
      $this->templatePath = base_path("resources/views/{$templateName}.view.php");
    }
    $this->compiledPath = base_path("store/framework/views/" . $this->cacheViewName($templateName));
  }

  public function __get($name)
  {
    if (property_exists($this, $name)) {
      return $this->$name;
    }
    return null;
  }


  /**
   * Compiles the template into executable PHP code.
   *
   * @throws Exception If the template file is not found.
   */
  public function compile(): void
  {
    $forceCompile = env('APP_ENV') === 'local';
    $filesToCheck = [$this->templatePath];

    $templateContent = file_get_contents($this->templatePath);
    if (preg_match('/@layout\(\s*[\'"](.+?)[\'"]\s*\)/', $templateContent, $match)) {
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
    $content = $this->processTemplateInheritance($content);
    $content = $this->compileComments($content);
    $content = $this->compileEchos($content);
    $content = $this->compileStatements($content);

    // Process @component blocks (with slots)
    $content = preg_replace_callback('/@component\((.*?)\)(.*?)@endcomponent/s', function ($matches) {
      return $this->compileComponentBlock($matches[1], $matches[2]);
    }, $content);

    // Process @include directives for partials.
    $content = preg_replace_callback('/@include\((.*?)\)/s', function ($matches) {
      return $this->compileInclude($matches[1]);
    }, $content);

    // Process other directives
    $content = $this->compileStatements($content);


    $content = preg_replace_callback(
      '/<x-paginate\s+([^\/>]+?)\s*\/\>/i',
      function ($matches) {
        $attrString = $matches[1];
        preg_match_all(
          '/(?P<key>[:\w-]+)\s*=\s*(?P<quote>[\'\"])([^\'\"]+)\k<quote>/x',
          $attrString,
          $attrMatches,
          PREG_SET_ORDER
        );
        $attrs = [];
        foreach ($attrMatches as $attr) {
          $key = ltrim($attr['key'], ':');
          $attrs[$key] = $attr[3];
        }
        $dataset = $attrs['dataset'] ?? 'null';
        $method = (isset($attrs['mode']) && strtolower($attrs['mode']) === 'cursor')
          ? 'renderCursor'
          : 'renderPages';
        return "<?php echo {$dataset}->{$method}(); ?>";
      },
      $content
    );

    // Process XML-like <x-...> component tags.
    $content = preg_replace_callback('/<x-([\w-]+)([^>]*)>(.*?)<\/x-\1>/s', function ($matches) {
      return $this->compileXComponent($matches);
    }, $content);


    $content = $this->minify($content);

    // Prepend source mapping metadata:
    $sourceMapping = "<?php /* Source: {$this->templatePath}, line_offset: 0 */ ?>\n";
    $content = $sourceMapping . $content;

    $compiledDir = dirname($this->compiledPath);
    if (!is_dir($compiledDir)) {
      mkdir($compiledDir, 0777, true);
    }

    file_put_contents($this->compiledPath, $content);
  }

  /**
   * Compiles template control structures (e.g., @if, @foreach, @each, etc.).
   *
   * Uses a regex pattern that handles a single level of nested parentheses.
   *
   * @param string $content The template content to compile.
   * @return string The compiled PHP code.
   */
  protected function compileStatements(string $content): string
  {
    return preg_replace_callback(
      '/\B@(\w+)(?:\s*\(([^()]*(?:\([^)]*\)[^()]*)*)\))?/s',
      function ($match) {
        $directive = $match[1];
        $args = isset($match[2]) ? $this->parseArguments($match[2]) : '';
        return $this->compileControlStructures($directive, $args);
      },
      $content
    );
  }

  /**
   * Compiles individual control structures into PHP code.
   *
   * Instance method calls are replaced with calls on the variable $__template.
   *
   * @param string $directive The directive name.
   * @param string $args The parsed arguments.
   * @return string The compiled PHP code.
   */
  protected function compileControlStructures(string $directive, string $args): string
  {
    return match ($directive) {
      'layout'       => "<?php \$__template->layout($args); ?>",
      'sublayout'    => "<?php \$__template->sublayout($args); ?>",
      'block'        => "<?php \$__template->startBlock($args); ?>",
      'endblock'     => "<?php \$__template->endBlock(); ?>",
      'insert'       => "<?php echo \$__template->insert($args); ?>",
      'if'           => "<?php if ($args): ?>",
      'elseif'       => "<?php elseif ($args): ?>",
      'else'         => "<?php else: ?>",
      'endif'        => "<?php endif; ?>",
      'foreach'      => $this->compileForEach($args),
      'endforeach'   => "<?php endforeach; ?>",
      'each'         => $this->compileEach($args),
      'endeach'      => $this->compileEndEach(),
      'embed'        => "<?php \$__template->embed($args); ?>",
      'component'    => "<?php \$__template->startComponent($args); ?>",
      'endcomponent' => "<?php \$__template->endComponent(); ?>",
      'method'       => '<?php echo \Pocketframe\TemplateEngine\TemplateCompiler::methodHelper(' . $args . '); ?>',
      'csrf'         => '<?php echo \Pocketframe\TemplateEngine\TemplateCompiler::csrfHelper(); ?>',
      'error'        => "<?php \$__template->startError($args); ?>",
      'enderror'     => "<?php \$__template->endError(); ?>",
      'debug'        => "<?php if(env('APP_DEBUG')): ?>",
      'enddebug'     => "<?php endif; ?>",
      'cache'        => "<?php if(!\$__template->startCache($args)): ?>",
      'endcache'     => "<?php endif; \$__template->endCache(); ?>",
      'lazy'         => "<?php echo \$__template->lazyLoad($args); ?>",
      'php'          => "<?php ",
      'endphp'       => " ?>",
      'dd'           => "<?php dd($args); ?>",
      'checked'      => "<?php echo ({$args}) ? 'checked' : ''; ?>",
      'selected'     => "<?php echo ({$args}) ? 'selected' : ''; ?>",
      'disabled'     => "<?php echo ({$args}) ? 'disabled' : ''; ?>",
      'required'     => "<?php echo ({$args}) ? 'required' : ''; ?>",
      'continue'     => $this->compileFlowControl('continue', $args),
      'break'        => $this->compileFlowControl('break', $args),
      'paginate'     => $this->compilePaginate($args),
      default        => "@$directive" . ($args !== '' ? "($args)" : '')
    };
  }


  /**
   * Compiles flow control statements (e.g., continue, break).
   *
   * @param string $type The type of flow control ("continue" or "break").
   * @param string $args The condition for the flow control.
   * @return string The compiled PHP code.
   */
  protected function compileFlowControl(string $type, string $args): string
  {
    if (empty(trim($args))) {
      return "<?php {$type}; ?>";
    }
    return "<?php if ({$args}) { {$type}; } ?>";
  }


  /**
   * Converts a collection to an array.
   *
   * If the given $collection is already an array, it is returned unchanged.
   * If it's an instance of Traversable, it will be converted via iterator_to_array.
   * Otherwise, returns an empty array.
   *
   * @param mixed $collection
   * @return array
   */
  public static function toArray($collection): array
  {
    if (is_array($collection)) {
      return $collection;
    }
    if ($collection instanceof \Traversable) {
      return iterator_to_array($collection);
    }
    return [];
  }

  /**
   * Compiles the "each" directive into a PHP foreach block with loop variables.
   *
   * @param string $expression The raw expression inside @each(...).
   * @return string The compiled PHP code.
   */
  protected function compileForEach(string $expression): string
  {
    // Expect expression like "$posts as $post"
    if (preg_match('/^\s*(.+?)\s+as\s+(\$[\w]+)/', $expression, $matches)) {
      $collection = trim($matches[1]); // e.g. $posts
      $itemVar = trim($matches[2]);     // e.g. $post
      // Build PHP code that:
      // 1. Stores the collection in a temporary variable.
      // 2. Calculates its count.
      // 3. Iterates over the collection.
      // 4. Creates a $loop object with useful properties.
      $compiled = "<?php \$__temp_collection = {$collection}; ";
      $compiled .= "\$__loop_count = is_array(\$__temp_collection) ? count(\$__temp_collection) : 0; ";
      $compiled .= "foreach(\$__temp_collection as \$key => {$itemVar}): ";
      $compiled .= "\$loop = new stdClass(); ";
      $compiled .= "\$loop->index = \$key; ";
      $compiled .= "\$loop->iteration = \$key + 1; ";
      $compiled .= "\$loop->count = \$__loop_count; ";
      $compiled .= "\$loop->first = \$key === 0; ";
      $compiled .= "\$loop->last = (\$key + 1) === \$__loop_count; ?>";
      return $compiled;
    }
    // Fallback in case the expression does not match expected pattern.
    return "<?php foreach({$expression}): ?>";
  }



  /**
   * Compiles the "each" directive into a PHP foreach block with output buffering.
   *
   * Expected arguments: "collection, as, empty"
   *
   * @param string $args The raw arguments.
   * @return string The compiled PHP code for the each block.
   */
  /**
   * Compiles the "each" directive into a PHP foreach block with output buffering.
   *
   * Supports two syntaxes:
   *   1) @each($collection, $as, $empty) – comma separated.
   *   2) @each($collection as $as) – "as" syntax; $empty defaults to an empty string.
   *
   * @param string $args The raw arguments.
   * @return string The compiled PHP code for the each block.
   */
  protected function compileEach(string $args): string
  {
    // Determine the syntax used.
    if (stripos($args, ' as ') !== false) {
      // "as" syntax.
      list($collection, $as) = explode(' as ', $args, 2);
      $collection = trim($collection);
      $as = trim($as);
      $empty = '';
    } else {
      // Comma-separated syntax.
      $parts = array_map('trim', explode(',', $args));
      $collection = $parts[0] ?? '';
      $as = $parts[1] ?? 'item';
      $empty = $parts[2] ?? '';
    }
    // Remove leading '$' if present from both collection and alias.
    if (substr($collection, 0, 1) === '$') {
      $collection = substr($collection, 1);
    }
    if (substr($as, 0, 1) === '$') {
      $as = substr($as, 1);
    }
    $this->eachCount++;
    $varName = '__eachEmpty_' . $this->eachCount;
    $this->eachVarStack[] = $varName;

    return "<?php \${$varName} = " . var_export($empty, true) . "; "
      . "\$__collection = \\Pocketframe\\TemplateEngine\\TemplateCompiler::toArray(\$__template->data[" . var_export($collection, true) . "]); "
      . "\$__loop_count = count(\$__collection); "
      . "if(\$__loop_count > 0): foreach(\$__collection as \$key => \$$as): ob_start(); "
      . "\$loop = new stdClass(); "
      . "\$loop->index = \$key; "
      . "\$loop->iteration = \$key + 1; "
      . "\$loop->count = \$__loop_count; "
      . "\$loop->first = (\$key === 0); "
      . "\$loop->last = ((\$key + 1) === \$__loop_count); ?>";
  }



  /**
   * Compiles the "endeach" directive to close the foreach block started by "each".
   *
   * @return string The compiled PHP code for ending the each block.
   */
  protected function compileEndEach(): string
  {
    $varName = array_pop($this->eachVarStack);
    return "<?php echo ob_get_clean(); endforeach; else: echo \${$varName}; endif; ?>";
  }

  /**
   * Compiles echo statements (e.g., {{ }}, {{! }}, {{js }}).
   *
   * @param string $content The template content to compile.
   * @return string The compiled PHP code.
   */
  protected function compileEchos(string $content): string
  {
    $content = preg_replace_callback('/{{!\s*(.+?)\s*}}/s', function ($matches) {
      return "<?php echo {$matches[1]}; ?>";
    }, $content);

    $content = preg_replace_callback('/{{\s*(.+?)\s*}}/s', function ($matches) {
      return "<?php echo htmlspecialchars({$matches[1]} ?? '', ENT_QUOTES, 'UTF-8'); ?>";
    }, $content);

    $content = preg_replace_callback('/{{js\s*(.+?)}}/s', function ($matches) {
      return "<?php echo json_encode({$matches[1]}, JSON_HEX_TAG); ?>";
    }, $content);

    $content = preg_replace_callback(
      '/<script>\s*@{\s*(.*?)\s*}\s*<\/script>/s',
      function ($matches) {
        return $this->compileHydration($matches[1]);
      },
      $content
    );

    return $content;
  }

  /**
   * Compiles the "paginate" directive.
   *
   * @param string $args The arguments for the paginate directive.
   * @return string The compiled PHP code.
   *
   * Usage
   *
   * @paginate($items)            // full page links
   * @paginate($items, 'cursor')// only Prev/Nex
   */
  protected function compilePaginate(string $args): string
  {
    // split into at most two parts: [ dataset, mode? ]
    $parts = array_map('trim', explode(',', $args, 2));

    $dataset = $parts[0];

    // if the second arg is exactly the literal 'cursor', switch mode
    $mode = $parts[1] ?? '';
    $method = $mode === "'cursor'" || $mode === '"cursor"'
      ? 'renderCursor'
      : 'renderPages';

    // we DON'T pass a framework here, so each method will
    // read config('pagination.framework') internally.
    return "<?php echo {$dataset}->{$method}(); ?>";
  }

  /**
   * Compiles JavaScript hydration blocks.
   *
   * @param string $options The hydration options.
   * @return string The compiled PHP code.
   */
  protected function compileHydration(string $options): string
  {
    $params = [];
    parse_str(str_replace(', ', '&', $options), $params);
    $json = $params['json'] ?? '';
    $hydrate = $params['hydrate'] ?? 'data';
    return "<?php echo \$__template->hydrate('$hydrate', $json); ?>";
  }

  /**
   * Compiles template comments (e.g., {#  #}).
   *
   * @param string $content The template content to compile.
   * @return string The compiled PHP code.
   */
  protected function compileComments(string $content): string
  {
    return preg_replace('/{# (.+?) #}/s', '<?php /* $1 */ ?>', $content);
  }

  /**
   * Processes template inheritance (e.g., @layout, @block, @insert).
   *
   * @param string $content The template content to process.
   * @return string The processed content.
   * @throws Exception If the parent template is not found.
   */
  protected function processTemplateInheritance(string $content): string
  {
    if (preg_match('/@layout\(\s*[\'"](.+?)[\'"]\s*\)/', $content, $match)) {
      $parentTemplate = str_replace('.', '/', $match[1]);
      $content = str_replace($match[0], '', $content);

      preg_match_all('/@block\(\s*[\'"](.+?)[\'"]\s*\)(.*?)@endblock/s', $content, $childBlockMatches);
      $childBlocks = array_combine($childBlockMatches[1], $childBlockMatches[2]);

      $parentPath = base_path("resources/views/{$parentTemplate}.view.php");
      if (!file_exists($parentPath)) {
        throw new Exception("Parent template not found: {$parentPath}");
      }
      $parentContent = file_get_contents($parentPath);

      foreach ($childBlocks as $name => $block) {
        $parentContent = preg_replace(
          '/@insert\(\s*[\'"]' . preg_quote($name, '/') . '[\'"]\s*\)/',
          $block,
          $parentContent
        );
      }

      return $parentContent;
    }

    return $content;
  }

  /**
   * Minifies the compiled template output.
   *
   * This method removes extra whitespace between HTML tags while leaving PHP code intact.
   *
   * @param string $content The compiled template content.
   * @return string The minified template content.
   */
  protected function minify(string $content): string
  {
    // Split the content on PHP tags.
    $parts = preg_split('/(<\?php.*?\?>)/s', $content, -1, PREG_SPLIT_DELIM_CAPTURE);

    foreach ($parts as $i => $part) {
      // Only minify non-PHP parts.
      if (strpos($part, '<?php') === false) {
        // Remove extra whitespace between HTML tags.
        $parts[$i] = preg_replace('/>\s+</', '><', $part);
        // Optionally, trim leading/trailing whitespace.
        $parts[$i] = trim($parts[$i]);
      }
    }

    return implode('', $parts);
  }

  /**
   * Compiles the "component" block (with slot) in PHP code
   *
   * Expect a syntax like
   * @component('button', ['class' => 'bg-green-500'])
   *  Button text here
   * @endcomponent
   *
   * The content between @component and @endcomponent is passed as a slot to the component.
   *
   * @param string $expression The raw expression inside @component(...)
   * @param string $content The content of the component (slot)
   * @return string The compiled component code.
   */
  protected function compileComponentBlock(string $expression, string $content): string
  {
    $expression = trim($expression, '()');
    $parts = explode(',', $expression, 2);
    $component = str_replace('.', '/', trim($parts[0], " '\""));
    $props = isset($parts[1]) ? trim($parts[1]) : '[]';
    $unique = '__slot_' . uniqid();
    $compiled = "<?php \$__props = $props; extract(\$__props); \$slot = <<<{$unique}\n" . $content . "\n{$unique};\n";
    $compiled .= "include base_path('resources/views/components/" . strtolower($component) . ".view.php'); ?>";
    return $compiled;
  }



  /**
   * Compiles the "include" directive into PHP code.
   *
   * Expects a syntax like:
   *   @include('header', ['title' => 'Home'])
   *
   * @param string $expression The raw expression inside @include(...).
   * @return string The compiled PHP code.
   */
  protected function compileInclude(string $expression): string
  {
    $expression = trim($expression, '()');
    $parts = explode(',', $expression, 2);
    $partial = str_replace('.', '/', trim($parts[0], " '\""));
    $props = isset($parts[1]) ? trim($parts[1]) : '[]';
    $path = base_path("resources/views/{$partial}.view.php");
    if (!file_exists($path)) {
      throw new Exception("View file not found: {$path}");
    }
    return "<?php \$__props = $props; extract(\$__props); include '{$path}'; ?>";
  }

  /**
   * Retrieves the content of a block by name.
   *
   * @param string $name The name of the block.
   * @return string The content of the block, or an empty string if not found.
   */
  public function getBlock(string $name): string
  {
    return $this->blocks[$name] ?? '';
  }


  /**
   * Compiles an XML-like component tag (the <x-...> syntax) into PHP code.
   *
   * Expects a syntax like:
   *   <x-button class="bg-green-500">Submit</x-button>
   *
   * Attributes are parsed and passed as props, and the inner content is used as the slot.
   *
   * @param array $matches The regex matches:
   *        [1] component name, [2] attribute string, [3] inner content.
   * @return string The compiled PHP code.
   */
 protected function compileXComponent(array $matches): string
{
    $component = ucfirst(str_replace('-', '', $matches[1]));
    $attributes = $matches[2];
    $slotContent = $matches[3];

    // Parse attributes
    preg_match_all('/(\w+)=["\']([^"\']*)["\']/', $attributes, $attrMatches);
    $props = array_combine($attrMatches[1], $attrMatches[2]);

    // Generate unique ID for slots
    $slotId = uniqid('slot_');

    // Compile component
    $compiled = "<?php \n";
    $compiled .= "// Start component props extraction\n";
    $compiled .= "\$__props = ".var_export($props, true).";\n";
    $compiled .= "extract(\$__props);\n";
    $compiled .= "\$__attributes = new AttributeBag(\$__props);\n";

    // Process slots
    $compiled .= "ob_start();\n";
    $compiled .= "?>$slotContent<?php\n";
    $compiled .= "\$__slotContent = ob_get_clean();\n";
    $compiled .= "preg_match_all('/<x-slot\\s+name=\"([\\w-]+)\"[^>]*>(.*?)<\\/x-slot>/s', \$__slotContent, \$__slotMatches);\n";
    $compiled .= "foreach (\$__slotMatches[1] as \$__index => \$__name) {\n";
    $compiled .= "    \$__template->slots[\$__name] = \$__slotMatches[2][\$__index];\n";
    $compiled .= "}\n";

    // Handle component class or inline template
    $compiled .= "if (class_exists(\$__componentClass = \"App\\\\View\\\\Components\\\\{$component}\")) {\n";
    $compiled .= "    \$__component = new \$__componentClass(...\$__props);\n";
    $compiled .= "    include base_path(\$__component->render().'.view.php');\n";
    $compiled .= "} else {\n";
    $compiled .= "    include base_path('resources/views/components/".strtolower($component).".view.php');\n";
    $compiled .= "}\n";

    return $compiled;
}

  /**
   * Adds data to the template for rendering.
   *
   * @param array $data The data to add.
   * @return self
   */
  public function with(array $data): self
  {
    $this->data = array_merge($this->data, $data);
    return $this;
  }

  protected function compileSlots(string $content): string
{
    return preg_replace_callback(
        '/<x-slot\s+name="([\w-]+)"[^>]*>(.*?)<\/x-slot>/s',
        function ($matches) {
            $name = $matches[1];
            $content = $matches[2];
            return "<?php \$__template->startSlot('{$name}'); ?>{$content}<?php \$__template->endSlot(); ?>";
        },
        $content
    );
}

  public function startSlot(string $name): void
{
    ob_start();
    $this->slotStack[] = $name;
}

public function endSlot(): void
{
    $name = array_pop($this->slotStack);
    $this->slots[$name] = ob_get_clean();
}

public function getSlot(string $name): string
{
    return $this->slots[$name] ?? '';
}

  /**
   * Starts a block section.
   *
   * @param string $name The name of the block.
   */
  protected function startBlock(string $name): void
  {
    ob_start();
    $this->blocks[$name] = null;
    array_push($this->stacks, $name);
  }

  /**
   * Ends a block section.
   */
  protected function endBlock(): void
  {
    $name = array_pop($this->stacks);
    $this->blocks[$name] = ob_get_clean();
  }

  /**
   * Inserts the content of a block.
   *
   * @param string $name The name of the block.
   * @return string The block content.
   */
  protected function insert(string $name): string
  {
    return $this->blocks[$name] ?? '';
  }

  /**
   * Starts a reusable component.
   *
   * @param string $name The name of the component.
   */
  protected function startComponent(string $name): void
  {
    ob_start();
    array_push($this->stacks, $name);
  }


  /**
   * Ends a reusable component.
   *
   * @return void
   */
  protected function endComponent(): void
  {
    $name = array_pop($this->stacks);
    $this->components[$name] = ob_get_clean();
  }

  /**
   * Sets the validation errors.
   *
   * @param array $errors The validation errors.
   * @return self
   */
  public function setErrors(array $errors): self
  {
    $this->errors = $errors;
    return $this;
  }

  /**
   * Starts an error block.
   *
   * @param string $field The field associated with the error.
   */
  protected function startError(string $field): void
  {
    ob_start();
    $this->errorStack[] = $field;
  }

  /**
   * Ends an error block.
   */
  protected function endError(): void
  {
    $field = array_pop($this->errorStack);
    $error = ob_get_clean();
    echo isset($this->errors[$field]) ? "<div class=\"error\">$error</div>" : '';
  }

  /**
   * Starts a cache block.
   *
   * @param string $key The cache key.
   * @param int $minutes The cache duration in minutes.
   * @return bool Whether the cache was hit.
   */
  protected function startCache(string $key, int $minutes = 60): bool
  {
    if ($content = Cache::get($key)) {
      echo $content;
      return true;
    }
    ob_start();
    $this->cacheStack[] = compact('key', 'minutes');
    return false;
  }

  /**
   * Ends a cache block.
   */
  protected function endCache(): void
  {
    $params = array_pop($this->cacheStack);
    $content = ob_get_clean();
    Cache::put($params['key'], $content, $params['minutes']);
    echo $content;
  }

  /**
   * Hydrates JavaScript data.
   *
   * @param string $var The JavaScript variable name.
   * @param mixed $data The data to hydrate.
   * @return string The hydration script.
   */
  protected function hydrate(string $var, $data): string
  {
    $json = json_encode($data, JSON_HEX_TAG);
    return "<script>window.{$var} = {$json};</script>";
  }

  /**
   * Generates a lazy loading container.
   *
   * @param string $url The URL to lazy load.
   * @return string The lazy loading HTML.
   */
  protected function lazyLoad(string $url): string
  {
    return "<div data-lazy-container data-src=\"{$url}\"></div>";
  }

  /**
   * Generates a CSRF token input.
   *
   * Exposed as a static helper for use in compiled templates.
   *
   * @return string The CSRF token HTML.
   */
  public static function csrfHelper(): string
  {
    return csrf_token();
  }

  /**
   * Generates a hidden method input field for form spoofing.
   *
   * Exposed as a static helper for use in compiled templates.
   *
   * @param string $method The HTTP method (e.g. "DELETE", "PUT").
   * @return string The hidden method input HTML.
   */
  public static function methodHelper(string $method): string
  {
    return method($method);
  }

  /**
   * Sets the layout for the template.
   *
   * @param string $template The layout template name.
   */
  protected function layout(string $template): void
  {
    $this->sublayoutStack[] = $this->templatePath;
    $this->templatePath = base_path("resources/views/{$template}.view.php");
    ob_start();
  }

  /**
   * Sets a sublayout for the template.
   *
   * @param string $template The sublayout template name.
   */
  protected function sublayout(string $template): void
  {
    $this->layout($template);
  }

  /**
   * Embeds a sub-template.
   *
   * @param string $template The sub-template name.
   * @param array $data Additional data for the sub-template.
   */
  protected function embed(string $template, array $data = []): void
  {
    extract(array_merge($this->data, $data));
    include base_path("resources/views/{$template}.view.php");
  }

  /**
   * Parses directive arguments.
   *
   * Only trims whitespace so that parentheses remain intact.
   *
   * @param string $args The raw arguments.
   * @return string The cleaned arguments.
   */
  private function parseArguments(string $args): string
  {
    return trim($args);
  }

  /**
   * Generates a cache-friendly view name.
   *
   * @param string $viewPath The view path.
   * @return string The hashed view name.
   */
  private function cacheViewName(string $viewPath): string
  {
    return md5($viewPath) . '.php';
  }

  /**
   * Gets the latest modification time of files.
   *
   * @param array $files The files to check.
   * @return int The latest modification time.
   */
  private function getLatestModificationTime(array $files): int
  {
    $latest = 0;
    foreach ($files as $file) {
      if (file_exists($file)) {
        $latest = max($latest, filemtime($file));
      }
    }
    return $latest;
  }

  /**
   * Gets the path to the compiled template.
   *
   * @return string The compiled template path.
   */
  public function getCompiledPath(): string
  {
    return $this->compiledPath;
  }
}
