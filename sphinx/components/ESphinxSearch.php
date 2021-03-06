<?php

/**
 * ESphinxSearch extension wrapper to communicate with Sphinx full-text search engine
 * For More documentation please see:
 * http://sphinxsearch.com/
 */

/**
 * @class ESphinxSearch
 * @brief Implements Sphinx Search
 * @details Wrapper for sphinx searchd client class
 *
 * "A" - Team:
 * @author     Andrey Evsyukov <thaheless@gmail.com>
 * @author     Alexey Spiridonov <a.spiridonov@2gis.ru>
 * @author     Alexey Papulovskiy <a.papulovskiyv@2gis.ru>
 * @author     Alexander Biryukov <a.biryukov@2gis.ru>
 * @author     Alexander Radionov <alex.radionov@gmail.com>
 * @author     Andrey Trofimenko <a.trofimenko@2gis.ru>
 * @author     Artem Kudzev <a.kiudzev@2gis.ru>
 * @author     iefreer <iefreer@hotmail.com>
 *
 * @link       http://www.2gis.ru
 * @link https://github.com/iefreer/yii-sphinx
 * @copyright  2GIS
 * @license http://www.yiiframework.com/license/
 *
 * Requirements:
 * --------------
 *  - Yii 1.1.x or above
 *  - SphinxClient php library
 *                                ;
 */
if (!class_exists('SphinxClient', false)) {
    include_once(dirname(dirname(__FILE__)) . '/sphinxapi.php');
}
include_once(dirname(__FILE__) . '/ESphinxSearchResult.php');
class ESphinxSearch extends CApplicationComponent
{

    /**
     * @var string
     * @brief sphinx server
     */
    public $server = 'localhost';
    /**
     * @var integer
     * @brief sphinx server port
     */
    public $port = 6712;
    /**
     * @var integer
     * @brief sphinx default match mode
     */
    public $matchMode = SPH_MATCH_EXTENDED;
    /**
     * @var integer
     * @brief sphinx default rank mode
     */
    public $rankMode = SPH_RANK_SPH04;
    /**
     * @var integer
     * @brief sphinx max exec time
     */
    public $maxQueryTime = 3000;
    /**
     * @var array
     * @brief default field weights
     */
    public $fieldWeights = array();
    /**
     * @var boolean
     * @brief enable Yii profiling
     */
    public $enableProfiling = false;
    /**
     * @var boolean
     * @brief enable Yii tracing
     */
    public $enableResultTrace = false;
    /**
     * @var ESphinxCriteria
     * @brief current search criteria
     */
    protected $criteria;
    /**
     * @var ESphinxCriteria
     * @var last used criteria
     */
    protected $lastCriteria;
    /**
     * @var SphinxClient
     * @brief Sphinx client object
     */
    private $client;

    public function init()
    {
        parent::init();
        $this->client = new SphinxClient;
        $this->client->setServer($this->server, $this->port);
        $this->client->setMaxQueryTime($this->maxQueryTime);
        Yii::trace("weigth: " . print_r($this->fieldWeights, true), 'CEXT.ESphinxSearch.doSearch');

        $this->resetCriteria();
    }

    /**
     * @brief connect to searchd server, run given search query through given indexes,
     * and return the search results
     * @details Mapped from SphinxClient directly
     * @param string $query
     * @param string $index
     * @param string $comment
     * @return array
     */
    public function query($query, $index = '*', $comment = '')
    {
        return $this->doSearch($index, $query, $comment);
    }

    /**
     * @brief full text search system query
     * @details send query to full text search system
     * @param ESphinxCriteria criteria
     * @return ESphinxSearchResult
     */
    public function search($criteria = null)
    {
        if ($criteria === null) {
            $res = $this->doSearch($this->criteria->from, $this->criteria->query);
        }
        else {
            $res = $this->searchByCriteria($criteria);
        }
        return $this->initIterator($res, $this->lastCriteria);
    }

