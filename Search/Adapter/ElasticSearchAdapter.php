<?php
/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Massive\Bundle\SearchBundle\Search\Adapter;

use Massive\Bundle\SearchBundle\Search\AdapterInterface;
use Massive\Bundle\SearchBundle\Search\Document;
use Massive\Bundle\SearchBundle\Search\Factory;
use Massive\Bundle\SearchBundle\Search\Field;
use Massive\Bundle\SearchBundle\Search\SearchQuery;
use Elasticsearch\Client as ElasticSearchClient;

/**
 * ElasticSearch adapter using official client:
 *
 * https://github.com/elasticsearch/elasticsearch-php
 */
class ElasticSearchAdapter implements AdapterInterface
{
    const ID_FIELDNAME = '__id';
    const CLASS_TAG = '__class';

    const URL_FIELDNAME = '__url';
    const TITLE_FIELDNAME = '__title';
    const DESCRIPTION_FIELDNAME = '__description';
    const LOCALE_FIELDNAME = '__locale';
    const IMAGE_URL = '__image_url';

    /**
     * @var \Massive\Bundle\SearchBundle\Search\Factory
     */
    private $factory;

    /**
     * @var \ElasticSearch\Client
     */
    private $client;

    /**
     * @var boolean
     */
    private $indexListLoaded;

    /*
     * @var array
     */
    private $indexList;

    /**
     * @param string $basePath Base filesystem path for the index
     */
    public function __construct(
        Factory $factory,
        ElasticSearchClient $client
    ) {
        $this->factory = $factory;
        $this->client = $client;
    }

    /**
     * {@inheritDoc}
     */
    public function index(Document $document, $indexName)
    {
        $fields = array();
        foreach ($document->getFields() as $massiveField) {
            switch ($massiveField->getType()) {
                case Field::TYPE_STRING:
                    $fields[$massiveField->getName()] = $massiveField->getValue();
                    break;
                default:
                    throw new \InvalidArgumentException(sprintf(
                        'Search field type "%s" is not known. Known types are: %s',
                        $massiveField->getType(), implode(', ', Field::getValidTypes())
                    ));
            }
        }

        $fields[self::URL_FIELDNAME] = $document->getUrl();
        $fields[self::TITLE_FIELDNAME] = $document->getTitle();
        $fields[self::DESCRIPTION_FIELDNAME] = $document->getDescription();
        $fields[self::LOCALE_FIELDNAME] = $document->getLocale();
        $fields[self::CLASS_TAG] = $document->getClass();
        $fields[self::IMAGE_URL] = $document->getImageUrl();

        // ensure that any new index name is listed when calling listIndexes
        $this->indexList[$indexName] = $indexName;

        $params = array(
            'id' => $document->getId(),
            'index' => $indexName,
            'type' => $this->documentToType($document),
            'body' => $fields,
        );

        $this->client->index($params);
    }

    /**
     * {@inheritDoc}
     */
    public function deindex(Document $document, $indexName)
    {
        $params = array(
            'index' => $indexName,
            'type' => $this->documentToType($document),
            'id' => $document->getId(),
            'refresh' => true,
            'ignore' => 404,
        );

        $this->client->delete($params);
    }

    /**
     * {@inheritDoc}
     */
    public function search(SearchQuery $searchQuery)
    {
        $indexNames = $searchQuery->getIndexes();

        $queryString = $searchQuery->getQueryString();

        $params['index'] = implode(',', $indexNames);
        $params['body'] = array(
            'query' => array(
                'query_string' => array(
                    'query' => $queryString,
                ),
            ),
        );

        $res = $this->client->search($params);
        $elasticHits = $res['hits']['hits'];

        $hits = array();

        foreach ($elasticHits as $elasticHit) {
            $hit = $this->factory->createQueryHit();
            $document = $this->factory->createDocument();

            $hit->setDocument($document);
            $hit->setScore($elasticHit['_score']);

            $document->setId($elasticHit['_id']);

            $elasticSource = $elasticHit['_source'];

            if (isset($elasticSource[self::TITLE_FIELDNAME])) {
                $document->setTitle($elasticSource[self::TITLE_FIELDNAME]);
            }
            if (isset($elasticSource[self::DESCRIPTION_FIELDNAME])) {
                $document->setDescription($elasticSource[self::DESCRIPTION_FIELDNAME]);
            }
            if (isset($elasticSource[self::LOCALE_FIELDNAME])) {
                $document->setLocale($elasticSource[self::LOCALE_FIELDNAME]);
            }
            if (isset($elasticSource[self::URL_FIELDNAME])) {
                $document->setUrl($elasticSource[self::URL_FIELDNAME]);
            }
            if (isset($elasticSource[self::CLASS_TAG])) {
                $document->setClass($elasticSource[self::CLASS_TAG]);
            }
            if (isset($elasticSource[self::IMAGE_URL])) {
                $document->setImageUrl($elasticSource[self::IMAGE_URL]);
            }

            $hit->setId($document->getId());

            foreach ($elasticSource as $fieldName => $fieldValue) {
                $document->addField($this->factory->createField($fieldName, $fieldValue));
            }
            $hits[] = $hit;
        }

        return $hits;
    }

    /**
     * {@inheritDoc}
     */
    public function getStatus()
    {
        $indexes = $this->listIndexes();

        $indices = $this->client->indices()->status(array('index' => '_all'));
        $indexes = $indices['indices'];
        $status = array();

        foreach ($indexes as $indexName => $index) {
            foreach ($index as $field => $value) {
                $status['idx:' . $indexName . '.' . $field] = substr(trim(json_encode($value)), 0, 100);
            }
        }

        return $status;
    }

    /**
     * {@inheritDoc}
     */
    public function purge($indexName)
    {
        try {
            $this->client->indices()->delete(array('index' => $indexName));
            $this->indexListLoaded = false;
        } catch (\Elasticsearch\Common\Exceptions\Missing404Exception $e) {
        }
    }

    /**
     * {@inheritDoc}
     */
    public function listIndexes()
    {
        if (!$this->indexListLoaded) {
            $indices = $this->client->indices()->status(array('index' => '_all'));
            $indexes = $indices['indices'];
            $this->indexList = array_combine(
                array_keys($indexes),
                array_keys($indexes)
            );
            $this->indexListLoaded = true;
        }

        return $this->indexList;
    }

    /**
     * {@inheritDoc}
     */
    public function flush(array $indexNames)
    {
        $this->client->indices()->flush(array(
            'index' => implode(', ', $indexNames),
            'full' => true,
        ));
    }

    /**
     * Convert FQCN to a snake-case string to use as an
     * elastic search type.
     *
     * @param Document $document
     *
     * @return string
     */
    private function documentToType(Document $document)
    {
        $class = $document->getClass();

        if (!$class) {
            return 'massive_undefined';
        }

        return substr(str_replace('\\', '_', $class), 1);
    }
}