<?php

declare(strict_types=1);

namespace Pocketframe\Package;

use Composer\InstalledVersions;
use Pocketframe\Container\Container;
use Pocketframe\Package\Contract\PackageInterface;

class PackageLoader
{
  public static function loadPackages(Container $container): void
  {
    foreach (InstalledVersions::getInstalledPackages() as $packageName) {
      if (!str_starts_with($packageName, 'pocketframe/')) {
        continue;
      }

      $extra = self::readPackageExtra($packageName);
      if (!isset($extra['pocketframe']['packages'])) {
        continue;
      }

      foreach ($extra['pocketframe']['packages'] as $class) {
        if (class_exists($class)) {
          $instance = new $class;
          if ($instance instanceof PackageInterface) {
            $instance->register($container);
          }
        }
      }
    }
  }

  protected static function readPackageExtra(string $packageName): array
  {
    $composerPath = InstalledVersions::getInstallPath($packageName) . '/composer.json';
    if (!file_exists($composerPath)) {
      return [];
    }

    $json = json_decode(file_get_contents($composerPath), true);
    return $json['extra'] ?? [];
  }
}
