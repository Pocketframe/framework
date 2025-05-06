<?php

use Pocketframe\Validation\Rules\DateRule;


beforeEach(function () {
    $this->rule = new DateRule();
});

it('passes when value is a valid date', function () {
    expect($this->rule->isValid('2023-01-01'))->toBeTrue();
    expect($this->rule->isValid('2023-01-01 00:00:00'))->toBeTrue();
    expect($this->rule->isValid('2023-01-01 00:00:00.000000'))->toBeTrue();
});

it('fails when value is not a valid date', function () {
    expect($this->rule->isValid('some date'))->toBeFalse();
    expect($this->rule->isValid('123456789'))->toBeFalse();
    expect($this->rule->isValid('@#$%^&*()'))->toBeFalse();
});

it('returns correct error message', function () {
    expect($this->rule->message('date'))->toBe('The date must be a valid date.');
});
