yii2-image-uploader
===================

Yii2 behavior to sort models

Installation
------------
Add to composer.json in your project
```json
{
	"require":
	{
  		"demi/sort": "dev-master"
	}
}
```
then run command
```code
php composer.phar update
```

Configuration
-------------
In model file add sort behavior:
```php
public function behaviors()
{
    $it = $this;

    return [
        // ...
        'sortBehavior' => [
            'class' => 'demi\sort\SortBehavior',
            'sortConfig' => [
                'sortAttribute' => 'sort',
                'condition' => function ($query) use ($it) {
                        /* @var $query \yii\db\Query */
                        $query->andWhere(['category_id' => $it->category_id]);
                    },
            ],
        ],
    ];
}
```

Usage
-----
In view file:
```php
$canSortDown = $model->canSort(SORT_DESC);
$canSortUp = $model->canSort(SORT_ASC);
```

In any file:
```php
// sort model down
$model->changeSorting(SORT_DESC);

// sort model up
$model->changeSorting(SORT_DESC);
```

Bonus: Sort Action
-----
Add this code to you controller:
```php
public function actions()
{
    return [
        'change-sort' => [
            'class' => SortAction::className(),
            'modelClass' => RealtyImage::className(),

            'afterChange' => function ($model) {
                    if (!Yii::$app->request->isAjax) {
                        return Yii::$app->response->redirect(Url::to(['update', 'id' => $model->category_id]));
                    } else {
                        return Yii::$app->controller->renderPartial('index', ['model' => $model]);
                    }
                },
            // or
            'redirectUrl' => Url::to('index'),
            // or
            'redirectUrl' => function ($model) {
                    return Yii::$app->response->redirect(Url::to(['update', 'id' => $model->category_id]));
                },

            'canSort' => Yii::app()->user->can('admin'),
            // or
            'canSort' => function ($model) {
                    return Yii::app()->user->id == $model->user_id;
                },
        ]
    ];
}
```