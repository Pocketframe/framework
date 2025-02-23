<?php

namespace Pocketframe\Contracts;

interface PocketframeExceptionInterface
{
  public function getErrorType(): string;
}
