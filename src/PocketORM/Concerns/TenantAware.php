<?php

namespace Pocketframe\PocketORM\Concerns;

use Pocketframe\Sessions\Mask\Session;

trait TenantAware
{
  /**
   * Get the current tenant ID for this entity.
   *
   * @return string|null
   */
  public static function getTenantId(): string
  {
    if (config('tenant.enabled') === false) {
      throw new \Exception('Tenant feature is not enabled');
    }

    if (Session::has(config('tenant.tenant_column'))) {
      return Session::get(config('tenant.tenant_column'));
    }
    throw new \Exception("Tenant ID not found. Try checking your session or database to ensure the tenant ID exists.");
  }

  /**
   * Get the tenant column name for this entity.
   * Override in your entity if you use a different column.
   *
   * @return string
   */
  public static function tenantColumn(): string
  {
    return config('tenant.tenant_column');
  }
}