    /**
     * @brief full text search system query
     * @details send query to full text search system
     * @param object criteria
     * @return array
     */
    public function searchRaw($criteria = null)
    {
        if ($criteria === null) {
            $res = $this->doSearch($this->criteria->from, $this->criteria->query);
        }
        else {
            $res = $this->searchByCriteria($criteria);
        }
        return $res;
    }

    /**
     * @brief set select-list (attributes or expressions), SQL-like syntax - 'expression'
     * @param string $select
     * @return $this chain
     */
    public function select($select)
    {
        $this->criteria->select = $select;
        $this->client->SetSelect($select);
        return $this;
    }

    /**
     * @brief set index name for search, SQL-like syntax - 'table_reference'
     * @param string $index
     * @return $this chain
     */
    public function from($index)
    {
        $this->criteria->from = $index;
        return $this;
    }

    /**
     * @brief set search query, SQL-like syntax - 'where_condition'
     * @param string $query
     * @return $this chain
     */
    public function where($query)
    {
        $this->criteria->query = $query;
        return $this;
    }

    /**
     * @brief set query filters, SQL-like syntax - 'additional where_condition'
     * @param array $filters
     * @return $this chain
     */
    public function filters($filters)
    {
        $this->criteria->filters = $filters;
        //set filters
        if ($filters && is_array($filters)) {
            foreach ($filters as $fil => $vol) {
                // geo filter
                if ($fil == 'geo') {
                    $min = (float)(isset($vol['min']) ? $vol['min'] : 0);
                    $point = explode(' ', str_replace('POINT(', '', trim($vol['point'], ')')));
                    $this->client->setGeoAnchor('latitude', 'longitude', (float)$point[1] * (pi() / 180), (float)$point[0] * (pi() / 180));
                    $this->client->setFilterFloatRange('@geodist', $min, (float)$vol['buffer']);
                    // usual filter
                }
                else if ($vol) {
                    $this->client->SetFilter($fil, (is_array($vol)) ? $vol : array($vol));
                }
            }
        }
        return $this;
    }

    /**
     * @brief set grouping attribute and function, SQL-like syntax - 'group_by'
     * @param array $groupby
     * @return $this chain
     */
    public function groupby($groupby = null)
    {
        $this->criteria->groupby = $groupby;
        // set groupby
        if ($groupby && is_array($groupby)) {
            $this->client->setGroupBy($groupby['field'], $groupby['mode'], $groupby['order']);
        }
        return $this;
    }

    /**
     * @brief set matches sorting, SQL-like syntax - 'order_by expression'
     * @param array $orders
     * @return $this chain
     */
    public function orderby($orders = null)
    {
        $this->criteria->orders = $orders;        
        if ($orders) {      
            $this->client->SetSortMode(SPH_SORT_EXTENDED, $orders);
        }
        return $this;
    }

    /**
     * @brief set offset and count into result set, SQL-like syntax - 'limit $offset, $count'
     * @param integer $offset
     * @param integer $limit
     * @return $this chain
     */
    public function limit($offset = null, $limit = null)
    {
        if (isset($offset) && isset($limit)) {
            $this->client->setLimits($offset, $limit);
        }
        return $this;
    }

    /**
     * @brief returns errors if any
     */
    public function getLastError()
    {
        return $this->client->getLastError();
    }

    /**
     * @brief reset search criteria to default
     * @details reset conditions and set default search options
     */
    public function resetCriteria()
    {
        if (is_object($this->criteria)) {
            $this->lastCriteria = clone($this->criteria);
        }
        else {
            $this->lastCriteria = new ESphinxCriteria();
        }
        $this->criteria = new ESphinxCriteria();
        $this->criteria->query = '';
        $this->client->resetFilters();
        $this->client->resetGroupBy();
        $this->client->setArrayResult(false);
        $this->client->setMatchMode($this->matchMode);
        $this->client->setRankingMode($this->rankMode);
        $this->client->setSortMode(SPH_SORT_RELEVANCE, '@relevance DESC');
        $this->client->setLimits(0, 1000);
        if (!empty($this->fieldWeights)) {
            $this->client->setFieldWeights($this->fieldWeights);
        }
    }

