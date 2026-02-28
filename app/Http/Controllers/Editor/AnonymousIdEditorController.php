<?php

namespace SzentirasHu\Http\Controllers\Editor;

use SzentirasHu\Http\Controllers\Controller;
use SzentirasHu\Data\Entity\AnonymousId;

class AnonymousIdEditorController extends Controller
{
    /**
     * Display a listing of anonymous IDs.
     */
    public function index()
    {
        $anonymousIds = AnonymousId::query()
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return view('editor.anonymousIds.index', [
            'anonymousIds' => $anonymousIds,
        ]);
    }
}
