<?php

use Pocketframe\Validation\Rules\FileRule;

beforeEach(function () {
    $this->rule = new FileRule();
});

it('passes when value is a valid file', function () {
    expect($this->rule->isValid(['name' => 'file.txt', 'error' => UPLOAD_ERR_OK]))->toBeTrue();
    expect($this->rule->isValid(['name' => 'file.jpg', 'error' => UPLOAD_ERR_OK]))->toBeTrue();
    expect($this->rule->isValid(['name' => 'file.png', 'error' => UPLOAD_ERR_OK]))->toBeTrue();
    expect($this->rule->isValid(['name' => 'file.pdf', 'error' => UPLOAD_ERR_OK]))->toBeTrue();
});

it('fails when value is not a valid file', function () {
    expect($this->rule->isValid('some file'))->toBeFalse();
    expect($this->rule->isValid('123456789'))->toBeFalse();
    expect($this->rule->isValid('@#$%^&*()'))->toBeFalse();
});

it('returns correct error message', function () {
    expect($this->rule->message('file'))->toBe('The file must be a valid file.');
});
