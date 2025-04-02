<?php

declare(strict_types=1);

namespace Pocketframe\Validation;

use Pocketframe\Contracts\Rule;
use Pocketframe\Validation\Rules\{
  ArrayRule,
  DateRule,
  EmailRule,
  FileRule,
  ImageRule,
  InRule,
  LowercaseRule,
  MaxRule,
  MinRule,
  NullableRule,
  NumericRule,
  RequiredRule,
  SometimesRule,
  StringRule,
  UppercaseRule
};

class Validator
{
  private array $errors = [];
  private array $errorDetails = [];
  private array $customMessages = [];

  private const RULE_MAPPING = [
    'required'  => RequiredRule::class,
    'string'    => StringRule::class,
    'numeric'   => NumericRule::class,
    'email'     => EmailRule::class,
    'min'       => MinRule::class,
    'max'       => MaxRule::class,
    'date'      => DateRule::class,
    'image'     => ImageRule::class,
    'nullable'  => NullableRule::class,
    'lowercase' => LowercaseRule::class,
    'uppercase' => UppercaseRule::class,
    'sometimes' => SometimesRule::class,
    'file'      => FileRule::class,
    'in'        => InRule::class,
    'array'     => ArrayRule::class,
  ];

  public function validate(array $data, array $rules): self
  {
    $this->errorDetails = [];

    foreach ($rules as $field => $ruleSet) {
      // Handle wildcard array validation (tags.*)
      if (str_contains($field, '.*')) {
        $baseField = str_replace('.*', '', $field);
        $this->validateArrayElements($data, $baseField, $ruleSet);
        continue;
      }

      $value = $data[$field] ?? null;

      // Skip validation if field is nullable and value is null/empty
      if (in_array('nullable', $ruleSet, true) && ($value === null || $value === '')) {
        continue;
      }

      // Skip validation if field is sometimes and not present
      if (in_array('sometimes', $ruleSet, true) && !array_key_exists($field, $data)) {
        continue;
      }

      foreach ($ruleSet as $rule) {
        if ($rule === 'nullable' || $rule === 'sometimes') {
          continue;
        }

        $ruleInstance = $this->resolveRule($rule);
        if (!$ruleInstance->isValid($value)) {
          $this->recordError($field, $ruleInstance);
          break;
        }
      }
    }

    $this->finalizeErrors();
    $_SESSION['old'] = $data;

    return $this;
  }

  private function validateArrayElements(array $data, string $baseField, array $rules): void
  {
    if (!isset($data[$baseField])) {
      return;
    }

    if (!is_array($data[$baseField])) {
      $this->recordError($baseField, new ArrayRule());
      return;
    }

    foreach ($data[$baseField] as $index => $value) {
      foreach ($rules as $rule) {
        if ($rule === 'nullable' || $rule === 'sometimes') {
          continue;
        }

        $ruleInstance = $this->resolveRule($rule);
        if (!$ruleInstance->isValid($value)) {
          $this->recordError("{$baseField}.{$index}", $ruleInstance);
          break;
        }
      }
    }
  }



  private function resolveRule($rule): Rule
  {
    if ($rule instanceof Rule) {
      return $rule;
    }

    if (!is_string($rule)) {
      throw new \InvalidArgumentException('Rule must be a string or implement Rule interface');
    }

    if (strpos($rule, ':') === false) {
      return $this->createRuleInstance($rule);
    }

    [$ruleName, $paramStr] = explode(':', $rule, 2);
    return $this->createParameterizedRule($ruleName, $paramStr);
  }

  private function createRuleInstance(string $ruleName): Rule
  {
    if (!isset(self::RULE_MAPPING[$ruleName])) {
      throw new \RuntimeException("No rule mapping defined for: {$ruleName}");
    }
    $ruleClass = self::RULE_MAPPING[$ruleName];
    return new $ruleClass();
  }

  private function createParameterizedRule(string $ruleName, string $paramStr): Rule
  {
    if (!isset(self::RULE_MAPPING[$ruleName])) {
      throw new \RuntimeException("No rule mapping defined for: {$ruleName}");
    }

    // Special handling for 'in' rule
    if ($ruleName === 'in') {
      return new InRule($paramStr);
    }

    // Handle other parameterized rules
    $params = explode(',', $paramStr);
    $param = trim($params[0]);

    // Cast to appropriate type
    if (strpos($param, '.') !== false) {
      $param = (float)$param;
    } elseif (is_numeric($param)) {
      $param = (int)$param;
    }

    $ruleClass = self::RULE_MAPPING[$ruleName];
    return new $ruleClass($param);
  }

  private function recordError(string $field, Rule $rule): void
  {
    $shortName = (new \ReflectionClass($rule))->getShortName();
    $ruleName = strtolower(preg_replace('/rule$/i', '', $shortName));

    $this->errorDetails[$field] = [
      'field'   => $field,
      'rule'    => $ruleName,
      'default' => $rule->message($field)
    ];
  }

  public function message(array $messages): self
  {
    $this->customMessages = $messages;
    $this->finalizeErrors();
    return $this;
  }

  private function finalizeErrors(): void
  {
    $this->errors = [];
    foreach ($this->errorDetails as $detail) {
      $key = "{$detail['field']}.{$detail['rule']}";
      $message = $this->customMessages[$key] ?? $detail['default'];
      $message = str_replace(':attribute', ucfirst($detail['field']), $message);
      $this->errors[$detail['field']][] = $message;
    }
  }

  public function failed(): bool
  {
    if (!empty($this->errors)) {
      $_SESSION['errors'] = $this->errors;
      header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '/'));
      exit;
    }
    return false;
  }

  public function errors(): array
  {
    return $this->errors;
  }
}
