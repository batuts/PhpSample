<?php
/**
 * Created by PhpStorm.
 * User: 04-167
 * Date: 8/9/19
 * Time: 1:37 PM
 */

namespace SampleCode\Controllers\Traits;

use SampleCode\Models\PostingInterface;
use SampleCode\Models\PublisherInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Input;

trait ActionHandler {
    abstract function getCRUDModel($crudId);

    private function internalExecuteActionEvent($crudId, $action, $event, $dacModel) {
        $func = $crudId . "_{$event}" . ucfirst($action);

        if(method_exists($this, $func)) {
            return $this->$func($dacModel);
        } else return true;
    }

    private function updateFailed($except = null) {
        DB::rollBack();

        if ($except != null) {
            $msg = strtolower($except->getMessage());
            if (getenv('APP_DEBUG') == true) {

                $this->setExtData($except->errorInfo);
                $this->systemMessage = $msg;
                return false;
            } else {
                $keyCode = $this->get("form-key");
                $msg = strtolower($except->getMessage());

                if (strpos($msg, "duplicate"))
                    $this->systemMessage = "The code you entered, =>[{$keyCode}], already exist.<br>Please enter a new code before saving.";
                else if (strpos($msg, "nulls") || strpos($msg, "NULL"))
                    $this->systemMessage = "One or more required field contains empty value. Please make sure you filled out all required fields before saving.";
                else $this->systemMessage = "An unknown QueryException has occured. Please contact your systems administrator";

                $this->setExtData([]);
                return false;
            }
        } else {
            $this->systemMessage = "Update Failed!";
        }
    }

    function internalExecuteAdd($crudId) {
        return true;
    }

    function internalExecuteEdit($crudId) {
        $dacModel = $this->getCRUDModel($crudId);

        $extData["canPost"] = false;
        $extData["canPublish"] = false;

        $this->setExtData($extData);

        return $dacModel !=  null;
    }

    function internalExecuteDelete($crudId) {
        $dacModel = $this->getCRUDModel($crudId);

        $dataKey = $dacModel->getDataKeyName($crudId);

        if($dacModel = $dacModel->where($dataKey, $this->get("id"))->select()->first()) {
            try {
                $this->internalExecuteActionEvent($crudId, ACTION_DELETE, "Before", $dacModel);
                $result = $dacModel->delete();
                $this->internalExecuteActionEvent($crudId, ACTION_DELETE, "After", $dacModel);

                $dacModel = $this->getCRUDModel($crudId)->select()->first();

                if($dacModel == null)
                    $this->setExtData(array("id"=>"__BLANK__"));
                else $this->setExtData(array("id"=>$dacModel->$dataKey));

                return $result;
            } catch(QueryException $except){
                $this->systemMessage = "Delete request failed";
                $this->setExtData($except->getMessage());
                return false;
            }
        } else {
            $this->systemMessage = "Delete request failed";
            return false;
        }
    }

    function internalExecutePublish($crudId) {
        //removed
    }

    function internalExecuteSave($crudId, $fields) {
       // removed
    }

    function internalExecuteCancel($crudId) {
        return true;
    }

    private function internalAddExtData($model, &$extData) {
        $extData["supportsPublish"] = $model instanceof PublisherInterface;
        if($extData["supportsPublish"]) {
            $extData["canPublish"] = $model->canPublish();
        } else
            $extData["canPublish"] = false;

        $extData["supportsPost"] = $model instanceof PostingInterface;
        if($extData["supportsPost"]) {
            $extData["canPost"] = $model->canPost();
        } else
            $extData["canPost"] = false;
    }

    function internalExecuteBrowse($crudId, $model) {
        $func = $crudId . "_OnBrowse";

        $extData = [];

        $this->internalAddExtData($model, $extData);

        $this->setExtData($extData);

        if(method_exists($this, $func)) {
            $this->$func();
        }

        return true;
    }

    function onAction($crudId, $action, $fields, $dacModel = null) {

        /*
         * Print and Preview handled on JCController
         */
        switch(strtolower($action)) {
            case strtolower(ACTION_ADD):
                return $this->internalExecuteAdd($crudId);
                break;
            case strtolower(ACTION_EDIT):
                return $this->internalExecuteEdit($crudId);
                break;
            case strtolower(ACTION_DELETE):
                return $this->internalExecuteDelete($crudId);
                break;
            case strtolower(ACTION_SAVE):
                return $this->internalExecuteSave($crudId, $fields);
                break;
            case strtolower(ACTION_CANCEL):
                return $this->internalExecuteCancel($crudId);
                break;
            case strtolower(ACTION_PUBLISH):
                return $this->internalExecutePublish($crudId);
                break;
            default:
                return $this->internalExecuteBrowse($crudId, $dacModel);
        }
    }
}