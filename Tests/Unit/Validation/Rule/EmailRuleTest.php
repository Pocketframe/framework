<?php

use Pocketframe\Validation\Rules\EmailRule;

beforeEach(function () {
    $this->rule = new EmailRule();
});

it('passes when value is a valid email', function () {
    expect($this->rule->isValid('test@example.com'))->toBeTrue();
    expect($this->rule->isValid('test.william@example.com'))->toBeTrue();
    expect($this->rule->isValid('test@example.net'))->toBeTrue();
    expect($this->rule->isValid('test@example.framework.net'))->toBeTrue();
    expect($this->rule->isValid('test@example.org'))->toBeTrue();
});

it('fails when value is not a valid email', function () {
    expect($this->rule->isValid('some email'))->toBeFalse();
    expect($this->rule->isValid('123456789'))->toBeFalse();
    expect($this->rule->isValid(''))->toBeFalse();
});

it('returns correct error message', function () {
    expect($this->rule->message('email'))->toBe('The email field must be a valid email address.');
});