    /**
     * @brief handle given search criteria. set them to current object
     * @param ESphinxCriteria $criteria
     */
    public function setCriteria($criteria)
    {
        if (!is_object($criteria)) {
            throw new CException('Criteria does not set.');
        }

        // set select expression
        if (isset($criteria->select)) {
            $this->select($criteria->select);
        }
        // set from criteria
        if (isset($criteria->from)) {
            $this->from($criteria->from);
        }
        // set where criteria
        if (isset($criteria->query)) {
            $this->where($criteria->query);
        }
        // set grouping
        if (isset($criteria->groupby)) {
            $this->groupby($criteria->groupby);
        }
        // set filters
        if (isset($criteria->filters)) {
            $this->filters($criteria->filters);
        }
        // set field ordering
        if (isset($criteria->orders) && $criteria->orders) {
            $this->orderby($criteria->orders);
        }
        // set fieldWeights
        if (isset($criteria->fieldWeights)) {
            $this->fieldWeights = $criteria->fieldWeights;
        }
    }

    /**
     * @brief get current search criteria
     * @return object criteria
     */
    public function getCriteria()
    {
        return $this->criteria;
    }

    /**
     * @brief magic for wrap SphinxClient functions
     * @param string $name
     * @param array $parameters
     * @return ESphinxSearch
     */
    public function __call($name, $parameters)
    {
        $res = null;
        if (method_exists($this->client, $name)) {
            $res = call_user_func_array(array($this->client, $name), $parameters);
        }
        else {
            $res = parent::__call($name, $parameters);
        }
        // if setter or resetter then return chain
        if (strtolower(substr($name, 0, 3)) === 'set' || strtolower(substr($name, 0, 5)) === 'reset') {
            $res = $this;
        }
        return $res;
    }

    /**
     * @brief Performs actual query through Sphinx Connector
     * @details Profiles $this->client->query($query, $index);
     * @param string $index
     * @param string $query
     * @param string $comment
     * @return array
     */
    protected function doSearch($index, $query = '', $comment = '')
    {
        if (!$index) {
            throw new CException('Index search criteria invalid');
        }

        if ($this->enableResultTrace) {
            Yii::trace("Query '$query' is performed for index '$index'", 'CEXT.ESphinxSearch.doSearch');
        }

        if ($this->enableProfiling) {
            Yii::beginProfile("Search query: '{$query}' in index: '{$index}'", 'CEXT.ESphinxSearch.doSearch');
        }

        $res = $this->client->query($query, $index, $comment);

        if ($this->getLastError()) {
            throw new CException($this->getLastError());
        }

        if ($this->enableProfiling) {
            Yii::endProfile("Search query: '{$query}' in index: '{$index}'", 'CEXT.ESphinxSearch.doSearch');
        }

        if ($this->enableResultTrace) {
            Yii::trace("Query result: " . substr(print_r($res, true), 500), 'CEXT.ESphinxSearch.doSearch');
        }

        if (!isset($res['matches'])) {
            $res['matches'] = array();
        }
        $this->resetCriteria();
        return $res;
    }

    /**
     * @brief full text search system query by given criteria object
     * @details send query to full text search system
     * @param object criteria
     * @return array
     */
    protected function searchByCriteria($criteria)
    {
        if (!is_object($criteria)) {
            throw new CException('Criteria does not set.');
        }

        // handle given criteria
        $this->setCriteria($criteria);

        // process search
        $res = $this->doSearch($this->criteria->from, $this->criteria->query);

        return $res;
    }

    /**
     * @brief init ESphinxSearchResult interator for search results
     * @param array $data
     * @param ESphinxCriteria $criteria
     * @return ESphinxSearchResult
     */
    protected function initIterator(array $data, $criteria = NULL)
    {
        $iterator = new ESphinxSearchResult($data, $criteria);
        $iterator->enableProfiling = $this->enableProfiling;
        $iterator->enableResultTrace = $this->enableResultTrace;
        return $iterator;
    }

}
