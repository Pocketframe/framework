<?php

namespace Pocketframe\Essentials\Utilities;

use ArrayAccess;

class ArrayUtils
{
  /**
   * Check if value is array accessible.
   *
   * @param mixed $value
   * @return bool
   */
  public static function isAccessible($value): bool
  {
    return is_array($value) || $value instanceof ArrayAccess;
  }

  /**
   * Add element to array if it doesn't exist.
   *
   * @param array $array
   * @param string $key
   * @param mixed $value
   * @return array
   */
  public static function add(array $array, string $key, $value): array
  {
    if (!isset($array[$key])) {
      $array[$key] = $value;
    }
    return $array;
  }

  /**
   * Flatten multi-dimensional array to single level.
   *
   * @param iterable $array
   * @param int $depth
   * @return array
   */
  public static function flatten(iterable $array, int $depth = INF): array
  {
    $result = [];
    foreach ($array as $item) {
      if (is_array($item)) {
        $result = array_merge(
          $result,
          $depth === 1 ? $item : self::flatten($item, $depth - 1)
        );
      } else {
        $result[] = $item;
      }
    }
    return $result;
  }

  /**
   * Get subset of array using dot notation.
   *
   * @param array $array
   * @param string|array $keys
   * @return array
   */
  public static function only(array $array, $keys): array
  {
    return array_intersect_key($array, array_flip((array)$keys));
  }

  /**
   * Remove elements using dot notation.
   *
   * @param array $array
   * @param string|array $keys
   */
  public static function forget(array &$array, $keys): void
  {
    foreach ((array)$keys as $key) {
      $parts = explode('.', $key);
      while (count($parts) > 1) {
        $part = array_shift($parts);
        if (isset($array[$part])) {
          $array = &$array[$part];
        }
      }
      unset($array[array_shift($parts)]);
    }
  }

  /**
   * Get value using dot notation.
   *
   * @param array $array
   * @param string $key
   * @param mixed $default
   * @return mixed
   */
  public static function get(array $array, string $key, $default = null)
  {
    foreach (explode('.', $key) as $segment) {
      if (!is_array($array)) return $default;
      $array = $array[$segment] ?? $default;
    }
    return $array;
  }

  /**
   * Check if array is associative.
   *
   * @param array $array
   * @return bool
   */
  public static function isAssociative(array $array): bool
  {
    return array_keys($array) !== range(0, count($array) - 1);
  }

  /**
   * Convert array to CSS class string.
   *
   * @param array $classes
   * @return string
   */
  public static function toCssClasses(array $classes): string
  {
    return implode(' ', array_filter(array_keys($classes), function ($class) use ($classes) {
      return (bool)$classes[$class];
    }));
  }

  /**
   * Convert array to query string.
   *
   * @param array $params
   * @return string
   */
  public static function toQueryString(array $params): string
  {
    return http_build_query($params, '', '&', PHP_QUERY_RFC1738);
  }

  /**
   * Filter null values from array.
   *
   * @param array $array
   * @return array
   */
  public static function whereNotNull(array $array): array
  {
    return array_filter($array, fn($value) => !is_null($value));
  }

  // ... Other methods with similar implementations ...

  /**
   * Data helper: Get nested value using dot notation.
   *
   * @param mixed $target
   * @param string|array $key
   * @param mixed $default
   * @return mixed
   */
  public static function getData($target, $key, $default = null)
  {
    if (is_null($key)) return $target;

    foreach (explode('.', $key) as $segment) {
      if (is_array($target) && array_key_exists($segment, $target)) {
        $target = $target[$segment];
      } elseif ($target instanceof ArrayAccess && $target->offsetExists($segment)) {
        $target = $target[$segment];
      } else {
        return $default;
      }
    }
    return $target;
  }

  /**
   * Data helper: Set nested value using dot notation.
   *
   * @param mixed $target
   * @param string $key
   * @param mixed $value
   * @return mixed
   */
  public static function setData(&$target, string $key, $value)
  {
    $segments = explode('.', $key);
    $current = &$target;

    foreach ($segments as $segment) {
      if (!is_array($current)) {
        $current = [];
      }
      $current = &$current[$segment];
    }

    $current = $value;
    return $target;
  }
}
