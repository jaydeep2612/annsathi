<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRestaurantTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Ensure the authenticated staff member belongs to a restaurant
        if (!$user || !$user->restaurant_id) {
            return response()->json(['message' => 'Unauthorized. No restaurant assigned.'], 403);
        }

        return $next($request);
    }
}