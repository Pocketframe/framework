<?php

use Pocketframe\Validation\Rules\ArrayRule;

beforeEach(function () {
    $this->rule = new ArrayRule();
});

it('passes when value is an array', function () {
    expect($this->rule->isValid([]))->toBeTrue();
    expect($this->rule->isValid(['a', 'b']))->toBeTrue();
    expect($this->rule->isValid(['key' => 'value']))->toBeTrue();
});

it('fails when value is not an array', function () {
    expect($this->rule->isValid('string'))->toBeFalse();
    expect($this->rule->isValid(42))->toBeFalse();
    expect($this->rule->isValid(null))->toBeFalse();
    expect($this->rule->isValid(false))->toBeFalse();
    expect($this->rule->isValid(new stdClass()))->toBeFalse();
});

it('returns correct error message', function () {
    expect($this->rule->message('data'))->toBe('The data must be an array.');
});
