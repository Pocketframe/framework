<?php

declare(strict_types=1);

namespace Pocketframe\Console\Commands;

use Pocketframe\Contracts\CommandInterface;
use Pocketframe\Storage\Storage;

class CreateStoreLinkCommand implements CommandInterface
{
  protected array $args;

  public function __construct(array $args)
  {
    $this->args = $args;
  }

  public function handle(): void
  {
    Storage::linkPublic();
  }
}
