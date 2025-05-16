<?php

namespace Pocketframe\TemplateEngine;

class AttributeBag
{
  protected array $attributes = [];

  public function __construct(array $initial = [])
  {
    $this->attributes = $initial;
  }

  /**
   * Merge arbitrary attributes.
   * Strings/arrays under 'class' are handled via ->class().
   */
  public function merge(array $attrs): static
  {
    foreach ($attrs as $key => $value) {
      if ($key === 'class') {
        // allow merge(['class'=>['p-4','text-lg']]) or ['bg-red'=>$hasError]
        $this->class(is_array($value) ? $value : [$value => true]);
      } else {
        $this->attributes[$key] = $value;
      }
    }
    return $this;
  }

  /**
   * Merge classes, supports ['p-4', 'bg-red' => $hasError].
   */
  public function class(array $classes): static
  {
    $existing = isset($this->attributes['class'])
      ? preg_split('/\s+/', trim($this->attributes['class']))
      : [];

    foreach ($classes as $key => $value) {
      if (is_int($key) && $value) {
        $existing[] = $value;
      } elseif ($value) {
        $existing[] = $key;
      }
    }

    // dedupe
    $this->attributes['class'] = implode(' ', array_unique($existing));
    return $this;
  }

  public function __toString(): string
  {
    $html = [];
    foreach ($this->attributes as $k => $v) {
      $html[] = sprintf('%s="%s"', $k, htmlspecialchars($v, ENT_QUOTES));
    }
    return $html ? ' ' . implode(' ', $html) : '';
  }
}
