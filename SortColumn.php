<?php

namespace demi\sort;

use Closure;
use Yii;
use yii\db\ActiveRecord;
use yii\grid\ActionColumn;
use yii\helpers\Html;
use yii\helpers\Url;

/**
 * GridView Column for change sort of model
 *
 * @package demi\sort
 */
class SortColumn extends ActionColumn
{
    /**
     * @inheritdoc
     */
    public $template = '{sortDown} {sortUp}';
    /** @var string Name of action for handle sort changing */
    public $action = 'change-sort';

    public function init()
    {
        parent::init();

        $this->headerOptions['style'] = 'min-width: 105px;' . (isset($this->headerOptions['style']) ? ' ' . $this->headerOptions['style'] : '');
        $this->contentOptions['style'] = 'text-align: center;' . (isset($this->contentOptions['style']) ? ' ' . $this->contentOptions['style'] : '');
    }

    /**
     * @inheritdoc
     */
    protected function initDefaultButtons()
    {
        if (!isset($this->buttons['sortUp'])) {
            $this->buttons['sortUp'] = function ($url, $model, $key) {
                /* @var $model ActiveRecord|SortBehavior */
                return Html::a('<span class="glyphicon glyphicon-arrow-up"></span>', $url, [
                    'title' => 'Sort Up',
                    'disabled' => !$model->canSort(SortBehavior::DIR_UP),
                    'data-pjax' => '0',
                    'class' => 'btn btn-info',
                ]);
            };
        }
        if (!isset($this->buttons['sortDown'])) {
            $this->buttons['sortDown'] = function ($url, $model, $key) {
                /* @var $model ActiveRecord|SortBehavior */
                return Html::a('<span class="glyphicon glyphicon-arrow-down"></span>', $url, [
                    'title' => 'Sort Down',
                    'disabled' => !$model->canSort(SortBehavior::DIR_DOWN),
                    'data-pjax' => '0',
                    'class' => 'btn btn-info',
                ]);
            };
        }
    }

    /**
     * @inheritdoc
     */
    public function createUrl($buttonName, $model, $key, $index)
    {
        if ($this->urlCreator instanceof Closure) {
            return call_user_func($this->urlCreator, $this->action, $model, $key, $index);
        } else {
            $params = is_array($key) ? $key : ['id' => (string)$key];

            // set sort direction param
            if ($buttonName == 'sortUp') {
                $params['direction'] = SortBehavior::DIR_UP;
            } elseif ($buttonName == 'sortDown') {
                $params['direction'] = SortBehavior::DIR_DOWN;
            }

            $params[0] = $this->controller ? $this->controller . '/' . $this->action : $this->action;

            return Url::toRoute($params);
        }
    }
}
