<?php

namespace SzentirasHu\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use SzentirasHu\Service\Editor\EditorService;
use SzentirasHu\Service\Ai\CommentaryService;
use Symfony\Component\HttpFoundation\Response;

class CheckCommentaryGeneration
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        protected EditorService $editorService,
        protected CommentaryService $commentaryService
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

        // Check daily token usage limit
        $maxTokenPerDay = config('ai.configurations.commentary.max_token_per_day', 0);
        $usedTokens = $this->commentaryService->sumTokenUsageForDay();
        if ($usedTokens >= $maxTokenPerDay) {
            return response()->json([
                'success' => false,
                'message' => sprintf(
                    'Daily token usage limit (%d tokens) already exceeded (%d tokens used).',
                    $maxTokenPerDay,
                    $usedTokens
                )
            ], 429); // 429 Too Many Requests
        }

        // Token validation is already done by FillAnonymousIdFromCookie middleware,
        // but we can optionally verify it exists in database.
        // For simplicity, we assume token is valid if session has it.

        return $next($request);
    }
}