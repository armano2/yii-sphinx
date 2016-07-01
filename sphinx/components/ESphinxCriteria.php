<?php

/**
 * ESphinxCriteria
 *
 * @author Brett O'Donnell <cornernote@gmail.com>
 * @author Zain Ul abidin <zainengineer@gmail.com>
 * @author iefreer <iefreer@hotmail.com>
 * @copyright 2014 Mr PHP
 * @link https://github.com/iefreer/yii-sphinx
 * @license BSD-3-Clause https://raw.github.com/iefreer/yii-sphinx/master/LICENSE
 *
 * @package yii-sphinx
 */
class ESphinxCriteria extends CComponent
{
    /**
     * @var string $select
     */
    public $select;

    /**
     * @var array
     */
    public $filters = array();

    /**
     * @var
     */
    public $query;

    /**
     * @var
     */
    public $groupby;

    /**
     * @var array
     */
    public $orders = array();

    /**
     * @var
     */
    public $from;

    /**
     * @var array
     */
    public $fieldWeights = array();

}
