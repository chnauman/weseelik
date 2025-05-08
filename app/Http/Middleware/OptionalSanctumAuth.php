<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\Guard;

class OptionalSanctumAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->bearerToken()) {
            try {
                $user = auth('sanctum')->user();
                if ($user) {
                    auth()->login($user);
                }
            } catch (\Exception $e) {
                // Token is invalid, continue as guest
            }
        }

        return $next($request);
    }
}
