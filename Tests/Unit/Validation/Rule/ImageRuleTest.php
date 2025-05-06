<?php

use Pocketframe\Validation\Rules\ImageRule;

beforeEach(function () {
    $this->rule = new ImageRule();
});

it('passes when value is a valid image', function () {
    expect($this->rule->isValid(['name' => 'file.jpg', 'type' => 'image/jpeg']))->toBeTrue();
    expect($this->rule->isValid(['name' => 'file.png', 'type' => 'image/jpeg']))->toBeTrue();
    expect($this->rule->isValid(['name' => 'file.gif', 'type' => 'image/jpeg']))->toBeTrue();
});

it('fails when value is not a valid image', function () {
    expect($this->rule->isValid('some image'))->toBeFalse();
    expect($this->rule->isValid('123456789.jpg'))->toBeFalse();
    expect($this->rule->isValid(''))->toBeFalse();
});

it('returns correct error message', function () {
    expect($this->rule->message('image'))->toBe('The image must be a valid image.');
});
