<?php

namespace Pocketframe\Essentials;

use Parsedown;

final class Markdown
{
  protected static ?Parsedown $parser = null;

  public static function parse(string $text, bool $inline = false): string
  {
    if (!self::$parser) {
      self::$parser = new Parsedown();
      self::$parser->setSafeMode(true);
    }
    return $inline ? self::$parser->line($text) : self::$parser->text($text);
  }
}
