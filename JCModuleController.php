<?php
/**
 * Created by PhpStorm.
 * User: JDC
 * Date: 7/30/19
 * Time: 2:28 PM
 */

namespace SampleCode\Controllers;

use SampleCode\Controllers\Traits\PageBuilder;

abstract class JCModuleController extends JCPageController {
    private  $isJSONResult = true;
    protected $viewName = "fullwidth";

    protected function getModuleParams() {
        parent::getModuleParams();

        $this->param("actionAddURL"    , $this->getModulePath(ACTION_ADD    ));
        $this->param("actionEditURL"   , $this->getModulePath(ACTION_EDIT   ));
        $this->param("actionInfoURL"   , $this->getModulePath(ACTION_INFO   ));
        $this->param("actionSaveURL"   , $this->getModulePath(ACTION_SAVE   ));
        $this->param("actionDeleteURL" , $this->getModulePath(ACTION_DELETE ));
        $this->param("actionDetailsURL", $this->getModulePath(ACTION_DETAILS));
    }

    function setReturn($success, $message) {
        $this->success = $success;
        $this->message = $message;
    }

}