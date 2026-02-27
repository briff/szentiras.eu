<?php

namespace SzentirasHu\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use SzentirasHu\Service\Editor\EditorService;
use Symfony\Component\HttpFoundation\Response;

class CheckCommentaryGeneration
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
        // Allow editors unconditionally
        if ($this->editorService->currentIsEditor()) {
            return $next($request);
        }

        // Check if commentary generation is allowed for all logged-in users
        $allUsersAllowed = config('ai.configurations.commentary.all_users_allowed', false);
        if (!$allUsersAllowed) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Editor privileges required.'
            ], 403);
        }

        // Check if user is logged in (has anonymous token)
        $token = Session::get('anonymous_token');
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required. Please log in.'
            ], 401);
        }

        // Token validation is already done by FillAnonymousIdFromCookie middleware,
        // but we can optionally verify it exists in database.
        // For simplicity, we assume token is valid if session has it.

        return $next($request);
    }
}