<?php

namespace demi\sort;

use Closure;
use Yii;
use yii\base\Action;
use yii\base\InvalidParamException;
use yii\db\ActiveRecord;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Standalone action for change sort of model
 *
 * @package demi\sort
 */
class SortAction extends Action
{
    /** @var string ClassName of AR model */
    public $modelClass;
    /** @var Closure|bool Closure function to check user access to delete model image */
    public $canSort = true;
    /** @var Closure|array|string Closure function to get redirect url on after delete image */
    public $redirectUrl;
    /** @var Closure|null Closure function to get custom response on after delete image */
    public $afterChange;

    public function run()
    {
        /* @var $model ActiveRecord|SortBehavior */
        $model = new $this->modelClass;

        $request = Yii::$app->request;
        $pk = $model->getTableSchema()->primaryKey;
        $attributes = [];

        // forming search condition
        foreach ($pk as $primaryKey) {
            $pkValue = static::_getRequestParam($primaryKey);
            if ($pkValue === null) {
                throw new InvalidParamException('You must specify "' . $primaryKey . '" param');
            }
            $attributes[$primaryKey] = $pkValue;
        }

        $model = $model->find()->where($attributes)->one();

        if (!$model) {
            throw new NotFoundHttpException('The requested model does not exist.');
        }

        $canSort = $this->canSort instanceof Closure ? call_user_func($this->canSort, $model) : $this->canSort;
        if (!$canSort) {
            throw new ForbiddenHttpException('You are not allowed to change sort');
        }

        $direction = static::_getRequestParam('direction');

        // Change sort value
        $model->changeSorting($direction);

        // if exist custom response function
        if ($this->afterChange instanceof Closure) {
            return call_user_func($this->afterChange, $model);
        }

        $response = Yii::$app->response;
        // if is AJAX request
        if ($request->isAjax) {
            $response->getHeaders()->set('Vary', 'Accept');
            $response->format = Response::FORMAT_JSON;

            return ['status' => 'success'];
        }

        $url = $this->redirectUrl instanceof Closure ? call_user_func($this->redirectUrl, $model) : $this->redirectUrl;

        // Generate default url
        if (empty($url)) {
            $url = array_merge(['view'], $attributes);
        }

        return $response->redirect($url);
    }

    /**
     * Return param by name from $_POST or $_GET. Post priority
     *
     * @param string $name
     * @param mixed|null $defaultValue
     *
     * @return array|mixed|null
     */
    private static function _getRequestParam($name, $defaultValue = null)
    {
        $value = $defaultValue;
        $request = Yii::$app->request;

        $get = $request->get($name, $defaultValue);
        $post = $request->post($name, $defaultValue);

        if ($post !== $defaultValue) {
            return $post;
        } elseif ($get !== $defaultValue) {
            return $get;
        }

        return $value;
    }
} 