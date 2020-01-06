<?php
/**
 * Created by PhpStorm.
 * User: JDC
 * Date: 10/29/18
 * Time: 3:52 PM
 */

namespace SampleCode\Controllers;

use SampleCode\Controllers\Interfaces\PublishesLinkInterface;
use SampleCode\Controllers\Traits\Router;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Input;
use Illuminate\View\View;

use SampleCode\UXRender\Exceptions\HTMLBuilderException;
use SampleCode\Extras\AcceptParameters;
use SampleCode\Extras\DefaultPages;
use \Exception;


/*
 * _ajaxPath, _controllerPath, _modelPath are helper function that returns current path based on Laravel
 * since laravel has tendency to change directory.
 *
 * Learned this the hard way migrating 4 to 5
 */

abstract class JCController extends BaseController implements PublishesLinkInterface {
    use DefaultPages, AcceptParameters, Router;

    protected $supportsAdd    = false;
    protected $supportsEdit   = false;
    protected $supportsDelete = false;
    protected $supportsSearch = true;
    protected $supportsTitle  = true;

    protected $viewName = "";
    protected $systemMessage = "";
    protected $redirectTo = "/login";
    private $extData = array();

    /*
     * flags if the caller expect JSON result usually API calls
     * set to false to return content directly
     */
    private  $isJSONResult = false;

    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    function __construct(){
        Cache::rememberForever('js_version_number', function() { return time(); });
        app()->setLocale("en");
    }

    public static function basePath($class = null) {
        if(!$class) {
            $curClass = get_called_class();
            return _controllerPath(new $curClass());
        } else
            return _controllerPath($class);
    }

    public static function baseAjaxPath() {
        return strtolower(_ajaxPath() .  static::basePath());
    }

    function getActionPath($module, $action, $params = [], $forAjax = true) {
        $classPath = static::basePath();

        $url = "{$classPath}{$module}/{$action}";

        if(sizeof($params) > 0)
            $url = $url . "?" . http_build_query($params, "", "&");

        if($forAjax)
            return strtolower(_ajaxPath() .  $url);
        else
            return $url;
    }
    
    function getModulePath($module, $params = [], $forAjax = true) {
        $classPath = static::basePath();

        $url = "{$classPath}{$module}";

        if(sizeof($params) > 0)
            $url = $url . "?" . http_build_query($params, "", "&");

        if($forAjax)
            return strtolower(_ajaxPath() .  $url);
        else
            return $url;
    }

    function getBaseActionPath($forAjax = true) {
        if($forAjax)
            return static::baseAjaxPath();
        else
            return static::basePath();
    }

    function getModelPath($model, $package = "") {
        return _modelPath($model, $package);
    }

    function Model($model) {
        $mdl = $this->getModelPath($model);
        if(!class_exists($mdl))
            return false;
        else return new $mdl([], true);
    }

    protected function ownerId() {
        return Auth::user()->getOwnerKey();
    }

    protected function aCheck($key, $arr , $def) {
        return array_key_exists($key, $arr)?$arr[$key]:$def;
    }

    /*
     * flags if the caller expect JSON result usually API calls
     * set to false to return content directly
     */
    public function expectsJSON($val)  {
        $this->isJSONResult = $val;

        return $this->isJSONResult;
    }

    public function expectingJSON() {
        return $this->isJSONResult;
    }

    protected static function baseName() {
        return (new \ReflectionClass(new static))->getShortName();
    }

    /*
     * Loads view based on class path of the controller {package}.{controller}
     * This method is automatically loaded to get the default view of the controller,
     * set $viewName property to override this default view
     */
    protected final function getDefaultView() {
        $class = get_class($this);

        $class = str_replace("App\\Http\\Controllers\\", "", $class);
        $class = str_replace("Controller", "", $class);
        return strtolower(str_replace("\\", ".", $class));
    }

    /*
     * Loads view based on class path of the action {package}.{controller}.{content}
     * This method is automatically loaded to get the default view of the action,
     * use get{Action}View to override default path and return path of the new view
     */
    protected final function getDefaultContentView($action) {
        $class = get_class($this);

        $class = str_replace("App\\Http\\Controllers\\", "", $class);
        $class = str_replace("Controller", "", $class);

        return strtolower(str_replace("\\", ".", $class) . ".{$action}");
    }

    public function LoadModule($module) {
        if(auth()->check()) {
            return $this->$module($this->getModuleParams());
        } else {
            return redirect($this->redirectTo);
        }
    }
    /*
     * USAGE:
     *
     * Automatically invokes method based on @content parameter
     *
     * @content             - The content of the controller to load
     * @get{$content}View   - Return a custom view. System will load the view if it points to existing view
     *                        otherwise will be treated as string
     * @get{$action}Params  - Method to set parameters of the content
     * @on{$action}         - Perform additional action
     *
     * getView -> getParameter -> getAction -> return view/json
     */
    public function LoadContent($content, $action = ACTION_BROWSE, $defaultParams = false, $json = null) {
        if(auth()->check()) {
            return $this->InternalLoadContent($content, $action, $defaultParams, $json);
        } else {
            return redirect($this->redirectTo);
        }
    }

    public function LoadContentEx($content, $action = ACTION_BROWSE) {
        return $this->LoadContent($content, $action, false, true);
    }

    abstract protected function onLoad($content = "");

    protected function MainView() {
        if($this->viewName == "") {
            if(view()->exists($this->getDefaultView()))
                return $this->getDefaultView();
            else return "misc.blank";
        } else
            return $this->viewName;
    }

