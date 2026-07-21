<?php

use Laravel\Mcp\Facades\Mcp;
use SzentirasHu\Mcp\Servers\BibleServer;

/*
|--------------------------------------------------------------------------
| MCP Servers
|--------------------------------------------------------------------------
|
| This file is loaded by Laravel\Mcp\Server\McpServiceProvider without any
| prefix or middleware group, so the full URI and middleware are declared here.
|
| The optional translation segment lets each user point their MCP client at the
| translation of their own tradition, e.g. https://szentiras.eu/mcp/bible/RUF for
| a Protestant user and .../mcp/bible/SZIT for a Catholic one. The API key travels
| as a ?api_key= query parameter, since MCP clients generally configure only a URL.
|
*/

Mcp::web('mcp/bible/{translation?}', BibleServer::class)
    ->where('translation', '[A-Za-z0-9]+')
    ->middleware(['apiKey', 'throttle:api_key']);

Mcp::local('bible', BibleServer::class);
