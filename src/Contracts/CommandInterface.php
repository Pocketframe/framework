<?php

namespace Pocketframe\Contracts;

interface CommandInterface
{
  public function handle(): void;
}
