<?php

namespace Pocketframe\Middleware;

use Closure;
use Pocketframe\Database\DB;
use Pocketframe\Http\Request\Request;
use Pocketframe\Http\Response\Response;

class ApiAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Get API token from header
        $token = $request->header('Authorization') ?? '';
        $token = str_replace('Bearer ', '', $token);

        if (!$this->isValidToken($token)) {
            return Response::json([
                'error' => 'Unauthorized',
                'message' => 'Invalid API token'
            ], Response::UNAUTHORIZED);
        }

        return $next($request);
    }

    protected function isValidToken(string $token): bool
    {
        // Validate token against database
        $result = DB::table('api_tokens')
            ->where('token', hash('sha256', $token))
            ->andWhere('expires_at', '>', date('Y-m-d H:i:s'))
            ->first();

        return !empty($result);


        // Create table if not exists
        //     CREATE TABLE api_tokens (
        // id INT PRIMARY KEY AUTO_INCREMENT,
        // token VARCHAR(64) NOT NULL, -- Store hashed tokens
        // user_id INT NOT NULL,
        // expires_at DATETIME NOT NULL,
        // created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        // );
    }
}
