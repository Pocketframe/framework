<?php

namespace Pocketframe\Middleware;

use Closure;
use Pocketframe\Http\Request\Request;
use Pocketframe\Sessions\Session;

class SessionMiddleware
{
  public function handle(Request $request, Closure $next)
  {
    // Start session if not already started
    Session::start();

    // Generate CSRF token if not exists
    if (!isset($_SESSION['csrf_token'])) {
      $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // Attach session to request
    $request->setSession($_SESSION);

    // Handle next middleware
    $response = $next($request);

    // Save session changes
    $_SESSION = $request->session()->all();

    // Clear old input and errors after the request
    Session::remove(['_old', 'errors']);

    return $response;
  }
}
