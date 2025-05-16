<?php

namespace Pocketframe\PocketORM\Essentials;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * This class represents a dataset of records.
 * It's an implementation of the IteratorAggregate interface,
 * which allows you to loop through the records using a foreach loop.
 * You can also use the getIterator() method to get an array iterator.
 *
 * @template TEntity of \Pocketframe\PocketORM\Entity\Entity
 * @implements IteratorAggregate<int, TEntity>
 * @implements ArrayAccess<int, TEntity>
 */
class DataSet implements IteratorAggregate, Countable, ArrayAccess
{
  /**
   * The records in the dataset.
   *
   * @var array<int, Entity>
   */
  private array $records = [];


  public function __construct(array $records = [])
  {
    $this->records = $records;
  }

  /**
   * Convert the dataset to an array.
   *
   * @return array
   */
  public function toArray(): array
  {
    return array_map(function ($record) {
      return is_object($record) ? get_object_vars($record) : $record;
    }, $this->records);
  }

  /**
   * Static factory method for building a new DataSet instance.
   *
   * @param array $records
   * @return self
   */
  public static function for(array $records): self
  {
    return new self($records);
  }

  /**
   * Get all records.
   *
   * @return array
   */
  public function all(): array
  {
    return $this->records;
  }

  /**
   * Get the first record.
   *
   * @return ?object
   */
  public function first(): ?object
  {
    $record = $this->records[0] ?? null;
    return $record ? (object)$record : null;
  }

  /**
   * Get the last record.
   *
   * @return ?object
   */
  public function last(): ?object
  {
    $record = $this->records[count($this->records) - 1] ?? null;
    return $record ? (object)$record : null;
  }

  /**
   * Map the dataset to a new dataset.
   *
   * The callback function takes an Entity as its argument.
   *
   * @param callable $callback
   * @return self
   */
  public function map(callable $callback): self
  {
    return new self(array_map($callback, $this->records));
  }

  /**
   * Filter the dataset.
   *
   * The callback function takes an Entity as its argument.
   * Return true to keep the record, false to exclude it.
   *
   * @param callable $callback
   * @return self
   */
  public function filter(callable $callback): self
  {
    return new self(array_filter($this->records, $callback));
  }

  /**
   * Reduce the dataset to a single value.
   *
   * The callback function takes two arguments:
   * - The accumulated value
   * - The current record
   *
   * @param callable $callback
   * @param mixed $initial
   * @return mixed
   */
  public function reduce(callable $callback, $initial)
  {
    return array_reduce($this->records, $callback, $initial);
  }

  /**
   * Join a column of this dataset into a string.
   *
   * @param string $glue   What to insert between values
   * @param string $column The name of the column to join
   * @return string
   */
  public function join(string $glue, string $column): string
  {
    return implode($glue, $this->pluck($column));
  }

  /**
   * Group the dataset by a key.
   *
   * The callback function takes an Entity as its argument.
   * The return value of the callback will be used as the key
   * for the grouped records.
   *
   * @example
   * $dataSet->groupBy(function ($record) {
   *   return $record->category;
   * })
   *
   * @param callable $callback
   * @return array
   */
  public function groupBy(callable $callback): array
  {
    $groups = [];
    foreach ($this->records as $record) {
      $key = $callback($record);
      $groups[$key][] = $record;
    }
    return $groups;
  }

  /**
   * Partition the dataset into two datasets based on a callback.
   *
   * The callback function takes an Entity as its argument.
   * Return true to include the record in the first dataset,
   * false to include it in the second dataset.
   *
   * @example
   * $dataSet->partition(function ($record) {
   *   return $record->status === 'active';
   * })
   *
   * @param callable $callback
   * @return array
   */
  public function partition(callable $callback): array
  {
    $truthy = [];
    $falsy = [];
    foreach ($this->records as $record) {
      if ($callback($record)) {
        $truthy[] = $record;
      } else {
        $falsy[] = $record;
      }
    }
    return [new self($truthy), new self($falsy)];
  }


  /**
   * Pluck a property from the dataset.
   *
   * @param string $column
   * @return array
   */
  public function pluck(string $column): array
  {
    return $this->getColumn($column);
  }

  /**
   * Sort the dataset.
   *
   * The callback function takes two arguments:
   * - The first record
   * - The second record
   *
   * @param callable $callback
   * @return self
   */
  public function sort(callable $callback): self
  {
    $items = $this->records;
    usort($items, $callback);
    return new self($items);
  }

  /**
   * Check if a record exists at a specific offset.
   *
   * @param int $offset
   * @return bool
   */
  public function offsetExists($offset): bool
  {
    return array_key_exists($offset, $this->records);
  }

  /**
   * Get a record at a specific offset.
   *
   * @param int $offset
   * @return mixed
   */
  public function offsetGet($offset): mixed
  {
    return $this->records[$offset] ?? null;
  }

  /**
   * Set a record at a specific offset.
   *
   * @param int $offset
   * @param mixed $value
   * @return void
   */
  public function offsetSet($offset, $value): void
  {
    if ($offset === null) {
      $this->records[] = $value;
    } else {
      $this->records[$offset] = $value;
    }
  }

  public function offsetUnset($offset): void
  {
    unset($this->records[$offset]);
  }


  public function getColumn(string $column): array
  {
    return array_map(function ($item) use ($column) {
      return is_object($item) ? $item->{$column} : $item[$column];
    }, $this->records);
  }

  /**
   * Get the count of records.
   *
   * @return int
   */
  public function count(): int
  {
    return count($this->records);
  }

  /**
   * Get the sum of a column.
   *
   * @param string $column
   * @return int
   */
  public function sum(string $column): int
  {
    return array_sum($this->getColumn($column));
  }

  /**
   * Get an iterator for the dataset.
   *
   * This method will return an iterator that can be used to loop through the
   * dataset using a foreach loop. The iterator will return each record as an
   * object, and the properties of the object will be the column names of the
   * dataset.
   *
   * @return ArrayIterator
   */
  public function getIterator(): Traversable
  {
    return new ArrayIterator($this->records);
  }

  /**
   * Create a fake dataset.
   *
   * This method is used to create a fake dataset for testing purposes. The
   * dataset created by this method is a fake dataset, meaning it is not related
   * to the actual data in the database. The dataset is created by using the
   * provided entity class and the provided data. The data provided should be an
   * array of objects, where each object is an instance of the entity class. If
   * the data provided is not an array of objects, or if the objects in the
   * array are not instances of the entity class, an exception will be thrown.
   *
   * Example:
   *
   * $data = [
   *     new BlogPost(1, 'title 1', 'content 1'),
   *     new BlogPost(2, 'title 2', 'content 2'),
   *     new BlogPost(3, 'title 3', 'content 3'),
   * ];
   *
   * $dataset = DataSet::fake(BlogPost::class, $data);
   *
   *  $dataset is now a fake dataset with 3 records
   *
   * @param string $entityClass
   * @param array $data
   * @return self
   */
  public static function fake(string $entityClass, array $data): self
  {
    foreach ($data as $item) {
      if (!$item instanceof $entityClass) {
        throw new \InvalidArgumentException("All items must be instances of $entityClass");
      }
    }
    return new self($data);
  }
}
