yii2-sort
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
    return [
        // ...
        'sortBehavior' => [
            'class' => 'demi\sort\SortBehavior',
            'sortConfig' => [
                'sortAttribute' => 'sort',
                'condition' => function ($query, $model) {
                        /* @var $query \yii\db\Query */
                        $query->andWhere(['category_id' => $model->category_id]);
                    },
            ],
        ],
    ];
}
```

Usage
-----
In GridView:
```php
<?= GridView::widget([
    // ...
    'columns' => [
        // ...
        [
            'class' => 'demi\sort\SortColumn',
            'action' => 'change-sort', // optional
        ],
    ],
]); ?>
```
Don't forget set default order!
usually CategorySearch::search()
```php
$dataProvider = new ActiveDataProvider([
    'query' => $query,
    'sort' => ['defaultOrder' => ['sort' => SORT_ASC]],
]);
```

In view file:
```php
$canSortDown = $model->canSort(SORT_DESC);
$canSortUp = $model->canSort(SORT_ASC);

if ($canSortDown) {
    echo Html::a('Down', ['change-sort', 'id' => $model->id, 'direction' => SORT_DESC]);
}

if ($canSortUp) {
    echo Html::a('Up', ['change-sort', 'id' => $model->id, 'direction' => SORT_ASC]);
}
```

In conrtoller/model file:
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
            'class' => 'demi\sort\SortAction',
            'modelClass' => \common\models\Category::className(),

            'afterChange' => function ($model) {
                    if (!Yii::$app->request->isAjax) {
                        return Yii::$app->response->redirect(Url::to(['update', 'id' => $model->category_id]));
                    } else {
                        return Yii::$app->controller->renderPartial('index', ['model' => $model]);
                    }
                },
            // or
            'redirectUrl' => Url::to(['index']),
            // or
            'redirectUrl' => function ($model) {
                    return Yii::$app->response->redirect(Url::to(['update', 'id' => $model->category_id]));
                },

            'canSort' => Yii::$app->user->can('admin'),
            // or
            'canSort' => function ($model) {
                    return Yii::$app->user->id == $model->user_id;
                },
        ],
    ];
}
```