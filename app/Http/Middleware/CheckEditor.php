<?php

namespace SzentirasHu\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use SzentirasHu\Service\Editor\EditorService;
use Symfony\Component\HttpFoundation\Response;

class CheckEditor
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        protected EditorService $editorService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->editorService->currentIsEditor()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Editor privileges required.'
            ], 403);
        }

        return $next($request);
    }
}