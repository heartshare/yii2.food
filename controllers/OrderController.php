<?php
/**
 * Created by PhpStorm.
 * User: mac
 * Date: 2017/10/20
 * Time: 下午2:51
 */

namespace app\controllers;

use Da\QrCode\Contracts\LabelInterface;
use Da\QrCode\Label;
use Yii;
use app\models\Order;
use yii\base\Exception;
use yii\web\Controller;

use EasyWeChat\Foundation\Application;
use EasyWeChat\Payment\Order as EOrder;

use Da\QrCode\QrCode;
use yii\web\Response;

class OrderController extends Controller {

    public $enableCsrfValidation = false;

    public function actionPay($id){
        $model = Order::findOne($id);

        $config = Yii::$app->params['wx'];
        $wxApp = new Application($config);
        $payment = $wxApp->payment;

        $totalFee = 1;
        $attributes = [
            'trade_type'=>EOrder::NATIVE,
            'body'=>"自助下单",
            'detail'=>"订单编号#{$model->id}",
            'out_trade_no'=>$model->pay_id,
            'total_fee'=>$totalFee,
            'notify_url'=>Yii::$app->urlManager->createAbsoluteUrl(['/order/notify'])// order-notify.html
        ];

        $o = new EOrder($attributes);
        $result = $payment->prepare($o);

        if ($result->return_code == 'SUCCESS' && $result->result_code == 'SUCCESS'){
            $prepayId = $result->prepay_id;
            $codeUrl = $result->code_url;

            $label = new Label("请用微信扫码支付",null,12,LabelInterface::ALIGN_CENTER,['t'=>-24,'b'=>10]);

            $qrcode = (new QrCode($codeUrl))
                ->setMargin(50)->setSize(250)->setLabel($label);
            return $this->render('pay',[
                'qrcode'=>$qrcode,
                'model'=>$model
            ]);
        }else{
            Yii::error($result->return_msg.$result->err_code_des."，订单ID为{$model->id}",'order');
            echo $result->return_msg.$result->err_code_des;
        }
    }

    public function actionNotify(){
        $config = Yii::$app->params['wx'];

        $wxApp = new Application($config);
        $payment = $wxApp->payment;
        $response = $payment->handleNotify(function($notify, $successful){
            $order_arr = json_decode($notify,true);
            $out_trade_no = $order_arr['out_trade_no'];//订单号

            @list($type, $id, $_) = explode('-', $out_trade_no);

            $model = Order::findOne($id);
            if($model == false){
                return 'Order not exist.';
            }

            if($model->paid_at > 0){
                return true;
            }

            if ($successful) {
                $model->state = 'pay';
                $model->paid_at = time();
                $model->transaction_id = $order_arr['transaction_id'];
            } else {
                $model->state = 'fail';
            }

            $model->update();

            return true;
        });
        return $response;
    }

    /**
     * 检测订单是否已经支付成功
     *
     * @param $id
     * @return array
     */
    public function actionCheck($id){
        Yii::$app->response->format = Response::FORMAT_JSON;
        try {
            $model = Order::findOne($id);
            if($model->state == 'unpay'){
                throw new Exception('订单未支付');
            }

            return ['done'=>true,'data'=>$model->id];
        }catch(Exception $e){
            return ['done'=>false,'error'=>$e->getMessage(),'data'=>$model->id];
        }
    }
}