<?php
use SzentirasHu\Lib\Search\Searcher;

/**

 */
class SearcherStub implements Searcher
{

    public function get()
    {
        return false;
    }

    public function getExcerpts($verses)
    {
        return [];
    }
}