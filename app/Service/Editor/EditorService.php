<?php

namespace SzentirasHu\Service\Editor;

use Illuminate\Support\Facades\Session;

class EditorService
{
    /**
     * Check if a given token has editor privileges.
     *
     * @param string|null $token The anonymous token to check. If null, uses current session token.
     * @return bool True if the token is in the editor list, false otherwise.
     */
    public function isEditor(?string $token = null): bool
    {
        $token = $token ?? Session::get('anonymous_token');
        
        if (!$token) {
            return false;
        }
        
        $editorTokens = config('editors.tokens', []);
        
        return in_array($token, $editorTokens, true);
    }
    
    /**
     * Check if the current session's anonymous token has editor privileges.
     *
     * @return bool True if current user is an editor, false otherwise.
     */
    public function currentIsEditor(): bool
    {
        return $this->isEditor();
    }
    
    /**
     * Get all editor tokens from configuration.
     *
     * @return array List of editor tokens.
     */
    public function getEditorTokens(): array
    {
        return config('editors.tokens', []);
    }
}