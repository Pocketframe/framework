<?php
// src/Middleware/SessionMiddleware.php
namespace Pocketframe\Middleware;

use Closure;
use Pocketframe\Http\Request\Request;
use Pocketframe\Sessions\Mask\Session;

class SessionMiddleware
{
  public function handle(Request $request, Closure $next)
  {
    // Ensure session is started
    Session::start();

    // CSRF token generation
    if (!Session::has('csrf_token')) {
      Session::put('csrf_token', bin2hex(random_bytes(32)));
    }

    // Attach Symfony session to Request
    $request->setSession(Session::all());

    $response = $next($request);

    // Save not needed: Symfony session writes automatically on shutdown

    // Clear old input & flash if desired
    Session::sweep();

    return $response;
  }
}
