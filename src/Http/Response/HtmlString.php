<?php

namespace Pocketframe\Http\Response;

/**
 * A string that should be rendered as raw HTML.
 */
class HtmlString implements \Stringable
{
    protected string $html;

    /**
     * @param string $html  The HTML content
     */
    public function __construct(string $html)
    {
        $this->html = $html;
    }

    /**
     * When you echo an HtmlString, it just outputs the raw HTML.
     */
    public function __toString(): string
    {
        return $this->html;
    }
}
