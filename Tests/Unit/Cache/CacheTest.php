<?php

use Pocketframe\Cache\Mask\Cache;

beforeEach(function () {
    Cache::flush();
});

test('it can store and retrieve cached data', function () {
    Cache::put('foo', 'bar', 1);

    expect(Cache::get('foo'))->toBe('bar');
});

test('it returns null for expired cache', function () {
    Cache::put('expired', 'bye', 0);

    sleep(1);

    expect(Cache::get('expired'))->toBeNull();
});

test('it can check if a cache key exists and is valid', function () {
    Cache::put('valid_key', 'value', 1);

    expect(Cache::has('valid_key'))->toBeTrue();
    expect(Cache::has('invalid_key'))->toBeFalse();
});

test('it forgets a cache item', function () {
    Cache::put('temp', 'data', 1);
    Cache::forget('temp');

    expect(Cache::get('temp'))->toBeNull();
    expect(Cache::has('temp'))->toBeFalse();
});

test('it flushes all cached items', function () {
    Cache::put('a', '1', 1);
    Cache::put('b', '2', 1);
    Cache::flush();

    expect(Cache::get('a'))->toBeNull();
    expect(Cache::get('b'))->toBeNull();
});

