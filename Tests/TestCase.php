<?php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Tests\Traits\RunFreshDatabase;

abstract class TestCase extends BaseTestCase
{
    use RunFreshDatabase;
}
