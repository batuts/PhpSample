<?php
/**
 * Created by PhpStorm.
 * User: JDC
 * Date: 8/9/19
 * Time: 10:35 AM
 */

namespace SampleCode\Controllers\Traits;

use Illuminate\Support\Facades\Auth;

trait PageBuilder {
    use ActionHandler;

    abstract protected function onPageInitialize();
    abstract protected function getTitle();
    abstract protected function getContent();

    /*
     * Format [funcName=>Title, ...]
     */
    protected $publishedModules = [];

    protected function checkBrowseMode($action) {
        $act = strtolower($action);
        return ($act == strtolower(ACTION_BROWSE) || $act == strtolower(ACTION_DETAILS) || $act == strtolower(ACTION_DETAILS));
    }

    protected function getPageActions() {
        return [];
    }

    protected function pageNotFound() {
        $this->isHome = false;
        $this->getModuleParams();

        $this->viewName = "404";

        $this->param("title", "Page Not Found!");

        return $this->render(true, view($this->MainView(), $this->getParameters()), false);
    }

    protected function getModuleParams() {
        parent::getModuleParams();
        $this->param("menu", $this->internalGetMenu());
        $this->param("pageActions", $this->getPageActions());
        $this->param("isHome", $this->isHome);
        $this->param("crudList", $this->getCrudList());
    }


    private function modulePublished($module) {
        return arrayIf($module, $this->publishedModules, false);
    }

    private function internalGetTitle($module = "") {
        if($module == "")
            return $this->getTitle(); // get the default page title
        else
            return arrayIf($module, $this->publishedModules, "");
    }

    private function internalGetMenu() {
        $menu["rightmenu"] = fireEvent("jc.build.rightmenu", "");
        $menu["leftmenu"] = fireEvent("jc.build.leftmenu", "");
        $menu["rightnav"] = fireEvent("jc.build.rightNav", "");

        return $menu;
    }

    function getBuilder() {
        return builder();
    }

    private function internalRenderPageBuilder($title, $module, $content) {
        $this->param("content", $content);
        $this->param("moduleId", $module);
        $this->param("title", $title);
    }

    final protected function onLoad($content = "") {
        $this->onPageInitialize();
    }

    public static function indexPath() {
        return static::basePath() . "index";
    }

    public static function indexAjaxPath() {
        return static::baseAjaxPath() . "index";
    }

    final function index() {
        if(Auth::check()) {
            return $this->Load();
        } else {
            return view('public', ["menu"=>"", "cols"=>"", "el"=>"", "topbar"=>""]);
        }
    }

    protected function getCrudList() {
        return [];
    }

    /*
     * Load Index Page of a PageController
     */
    public function Load() {
        if(auth()->check()) {
            $this->isHome = true;
            $this->onLoad("");
            $this->getModuleParams();

            if(is_array($view = $this->getContent())) {
                $view = builder()->dynamicRender($view, ACTION_BROWSE, $this->checkBrowseMode(ACTION_BROWSE));;
                $content = $view["content"];
            } else
                $content = $view;

            $this->internalRenderPageBuilder($this->internalGetTitle(), "index", $content);

            return $this->render(true, view($this->MainView(), $this->getParameters()), null);
        } else {
            return redirect($this->redirectTo);
        }
    }

    final function LoadPageFromModule($module, $action = ACTION_BROWSE) {
        if(Auth::check()) {
            if($this->modulePublished($module)) {
                $this->isHome = false;
                $content = $this->InternalLoadContent($module, $action, true, false);

                $this->internalRenderPageBuilder($this->internalGetTitle($module), $module, $content);

                return $this->render(true, view($this->MainView(), $this->getParameters()), null);
            } else
                return $this->pageNotFound();
        } else {
            return redirect($this->redirectTo);
        }
    }
}