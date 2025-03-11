<?php

namespace Pocketframe\Middleware;

use Closure;
use Pocketframe\Http\Request\Request;
use Pocketframe\Contracts\MiddlewareInterface;
use Pocketframe\Http\Response\Response;
use Pocketframe\TemplateEngine\View;

class CsrfMiddleware implements MiddlewareInterface
{
  public function handle(Request $request, Closure $next): Response
  {
    // For state-changing requests, validate the token.
    if (in_array($request->method(), ['POST', 'PUT', 'DELETE'])) {
      $token = $request->post('_token');

      if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        $content = View::renderFile(
          __DIR__ . '/../resources/views/errors/' . Response::PAGE_EXPIRED . '.view.php'
        );
        return new Response($content, Response::PAGE_EXPIRED, ['Content-Type' => 'text/html']);
      }

      // Instead of rotating the token immediately which could lead to a "page expired"
      // error on form resubmission we set a flag to rotate the token on the next GET request.
      $_SESSION['rotate_csrf_token'] = true;
    }
    // On GET requests, if the flag is set, regenerate the token.
    elseif ($request->method() === 'GET' && isset($_SESSION['rotate_csrf_token'])) {
      $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
      unset($_SESSION['rotate_csrf_token']);
    }

    return $next($request);
  }
}
