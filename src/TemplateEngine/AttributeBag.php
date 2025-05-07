<?php
namespace Pocketframe\TemplateEngine;

use Pocketframe\Http\Response\HtmlString;

class AttributeBag
{
    protected array $attributes;

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public function merge(array $new): HtmlString
    {
        $attrs = array_merge($this->attributes, $new);
        // simple helper to escape
        $escaped = array_map(fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'), $attrs);

        $string = collect($escaped)
            ->map(fn($value, $key) => $key.'="'.$value.'"')
            ->implode(' ');

        return new HtmlString($string);
    }
}
