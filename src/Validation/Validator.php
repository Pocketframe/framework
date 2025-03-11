<?php

declare(strict_types=1);

namespace Pocketframe\Validation;

use Pocketframe\Contracts\Rule;
use Pocketframe\Validation\Rules\EmailRule;
use Pocketframe\Validation\Rules\NumericRule;
use Pocketframe\Validation\Rules\RequiredRule;
use Pocketframe\Validation\Rules\StringRule;

class Validator
{
  // Holds the final error messages.
  private array $errors = [];
  // Contains detailed errors (field, rule name, default message).
  private array $errorDetails = [];
  // Custom messages provided via the message() method.
  private array $customMessages = [];

  /**
   * Validate the provided data against the rules.
   *
   * @param array $data
   * @param array $rules An associative array: e.g. 'title' => ['required', 'string']
   * @return self
   */
  public function validate(array $data, array $rules): self
  {
    $this->errorDetails = [];

    // Mapping of string rule names to their corresponding classes.
    $ruleMapping = [
      'required' => RequiredRule::class,
      'string'   => StringRule::class,
      'numeric'  => NumericRule::class,
      'email'    => EmailRule::class,
    ];

    foreach ($rules as $field => $ruleSet) {
      // For each field, run validations in order and stop on the first failure.
      foreach ($ruleSet as $rule) {
        // If the rule is provided as a string, instantiate it.
        if (is_string($rule)) {
          if (isset($ruleMapping[$rule])) {
            $rule = new $ruleMapping[$rule]();
          } else {
            throw new \Exception("No rule mapping defined for: {$rule}");
          }
        }

        // Now, $rule is an instance of Rule.
        if (!$rule->isValid($data[$field] ?? '')) {
          // Get the short class name, remove "Rule" suffix if it exists, then lowercase.
          $shortName = (new \ReflectionClass($rule))->getShortName();
          $ruleName = strtolower(preg_replace('/rule$/i', '', $shortName));

          $this->errorDetails[$field] = [
            'field'   => $field,
            'rule'    => $ruleName,
            'default' => $rule->message($field)
          ];
          // Stop further validations on this field after the first failure.
          break;
        }
      }
    }

    // Finalize error messages, using any custom messages if provided.
    $this->finalizeErrors();

    // Store old input in session.
    $_SESSION['old'] = $data;

    return $this;
  }

  /**
   * Set custom messages for validation errors.
   * Only keys defined in the array will override the default messages.
   * Keys should be in the format: field.rule (e.g. 'title.required').
   *
   * @param array $messages
   * @return self
   */
  public function message(array $messages): self
  {
    $this->customMessages = $messages;
    // Re-run the finalization so custom messages override defaults when available.
    $this->finalizeErrors();
    return $this;
  }

  /**
   * Merge the error details with custom messages (if provided)
   * so that only rules with custom messages are overridden.
   */
  private function finalizeErrors(): void
  {
    $this->errors = [];
    foreach ($this->errorDetails as $detail) {
      // Construct the key used to check for a custom message.
      $key = "{$detail['field']}.{$detail['rule']}";
      // If a custom message exists for this rule, use it; otherwise, fall back to the default.
      $message = $this->customMessages[$key] ?? $detail['default'];
      // Replace :attribute placeholder.
      $message = str_replace(':attribute', ucfirst($detail['field']), $message);
      $this->errors[$detail['field']][] = $message;
    }
  }

  /**
   * If there are any errors, store them in session and redirect.
   *
   * @return bool
   */
  public function failed(): bool
  {
    if (!empty($this->errors)) {
      $_SESSION['errors'] = $this->errors;
      header("Location: " . $_SERVER['HTTP_REFERER']);
      exit;
    }
    return false;
  }
}
