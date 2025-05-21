<?php
namespace app\controllers\home;

use app\models\Config;
use Yii;
use yii\helpers\Url;
use yii\web\Response;
use app\models\Module;
use app\models\Api;
use app\models\Field;
use app\models\api\CreateApi;
use app\models\api\UpdateApi;
use app\models\api\DeleteApi;

class ApiController extends PublicController
{
    public $checkLogin = false;

    public function actionDebug($id)
    {
        $api = Api::findModel($id);

        $project = $api->module->project;

        // 获取当前版本
        $project->current_version = $api->module->version;

        return $this->display('debug', ['project' => $project, 'api' => $api]);
    }

    /**
     * 添加接口
     * @param $module_id 模块ID
     * @return array|string
     */
    public function actionCreate($module_id)
    {
        $request = Yii::$app->request;

        $module = Module::findModel(['encode_id' => $module_id]);

        $api = new CreateApi();

        if($request->isPost){

            Yii::$app->response->format = Response::FORMAT_JSON;

            if(!$api->load($request->post())){
                return ['status' => 'error', 'message' => '数据加载失败'];
            }

            if(!$api->store()){
                return ['status' => 'error', 'message' => $api->getErrorMessage(), 'label' => $api->getErrorLabel()];
            }

            $callback = url('home/api/show', ['id' => $api->encode_id]);

            return ['status' => 'success', 'message' => '创建成功', 'callback' => $callback];

        }

        return $this->display('create', ['api' => $api, 'module' => $module]);
    }

    /**
     * 更新接口
     * @param $id 接口ID
     * @return array|string
     */
    public function actionUpdate($id)
    {
        $request = Yii::$app->request;

        $api = UpdateApi::findModel(['encode_id' => $id]);

        if($request->isPost){

            Yii::$app->response->format = Response::FORMAT_JSON;

            if(!$api->load($request->post())){
                return ['status' => 'error', 'message' => '数据加载失败'];
            }

            if ($api->store()) {
                $callback = url('home/api/show', ['id' => $api->encode_id]);
                return ['status' => 'success', 'message' => '编辑成功', 'callback' => $callback];
            }

            return ['status' => 'error', 'message' => $api->getErrorMessage(), 'label' => $api->getErrorLabel()];

        }

        return $this->display('update', ['api' => $api]);
    }
    
    /**
     * 删除接口
     * @param $id 接口ID
     * @return array|string
     */
    public function actionDelete($id)
    {
        $request = Yii::$app->request;

        $api = DeleteApi::findModel(['encode_id' => $id]);

        if($request->isPost){

            Yii::$app->response->format = Response::FORMAT_JSON;

            if(!$api->load($request->post())){
                return ['status' => 'error', 'message' => '加载数据失败'];
            }

            if ($api->delete()) {
                $callback = url('home/project/show', ['id' => $api->module->project->encode_id]);
                return ['status' => 'success', 'message' => '删除成功', 'callback' => $callback];
            }

            return ['status' => 'error', 'message' => $api->getErrorMessage(), 'label' => $api->getErrorLabel()];

        }

        return $this->display('delete', ['api' => $api]);
    }

    /**
     * 接口详情
     * @param $id 接口ID
     * @return string
     */
    public function actionShow($id, $tab = 'home')
    {
        $api = Field::findModel(['encode_id' => $id]);

        if($api->project->isPrivate()) {

            if(Yii::$app->user->isGuest) {
                return $this->redirect(['home/account/login','callback' => Url::current()]);
            }

            if(!$api->project->hasAuth(['project' => 'look'])) {
                return $this->error('抱歉，您无权查看');
            }
        }

        switch ($tab) {
            case 'home':
                $view  = '/home/api/home';
                break;
            case 'field':
                $view  = '/home/field/home';
                break;
            case 'debug':
                $view  = '/home/api/debug';
                break;
            default:
                $view  = '/home/api/home';
                break;
        }

        return $this->display($view, ['project' => $api->project, 'api' => $api]);
    }

    /**
     * 导出接口文档
     * @param $id 接口ID
     * @return string
     */
    public function actionExport($id)
    {
        $api = Field::findModel(['encode_id' => $id]);

        if(!$api->project->hasAuth(['api' => 'export'])) {
            return $this->error('抱歉，您没有操作权限');
        }

        $account = Yii::$app->user->identity;
        $cache   = Yii::$app->cache;

        $config = Config::findOne(['type' => 'app']);

        $cache_key = 'api_' . $id . '_' . $account->id;
        $cache_interval = (int)$config->export_time;

        if($cache_interval >0 && $cache->get($cache_key) !== false){
            $remain_time = $cache->get($cache_key)  - time();
            if($remain_time < $cache_interval){
                return $this->error("抱歉，导出太频繁，请{$remain_time}秒后再试!", 5);
            }
        }

        $file_name = "[{$api->module->title}]" . $api->title . '离线文档.html';

        header ("Content-Type: application/force-download");
        header ("Content-Disposition: attachment;filename=$file_name");

        // 限制导出频率, 60秒一次
        Yii::$app->cache->set($cache_key, time() + $cache_interval, $cache_interval);

        return $this->display('export', ['api' => $api]);
    }
}
