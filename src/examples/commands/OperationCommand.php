<?php
/**
 * @author Ian Kuznetsov <yankuznecov@ya.ru>
 * Date: 27.12.2016
 * Time: 12:56
 */

namespace yanpapayan\incubator\commands;

use common\incubator\models\Operation;
use yii\console\Controller;
use yii\db\Exception;

/**
 * Class OperationCommand
 * @package yanpapayan\incubator\commands
 * @author Ian Kuznetsov <yankuznecov@ya.ru>
 */
class OperationCommand extends Controller
{
    public function actionSync()
    {
        $models = Operation::find()->where(['status' => Operation::STATUS_NEW]);
        foreach ($models->each() as $model) {
            $this->process($model);
            sleep(1);
        }
    }

    private function process(Operation $model)
    {
        $dbTransaction = \Yii::$app->db->beginTransaction();
        try {
            $model->process();
            $dbTransaction->commit();
        } catch (Exception $e) {
            $dbTransaction->rollback();
            throw $e;
        }
    }
}