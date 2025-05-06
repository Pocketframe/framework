<?php

use Pocketframe\Storage\Storage;

beforeEach(function () {
    $this->storage = new Storage('local');
    $this->path = 'sample/test.txt';

    // Clean before each test
    $fullPath = $this->storage->path($this->path);
    if (file_exists($fullPath)) {
        unlink($fullPath);
    }
});

test('it stores file contents', function () {
    $result = $this->storage->put($this->path, 'Hello Pocketframe');
    expect($result)->toBeTrue();

    $storedPath = $this->storage->path($this->path);
    expect(file_exists($storedPath))->toBeTrue();
    expect(file_get_contents($storedPath))->toBe('Hello Pocketframe');
});

test('it retrieves file contents', function () {
    file_put_contents($this->storage->path($this->path), 'Read this');
    $contents = $this->storage->get($this->path);

    expect($contents)->toBe('Read this');
});

test('it deletes a file', function () {
    $this->storage->put($this->path, 'To be deleted');
    expect($this->storage->exists($this->path))->toBeTrue();

    $result = $this->storage->delete($this->path);
    expect($result)->toBeTrue();
    expect($this->storage->exists($this->path))->toBeFalse();
});

test('it checks file existence', function () {
    expect($this->storage->exists($this->path))->toBeFalse();
    $this->storage->put($this->path, 'Exists now');
    expect($this->storage->exists($this->path))->toBeTrue();
});

test('it resolves full path correctly', function () {
    $expected = base_path('store/app/sample/test.txt');
    $resolved = $this->storage->path($this->path);

    expect($resolved)->toBe($expected);
});

test('it returns a public URL if available', function () {
    $public = new Storage('public');
    $url = $public->url('example.txt');

    expect($url)->toBe('http://localhost/store/app/example.txt');
});

test('linkPublic creates symbolic link', function () {
    Storage::linkPublic();
    expect(is_link(base_path('public/store')) || is_dir(base_path('public/store')))->toBeTrue();
});


test('it can create and delete a directory', function () {
    $path = 'test-dir';
    $created = $this->storage->makeDirectory($path);
    expect($created)->toBeTrue();
    expect(is_dir($this->storage->path($path)))->toBeTrue();

    $deleted = $this->storage->deleteDirectory($path);
    expect($deleted)->toBeTrue();
    expect(is_dir($this->storage->path($path)))->toBeFalse();
});

test('it can list directories inside a path', function () {
    $this->storage->makeDirectory('dir-list/a');
    $this->storage->makeDirectory('dir-list/b');

    $dirs = $this->storage->directories('dir-list');
    expect($dirs)->toContain('dir-list/a', 'dir-list/b');
});

test('it can list files inside a directory', function () {
    $this->storage->makeDirectory('file-list');
    $this->storage->put('file-list/one.txt', 'First');
    $this->storage->put('file-list/two.txt', 'Second');

    $files = $this->storage->files('file-list');
    expect($files)->toContain('file-list/one.txt', 'file-list/two.txt');
});
