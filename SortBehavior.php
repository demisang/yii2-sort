<?php

namespace demi\sort;

use Closure;
use Yii;
use yii\base\Behavior;
use yii\db\ActiveRecord;
use yii\db\Query;
use yii\web\ServerErrorHttpException;

/**
 * Behavior for sorting materials
 *
 * @package demi\sort
 *
 * @property ActiveRecord $owner
 */
class SortBehavior extends Behavior
{
    /** @var string Name of attribute which is responsible for sorting */
    private $_sortAttribute = 'sort';
    /** @var Query A condition that will be excluded from sort some records
     * This is useful if for the new model must specify the restriction of sorting within the same "post_id" */
    private $_condition;
    /** @var array Behavior configuration */
    public $sortConfig = [];
    /** @var string Condition hash for current model */
    private $_hash;
    /** @var array Variable for temporarily storing min. and max. values */
    private static $_min_max_vals = array();
    const DIR_UP = SORT_ASC;
    const DIR_DOWN = SORT_DESC;

    /**
     * @param ActiveRecord $owner
     */
    public function attach($owner)
    {
        parent::attach($owner);

        // Apply configuration options
        foreach ($this->sortConfig as $key => $value) {
            $var = '_' . $key;
            $this->$var = $value;
        }
    }

    public function events()
    {
        return [
            ActiveRecord::EVENT_BEFORE_INSERT => 'beforeSave',
            ActiveRecord::EVENT_BEFORE_UPDATE => 'beforeSave',
        ];
    }

    /**
     * Change sort of this model
     *
     * @param string $direction
     *
     * @throws ServerErrorHttpException
     * @return boolean
     */
    public function changeSorting($direction)
    {
        static::_checkSortDirection($direction);

        $owner = $this->owner;
        // sort attribute name
        $s_attr = $this->_sortAttribute;
        // Current sort value
        $current_sort = $owner->getAttribute($s_attr);
        // Find adjacent model, which is necessary to exchange sorting
        $query = $owner->find();
        // Add custom conditions
        $this->_applyCustomCondition($query);

        if ($direction == static::DIR_UP) {
            $query->orderBy = [$s_attr => SORT_DESC];
            $sign = '<';
        } else {
            $sign = '>';
            $query->orderBy = [$s_attr => SORT_ASC];
        }

        $swap_model = $query->andWhere($s_attr . $sign . ':current_sort',
            [':current_sort' => $current_sort])->one($owner->getDb());

        if ($swap_model) {
            // Change sorting the current record on the value of neighboring recording
            $owner->setAttribute($s_attr, $swap_model->getAttribute($s_attr));
            $owner->update(false, [$s_attr]);
            // Change the sorting at neighboring record to the value of the current record
            $swap_model->setAttribute($s_attr, $current_sort);
            $swap_model->update(false, [$s_attr]);

            return true;
        }

        return false;
    }

    /**
     * Checks possible to to change the sort of the current model in the specified direction
     *
     * @param string $direction
     *
     * @throws ServerErrorHttpException
     * @return boolean
     */
    public function canSort($direction)
    {
        static::_checkSortDirection($direction);

        $owner = $this->owner;
        $hash = $this->_getConditionHash();
        if (!isset(self::$_min_max_vals[$hash])) {
            $this->_loadMinMax();
        }

        if ($direction == static::DIR_UP) {
            // If the current sorting value is equal to the minimum value
            if ($owner->getAttribute($this->_sortAttribute) == self::$_min_max_vals[$hash]['min']) {
                // Do not allow sorting up
                return false;
            }
        } else {
            // If the current sorting value is equal to the maximum value
            if ($owner->getAttribute($this->_sortAttribute) == self::$_min_max_vals[$hash]['max']) {
                // Do not allow sorting down
                return false;
            }
        }

        return true;
    }

    /**
     * Validates the specified sort direction
     *
     * @param string $direction SortBehavior::DIR_UP or SortBehavior::DIR_DOWN
     *
     * @throws ServerErrorHttpException
     */
    private static function _checkSortDirection($direction)
    {
        if ($direction != static::DIR_UP && $direction != static::DIR_DOWN) {
            throw new ServerErrorHttpException('You must set $direction as "' . static::DIR_UP . '" or "' . static::DIR_DOWN . '"!', 500);
        }
    }

    /**
     * Loads the minimum and maximum sort values
     */
    private function _loadMinMax()
    {
        $owner = $this->owner;
        $s_field = $this->_sortAttribute;

        // Find the minimum and maximum values for the sorting table
        $query = (new Query())->select("MIN(`$s_field`) as min_sort, MAX(`$s_field`) as max_sort")->from($owner->tableName());
        $this->_applyCustomCondition($query);
        $row = $query->one($owner->getDb());

        $hash = $this->_getConditionHash();

        // Stores the values
        self::$_min_max_vals[$hash] = [
            'min' => (int)$row['min_sort'],
            'max' => (int)$row['max_sort'],
        ];
    }

    /**
     * Event handler for before record saving
     */
    public function beforeSave()
    {
        $owner = $this->owner;
        $s_field = $this->_sortAttribute;
        $sort = $owner->getAttribute($s_field);

        // Set new sorting value for records without sort value
        if (empty($sort)) {
            // Find the last element of the sort
            $hash = $this->_getConditionHash();
            if (!isset(static::$_min_max_vals[$hash])) {
                $this->_loadMinMax();
            }
            $max = static::$_min_max_vals[$hash]['max'];

            // Sets the value of the sort as max + 1
            $owner->setAttribute($this->_sortAttribute, $max + 1);
        }
    }

    /**
     * Add custom condition to query conditions
     *
     * @param Query $query
     */
    private function _applyCustomCondition(Query $query)
    {
        if ($this->_condition instanceof Closure) {
            call_user_func($this->_condition, $query);
        } elseif ($this->_condition !== null) {
            $query->andWhere($this->_condition);
        }
    }

    /**
     * Returns the md5-hash composed of model class + condition
     * @return string
     */
    private function _getConditionHash()
    {
        if (!empty($this->_hash)) {
            // return previously obtained hash
            return $this->_hash;
        }

        $str = get_class($this->owner);

        // If isset custom condition
        if ($this->_condition !== null) {
            // generate sql-string with custom condition
            $q = $this->owner->find();
            $this->_applyCustomCondition($q);

            $str .= '::' . $q->createCommand()->getRawSql();
        }


        $this->_hash = md5($str);

        return $this->_hash;
    }
}