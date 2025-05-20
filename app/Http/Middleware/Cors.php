<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class Cors
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowedOrigins = ['*'];
        $origin = $request->headers->get('Origin');
        $response = $next($request);
        if (in_array($origin, $allowedOrigins)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization');

        }
        if ($request->isMethod('OPTIONS')) {
            return response()->json([], 200);
        }
        return $response;
    }
}
