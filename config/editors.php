<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Editor Tokens
    |--------------------------------------------------------------------------
    |
    | List of anonymous token strings that have editor privileges.
    | Tokens should be comma-separated in the EDITOR_TOKENS environment variable.
    |
    | Example: EDITOR_TOKENS="abc123,def456,ghi789"
    |
    */
    'tokens' => array_filter(
        array_map('trim', 
            explode(',', env('EDITOR_TOKENS', ''))
        )
    ),
];