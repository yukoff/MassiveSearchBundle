<?php

namespace Massive\Bundle\SearchBundle\Search\Event;

use Symfony\Component\EventDispatcher\Event;

class PreIndexEvent extends Event
{
    protected $indexName;
    protected $document;

    function __construct($document, $indexName)
    {
        $this->indexName = $indexName;
        $this->document = $document;
    }

    public function getIndexName()
    {
        return $this->indexName;
    }

    public function getDocument()
    {
        return $this->document;
    }
}
