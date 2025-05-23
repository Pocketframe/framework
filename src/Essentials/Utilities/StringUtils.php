<?php

namespace Pocketframe\Essentials\Utilities;

use Carbon\Carbon;
use Pocketframe\Essentials\Fluent\Stringable;
use Pocketframe\Essentials\Markdown;

class StringUtils
{
  /**
   * Return the class name of a given string or object.
   *
   * @param string|object $class The class to get the name from
   * @return string The name of the class
   */
  public static function classBasename(string|object $class): string
  {
    $className = is_object($class) ? get_class($class) : $class;
    return basename(str_replace('\\', '/', $className));
  }

  /**
   * Return a string representation of the current time.
   *
   * @return string The current time in the format 'Y-m-d H:i:s'
   */
  public static function now(): string
  {
    return Carbon::now()->toDateTimeString();
  }

  /**
   * Convert a word to its plural form.
   *
   * @param string $word The singular word to pluralize
   * @return string The plural form of the word
   */
  public static function plural($word)
  {
    // First check if the word is already plural
    if (self::isPlural($word)) {
      return $word;
    }

    // Irregular plurals
    $irregulars = [
      'child' => 'children',
      'person' => 'people',
      'man' => 'men',
      'woman' => 'women',
      'tooth' => 'teeth',
      'foot' => 'feet',
      'goose' => 'geese',
      'mouse' => 'mice',
      'ox' => 'oxen',
    ];

    // Check for irregular plurals first
    if (array_key_exists(strtolower($word), $irregulars)) {
      return $irregulars[strtolower($word)];
    }

    // Rules for regular pluralization
    $rules = [
      // Words ending in 'y' preceded by a consonant
      '/(.*[^aeiou])y$/i' => '$1ies',

      // Words ending in 'us'
      '/us$/i' => 'i',

      // Words ending in 'is'
      '/is$/i' => 'es',

      // Words ending in 'on'
      '/on$/i' => 'a',

      // Words ending in 'f' or 'fe'
      '/(.*)(f|fe)$/i' => '$1ves',

      // Words ending in 'o'
      '/(.*[^aeiou])o$/i' => '$1oes',

      // Words ending in 's', 'ss', 'sh', 'ch', 'x', 'z'
      '/([sxz]|[cs]h)$/i' => '$1es',
    ];

    // Apply the first matching rule
    foreach ($rules as $pattern => $replacement) {
      if (preg_match($pattern, $word)) {
        return preg_replace($pattern, $replacement, $word);
      }
    }

    // Fallback
    return $word . 's';
  }

  /**
   * Check if a word is already in plural form
   *
   * @param string $word The word to check
   * @return bool True if the word is plural
   */
  private static function isPlural($word)
  {
    // Common plural endings
    $pluralEndings = [
      's',
      'es',
      'ies',
      'i',    // for words like 'cacti'
      'a',    // for words like 'phenomena'
      'en'    // for words like 'oxen'
    ];

    $word = strtolower($word);

    // Check irregular plurals that are already plural
    $alreadyPlurals = [
      'children',
      'people',
      'men',
      'women',
      'teeth',
      'feet',
      'geese',
      'mice'
    ];

    if (in_array($word, $alreadyPlurals)) {
      return true;
    }

    // Check common plural endings
    foreach ($pluralEndings as $ending) {
      if (substr($word, -strlen($ending)) === $ending) {
        // Special case: words ending in 'ss' are not plural
        if ($ending === 's' && substr($word, -2) === 'ss') {
          continue;
        }
        return true;
      }
    }

    return false;
  }

  /**
   * Truncate a string to a specified length.
   *
   * @param string $value
   * @param int $limit
   * @param string $end
   * @return string
   *
   * @example StringUtils::truncate('Hello, world!', 5) -> 'Hello...'
   */
  public static function truncate(string $value, int $limit, string $end = '...'): string
  {
    if (mb_strlen($value) <= $limit) return $value;
    return rtrim(mb_substr($value, 0, $limit, 'UTF-8')) . $end;
  }


  /**
   * Get the part of a string after the first occurrence of a given value.
   *
   * @param string $subject
   * @param string $search
   * @return string
   *
   * @example StringUtils::after('App\Controllers', '\\') -> 'Controllers'
   */
  public static function after(string $subject, string $search): string
  {
    return $search === '' ? $subject : array_reverse(explode($search, $subject, 2))[0];
  }

  /**
   * Get the part of a string after the last occurrence of a given value.
   *
   * @param string $subject
   * @param string $search
   * @return string
   *
   * @example StringUtils::afterLast('App\Controllers', '\\') -> 'Controllers'
   */
  public static function afterLast(string $subject, string $search): string
  {
    return $search === '' ? $subject : array_reverse(explode($search, $subject))[0];
  }