    protected function getModuleParams() {
        $this->param("DefaultView", $this->getDefaultView());

        $this->param("supportsAdd"     , $this->supportsAdd    );
        $this->param("supportsEdit"    , $this->supportsEdit   );
        $this->param("supportsDelete"  , $this->supportsDelete );
        $this->param("supportsTitle"   , $this->supportsTitle  );

        $this->param("supportsSave"    , $this->supportsAdd || $this->supportsEdit);
        $this->param("supportsCancel"  , $this->supportsAdd || $this->supportsEdit);

        $this->param("supportsSearch"  , $this->supportsSearch );

        if(auth()->check() && !auth()->user()->isAdmin()) {
            $this->param("accountId", subscription()->accountId());
            $this->param("subsExpired", subscription()->expired());
            $this->param("subsEnd", subscription()->subscriptionEnd());
        }
    }

    protected function check($content, $action) {
        return true;
    }

    protected function onAction($content, $action, $fields, $dacModel = null) {
        return true;
    }

    protected function checkBrowseMode($action) {
        $act = strtolower($action);
        return ($act == strtolower(ACTION_BROWSE) || $act == strtolower(ACTION_DETAILS) || $act == strtolower(ACTION_DETAILS));
    }

    private function InternalRenderForHTML($content, $action, $defaultParams, $json) {
        $view = $this->$content($action);

        if(is_array($view)) {
            if($action == ACTION_ADD)
                $dacModel = null;
            else {
                $dataRes = builder()->loadData($this->checkBrowseMode($action), $view);
                $dacModel = $dataRes["data"];
            }

            $result = $this->onAction($content, $action, $this->aCheck("fields", $view, []), $dacModel);
        } else {
            $result = $this->onAction($content, $action, []);
            $dacModel = null;
        }

        $modelLoaded = $dacModel != null;

        if(! is_bool($result)) throw new HTMLBuilderException("onAction must return a boolean value");

        if(is_array($view)) {
            if($action == ACTION_SAVE) {
                return $this->render($result, "", $json);
            } else {
                $view = builder()->dynamicRender($view, $action, $this->checkBrowseMode($action), $modelLoaded, $dacModel);

                return $this->render($result, $view["content"], $json);
            }
        } else if(view()->exists(htmlentities($view)))
            return $this->render($result, view($view, $this->getParameters()), $json);
        else {
            return $this->render($result, $view, $json);
        }
    }

    private function InternalRenderForPreview($content, $action, $defaultParams, $json) {
        return $this->getContentPreview($content);
    }

    private function InternalRenderForPrinting($content, $action, $defaultParams, $json) {
        $preview = $this->InternalRenderForPreview($content, $action, $defaultParams, $json);
        // print code here
    }

    protected function pageNotFound() {
        return $this->render(true, view("jc-ux::misc.404"), false);
    }

    /*
     * See LoadContent
     */
    protected function InternalLoadContent($content, $action, $defaultParams, $json) {
        if($defaultParams) $this->getModuleParams();
        
        if(!$action) $action = ACTION_BROWSE;

        if($this->check($content, $action)) {
                if(method_exists($this, $content)) {
                    $this->onLoad($content);

                    if(method_exists($this, $content . "_Initialize")) {
                        $func = $content . "_Initialize";

                        $this->$func();
                    }

                    switch ($action) {
                        case ACTION_PRINT:
                            return $this->InternalRenderForPrinting($content, $action, $defaultParams, $json);
                        case ACTION_PREVIEW:
                        return $this->InternalRenderForPreview($content, $action, $defaultParams, $json);
                        default:
                            return $this->InternalRenderForHTML($content, $action, $defaultParams, $json);
                    }

                } else if($json)
                    return $this->render(true, view("jc-ux::misc.404"), $json);
                else {
                    return $this->pageNotFound();
                }
            }
    }

    protected function setMessage($message) {
        $this->systemMessage = $message;
    }

    protected function addExtData($data) {
        $this->extData[] = $data;
    }

    protected function setExtData($data) {
        if(!is_array($data))
            $this->extData = array($data);
        else $this->extData = $data;
    }

    public function render($success, $raw, $forceJSON) {
        if($forceJSON != null && !$forceJSON) {
            if($raw instanceof View)
                return $raw->render();
            else
                return $raw;
        } else if($this->isJSONResult || ($forceJSON != null || $forceJSON)) {
            $result['success'] = $success;
            $result['message'] = $this->systemMessage;
            $result['extData'] = $this->extData;

            if($raw instanceof View)
                $result['content'] = $raw->render();
            else
                $result['content'] = $raw;

            return response(json_encode($result))->header("content-type", "application/json");
        } else {
            if($raw instanceof View)
                return $raw->render();
            else
                return $raw;
        }
    }

    public function JSONResult($success, $content="") {
        $result['success'] = $success;
        $result['message'] = $this->systemMessage;
        $result['extData'] = $this->extData;
        $result['content'] = $content;

        return response(json_encode($result))->header("content-type", "application/json");
    }

    /*
     * IMPORTANT!!!!
     * Always use this method when getting value from Input for easier code maintenance
     *
     * I know you understand what I mean :D
     *
     * Yours, JDC
     */
    function get($key) {
        return clean(Input::get($key));
    }

    function mergeInput($key, $value) {
        return Input::merge([$key => $value]);
    }

    function hash($value) {
        return Hash::make($value);
    }

    function hashCheck($value, $hashed) {
        return Hash::check($value, $hashed);
    }
}
