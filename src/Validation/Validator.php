<?php

declare(strict_types=1);

namespace Pocketframe\Validation;

class Validator
{
  private array $errors = [];

  public function validate(array $data, array $rules): self
  {
    $this->errors = [];

    foreach ($rules as $field => $ruleSet) {
      foreach ($ruleSet as $rule) {
        if (!$rule->isValid($data[$field] ?? '')) {
          $this->errors[$field][] = $rule->message();
        }
      }
    }

    $_SESSION['errors'] = $this->errors;
    return $this;
  }

  public function failed(): bool
  {
    if (!empty($this->errors)) {
      header("Location: " . $_SERVER['HTTP_REFERER']);
      exit;
    }
    return false;
  }
}