  /**
   * Get the part of a string before the first occurrence of a given value.
   *
   * @param string $subject
   * @param string $search
   * @return string
   *
   * @example StringUtils::before('App\Controllers', '\\') -> 'App'
   */
  public static function before(string $subject, string $search): string
  {
    return $search === '' ? $subject : explode($search, $subject)[0];
  }


  /**
   * Get the part of a string before the last occurrence of a given value.
   *
   * @param string $subject
   * @param string $search
   * @return string
   *
   * @example StringUtils::beforeLast('App\Controllers', '\\') -> 'App'
   */
  public static function beforeLast(string $subject, string $search): string
  {
    if ($search === '') {
      return $subject;
    }

    $segments = explode($search, $subject);

    if (count($segments) === 1) {
      return $subject;
    }

    array_pop($segments);

    return implode($search, $segments);
  }

  /**
   * Convert value to camelCase format.
   *
   * @param string $value
   * @return string
   *
   * @example StringUtils::camelCase('hello_world') -> 'helloWorld'
   */
  public static function camelCase(string $value): string
  {
    return lcfirst(static::pascalCase($value));
  }


  /**
   * Convert a string to its singular form.
   *
   * @param string $string
   * @return string
   *
   * @example StringUtils::singular('categories') -> 'category'
   */
  public static function singular(string $string): string
  {
    $pluralPatterns = [
      '/(s|ss|os|es|ies)$/',
      '/(ves)$/',
    ];

    $singularReplacements = [
      '',
      '',
    ];

    foreach ($pluralPatterns as $index => $pattern) {
      if (preg_match($pattern, $string)) {
        $string = preg_replace($pattern, $singularReplacements[$index], $string);
        break;
      }
    }

    return $string;
  }



  /**
   * Determine if a string contains a given substring (case-sensitive).
   *
   * @param string $haystack
   * @param string|array $needles
   * @return bool
   *
   * @example StringUtils::contains('Hello, world!', 'world') -> true
   */
  public static function contains(string $haystack, $needles): bool
  {
    foreach ((array) $needles as $needle) {
      if ($needle !== '' && str_contains($haystack, $needle)) {
        return true;
      }
    }
    return false;
  }

  /**
   * Remove duplicate occurrences of a character/pattern.
   *
   * @param string $value
   * @param string $character
   * @return string
   *
   * @example StringUtils::deduplicate('Hello, world!') -> 'Hello, world!'
   */
  public static function deduplicate(string $value, string $character = ' '): string
  {
    return preg_replace('/' . preg_quote($character, '/') . '{2,}/', $character, $value);
  }

  /**
   * Convert string to ASCII format.
   *
   * Transliteration is used to convert characters to their closest ASCII equivalent.
   * Non-ASCII characters are removed.
   *
   * @param string $string
   * @param string $language
   * @return string
   *
   * @example StringUtils::toAscii('Hello, world!') -> 'Hello world'
   */
  public static function toAscii(string $string, string $language = 'en'): string
  {
    $string = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
    $string = preg_replace("/[^a-zA-Z0-9\s]/", '', $string);
    return $string;
  }

  /**
   * Generate a URL-friendly slug from the given string.
   *
   * @param string $title
   * @param string $separator
   * @param string $language
   * @return string
   *
   * @example StringUtils::slugify('Hello, world!') -> 'hello-world'
   */
  public static function slugify(string $title, string $separator = '-', string $language = 'en'): string
  {
    // Convert to ASCII
    $title = static::toAscii($title, $language);

    // Remove characters that aren't letters, numbers, or whitespace
    $title = preg_replace('![^' . preg_quote($separator) . '\pL\pN\s]+!u', '', mb_strtolower($title));

    // Replace whitespace and repeated separators
    return trim(preg_replace('/[' . preg_quote($separator) . '\s]+/u', $separator, $title), $separator);
  }

  /**
   * Convert string to snake_case format.
   *
   * @param string $value
   * @param string $delimiter
   * @return string
   *
   * @example StringUtils::snakeCase('Hello, world!') -> 'hello_world'
   */
  public static function snakeCase(string $value, string $delimiter = '_'): string
  {
    if (!ctype_lower($value)) {
      $value = preg_replace('/\s+/u', '', $value);
      $value = mb_strtolower(preg_replace('/(.)(?=[A-Z])/u', '$1' . $delimiter, $value));
    }
    return $value;
  }

  /**
   * Remove all extra whitespace from a string.
   *
   * @param string $value
   * @return string
   *
   * @example StringUtils::trim('  Hello, world!  ') -> 'Hello, world!'
   */
  public static function trim(string $value): string
  {
    return preg_replace('~(\s|\x{3164})+~u', ' ', preg_replace('~^[\s\x{FEFF}]+|[\s\x{FEFF}]+$~u', '', $value));
  }

