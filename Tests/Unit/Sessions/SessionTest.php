<?php

use Pocketframe\Sessions\Mask\Session;

beforeEach(function () {
    Session::flush();
});

it('can store and retrieve session data', function () {
    Session::put('username', 'william');
    expect(Session::has('username'))->toBeTrue();
    expect(Session::get('username'))->toBe('william');
});

it('returns default if session key is missing', function () {
    expect(Session::get('missing', 'default'))->toBe('default');
});

it('can store and retrieve nested session data with dot notation', function () {
    Session::put('user', ['email' => 'test@example.com']);
    expect(Session::get('user.email'))->toBe('test@example.com');
});

it('can remove session keys', function () {
    Session::put('key1', 'value');
    Session::put('key2', 'value');
    Session::remove(['key1', 'key2']);
    expect(Session::has('key1'))->toBeFalse();
    expect(Session::has('key2'))->toBeFalse();
});

it('can flash data and retrieve it once', function () {
    Session::flash('notice', 'Saved!');
    expect(Session::hasFlash('notice'))->toBeTrue();
    expect(Session::getFlash('notice'))->toBe('Saved!');
    expect(Session::hasFlash('notice'))->toBeFalse(); // Auto removed after retrieval
});

it('returns default if flashed value not set', function () {
    expect(Session::getFlash('not-set', 'fallback'))->toBe('fallback');
});

it('can flash multiple values at once', function () {
    Session::flashAll([
        'msg' => 'Welcome',
        'type' => 'success'
    ]);
    expect(Session::hasFlash('msg'))->toBeTrue();
    expect(Session::getFlash('type'))->toBe('success');
});

it('can store and retrieve old input', function () {
    Session::withOld(['name' => 'Asaba']);
    expect(Session::old('name'))->toBe('Asaba');
    expect(Session::old('name'))->toBeNull();
});

it('can flush all session data', function () {
    Session::put('abc', 123);
    Session::flush();
    expect(Session::has('abc'))->toBeFalse();
});

it('can destroy session completely', function () {
    Session::put('key', 'val');
    Session::destroy();
    expect(Session::has('key'))->toBeFalse();
});
