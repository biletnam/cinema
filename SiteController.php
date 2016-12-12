<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\filters\VerbFilter;
use app\models\Number;
use yii\db\Command;


class SiteController extends Controller
{

    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['logout'],
                'rules' => [
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    public function actionIndex()
    {
      	$seats = \Yii::$app
        	->db
        	->createCommand('SELECT * FROM seats ORDER BY seat_id ASC')->queryAll();

      	$model = new Number();
        if (Yii::$app->request->isPost) {
            $model->attributes = Yii::$app->request->post('Number');
	            if ($model->validate()) {
	            $visitors_count = $model->visitors;
	            $proposition = $this->getSeatsFor($visitors_count);
	            return $this->render('index', ['model' => $model,
	            'seats' => $seats,'visitors_count' => $visitors_count,'your_seat' => $proposition]);
          	}
        } else {
            return $this->render('index', ['model' => $model,'seats' => $seats]);
        }
    }

    private function getSeatsFor($visitors_count)
    {
      	if ($visitors_count <= 0) return [];
      	$seats = \Yii::$app
        	->db
        	->createCommand('SELECT * FROM seats ORDER BY seat_id ASC')->queryAll();

      	$available_intervals = [];
      	$available_interval = [];
      	foreach ($seats as $seat) {
          	if ($seat['received'] == 'no') {
              	array_push($available_interval, $seat);
              	$available_interval_size = count($available_interval);
              	if ($available_interval_size >= $visitors_count) {
                  	return $available_interval;
              	}
          	} elseif ($available_interval) {
              	array_push($available_intervals, $available_interval);
              	$available_interval = [];
          	}
      	}
      	if ($available_interval) {
          	array_push($available_intervals, $available_interval);
      	}
      	$available_seats = array_reduce($available_intervals, function($carry, $item) {
          	$carry = array_merge($carry, $item);
          	return $carry;
      	}, []);
      	$available_seats_count = count($available_seats);
      	if ($available_seats_count < $visitors_count) {
          	return null;
      	} elseif ($available_seats_count == $visitors_count) {
          	return $available_seats;
      	} else {
          	$seats_to_propose = [];
          	do {
              	$best_case = $this->getBestCase($available_intervals, $seats_to_propose, $visitors_count);
              	array_splice($available_intervals, $best_case->i, 1); // remove found interval from the list
              	$best_interval = $best_case->interval;
              	$seats_to_propose = array_merge($seats_to_propose,  array_slice($best_interval, 0, $visitors_count));    // add best seats to the list of seats
              	$visitors_count -= count($best_interval);
          	} while ($visitors_count > 0);
          	return $seats_to_propose;
      	}
    }

    private function getBestCase($intervals, $seats, $visitors_count)
    {
      	$best_case = (object) [
          	"i" => 0,
          	"interval" => $intervals[0]
      	];
      	$range = $seats ? [$seats[0], $seats[count($seats) - 1]] : null;   // first and last seats of already added seats
      	for ($i = 1; $i < count($intervals); $i++) {
          	$count1 = count($best_case->interval);
          	$count2 = count($intervals[$i]);
          	if ($count1 >= $visitors_count && $count2 >= $visitors_count) { // if both intervals contain sufficient number of seats find the closest one
           	   	$distance1 = $this->getDistance($best_case->interval, $range);
           	   	$distance2 = $this->getDistance($intervals[$i], $range);
            	if ($distance1 > $distance2) {
	                $best_case->i = $i;
	                $best_case->interval = $intervals[$i];
              	}
          	} elseif ($count1 < $count2) {
	            $best_case->i = $i;
	            $best_case->interval = $intervals[$i];
          	}
      	}
      	return $best_case;
    }

    private function getDistance($interval, $range)
    {
      	if (is_null($range)) {
          	return 0;
      	}
      	if ($interval[0]['seat_id'] > $range[0]['seat_id']) {
          	return min($interval[0]['seat_id'] - $range[0]['seat_id'], $interval[count($interval) - 1]['seat_id'] - $range[0]['seat_id'], $interval[0]['seat_id'] - $range[1]['seat_id'], $interval[count($interval) - 1]['seat_id'] - $range[1]['seat_id']);
      	} else {
          	return min($range[0]['seat_id'] - $interval[0]['seat_id'], $range[0]['seat_id'] - $interval[count($interval) - 1]['seat_id'], $range[1]['seat_id'] - $interval[0]['seat_id'], $range[1]['seat_id'] - $interval[count($interval) - 1]['seat_id']);
      	}
    }
    
}