  /**
   * Convert string to StudlyCase format.
   *
   * @param string $value
   * @return string
   *
   * @example StringUtils::pascalCase('hello_world') -> 'HelloWorld'
   */
  public static function pascalCase(string $value): string
  {
    $value = ucwords(str_replace(['-', '_'], ' ', $value));
    return str_replace(' ', '', $value);
  }

  /**
   * Limit the number of words in a string.
   *
   * @param string $value
   * @param int $words
   * @param string $end
   * @return string
   *
   * @example StringUtils::limitWords('Hello, world!', 1) -> 'Hello...'
   */
  public static function limitWords(string $value, int $words = 100, string $end = '...'): string
  {
    preg_match('/^\s*+(?:\S++\s*+){1,' . $words . '}/u', $value, $matches);
    if (!isset($matches[0]) || mb_strlen($value) === mb_strlen($matches[0])) {
      return $value;
    }
    return rtrim($matches[0]) . $end;
  }

  /**
   * Mask part of a string with a repeated character.
   *
   * Mask part of a string with a repeated character, usually used to hide sensitive
   * information like passwords or credit card numbers. The mask will be applied
   * from the start of the string up to the $visibleStart index and from the
   * $visibleEnd index to the end of the string. The characters in the middle
   * will be replaced with the $maskCharacter.
   *
   * @param string $value
   * @param string $maskCharacter
   * @param int $visibleStart
   * @param int $visibleEnd
   * @return string
   *
   * @example StringUtils::mask('1234567890', '*', 3, 3) -> '123*****7890'
   */
  public static function mask(
    string $value,
    string $maskCharacter = '*',
    int $visibleStart = 3,
    int $visibleEnd = 3
  ): string {
    $length = mb_strlen($value);
    $visible = $visibleStart + $visibleEnd;

    if ($length <= $visible) return $value;

    return mb_substr($value, 0, $visibleStart)
      . str_repeat($maskCharacter, $length - $visible)
      . mb_substr($value, -$visibleEnd);
  }

  /**
   * Convert Markdown to HTML.
   *
   * Convert Markdown text to HTML. If $inline is true, the text will be parsed
   * as inline Markdown (no wrappers).
   *
   * @param string $text
   * @param bool $inline  If true, parse as inline Markdown (no wrappers).
   * @return string
   *
   * @example StringUtils::markdown('# Hello, world!') -> '<h1>Hello, world!</h1>'
   */
  public static function markdown(string $text, bool $inline = false): string
  {
    return Markdown::parse($text, $inline);
  }

  /**
   * Reverse a string.
   *
   * Reverse the order of characters in a string.
   *
   * @param string $value
   * @return string
   *
   * @example StringUtils::reverse('Hello, world!') -> '!dlrow ,olleH'
   */
  public static function reverse(string $value): string
  {
    return implode('', array_reverse(mb_str_split($value)));
  }

  /**
   * Generate a random string.
   *
   * @param int $length
   * @return string
   *
   * @example StringUtils::random(10) -> '5e89f2a1b3'
   */
  public static function random(int $length = 16): string
  {
    return bin2hex(random_bytes($length / 2));
  }

  /**
   * Generate a UUID.
   *
   * Generate a version 4 UUID (randomly generated).
   *
   * @return string
   *
   * @example StringUtils::uuid() -> '123e4567-e89b-12d3-a456-426655440000'
   */
  public static function uuid(): string
  {
    return sprintf(
      '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
      mt_rand(0, 0xffff),
      mt_rand(0, 0xffff),
      mt_rand(0, 0xffff),
      mt_rand(0, 0x0fff) | 0x4000,
      mt_rand(0, 0x3fff) | 0x8000,
      mt_rand(0, 0xffff),
      mt_rand(0, 0xffff),
      mt_rand(0, 0xffff)
    );
  }

  /**
   * Create a Stringable instance.
   *
   * This method creates a new instance of the Stringable class from a given string.
   * The Stringable class is a simple wrapper around a string that provides an
   * interface with all the methods of the StringUtils class. This allows you to
   * chain methods together and make your code a bit more readable. The Stringable
   * class is also immutable, meaning that once you create an instance, you can't
   * change it anymore. This can be useful when you want to make sure that a string
   * is not modified by accident. For example, you can use it to create a slug from
   * a title before storing it in a database:
   *
   * $slug = StringUtils::create($title)->slugify()->lowercase();
   *
   * @param string $value
   * @return Stringable
   *
   * @example StringUtils::create('Hello, world!')->reverse() -> '!dlrow ,olleH'
   */
  public static function create(string $value): Stringable
  {
    return new Stringable($value);
  }
}
