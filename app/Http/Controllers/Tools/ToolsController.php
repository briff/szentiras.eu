<?php

namespace SzentirasHu\Http\Controllers\Tools;

use SzentirasHu\Http\Controllers\Controller;

/**
 * Controller for Tools index page
 */
class ToolsController extends Controller
{
    /**
     * Display the tools index page
     */
    public function index()
    {
        return \View::make("tools/index", [
            'pageTitle' => 'Eszközök - Szentírás.eu',
            'metaTitle' => 'Eszközök - Szentírás.eu'
        ]);
    }
}
