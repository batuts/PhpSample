<?php
/**
 * Created by PhpStorm.
 * User: JDC
 * Date: 8/9/19
 * Time: 10:29 AM
 */

namespace SampleCode\Controllers;


/*
 * Basic Usage
 * /{PACKAGE_CODE}                                              - Loads index page of a Package Controller
 * /{PACKAGE_CODE}/{MODULE}                                     - Loads page from a Package Controller
 * /{BASE_AJAX}/{PACKAGE_CODE}/{MODULE}/{ACTION}                - Execute action of package module
 *
 * /{PACKAGE_CODE}/{CONTROLLER}/index                           - Loads index page of a Module Controller
 *
 * /{BASE_AJAX}/{PACKAGE_CODE}/{CONTROLLER}/{MODULE}            - Loads module from a controller
 * /{BASE_AJAX}/{PACKAGE_CODE}/{CONTROLLER}/{MODULE}/{ACTION}   - Execute action of module from a module controller
  */

use SampleCode\Controllers\Interfaces\PackageControllerInterface;

abstract class JCPackageController extends JCPageController implements PackageControllerInterface {
    abstract public static function routes();

    /*
     * Override to return only the package code path for this package and not the controller path
     */
    public static function basePath($class = null) {
        return strtolower(static::PACKAGE_CODE . "/");
    }

    /*
     * This function is used to generate action path
     * override baseAjaxPath to support actions on Package
     */
    public static function baseAjaxPath($class = null) {
        return strtolower(_ajaxPath() . static::PACKAGE_CODE . "/p/");
    }

    public static function moduleAjaxPath() {
        return parent::baseAjaxPath();
    }

    public static function defaultRoutes() {
        $packageCode = static::PACKAGE_CODE;
        static::routeGet("/" . $packageCode, function() use ($packageCode) {
            return _loadPackage($packageCode);
        });

        static::routeGet($packageCode . "/{content}", function($content) use ($packageCode) {
            return _loadPackage($packageCode, $content, "", false);
        });

        static::routeGet(_ajaxPath() . $packageCode . "/{content}", function($content) use ($packageCode) {
            return _loadPackage($packageCode, $content, "", true);
        });

        static::routeGet(_ajaxPath() . $packageCode . "/p/{content}/{action}", function($content, $action) use ($packageCode) {
            return _loadPackage($packageCode, $content, $action, true);
        });

        static::routePost(_ajaxPath() . $packageCode . "/p/{content}/{action}", function($content, $action) use ($packageCode) {
            return _loadPackage($packageCode, $content, $action, true);
        });

        static::routeGet(_ajaxPath() . $packageCode . "/p/{content}", function($content) use ($packageCode) {
            return _loadPackage($packageCode, $content, "", true);
        });

        static::routeGet($packageCode . "/{controller}/{content}", function($controller, $content) use ($packageCode) {
            return _loadController($packageCode, $controller, $content, ACTION_BROWSE, false);
        });

        static::routeGet(_ajaxPath() . $packageCode . "/{controller}/{content}", function($controller, $content) use ($packageCode) {
            return _loadController($packageCode, $controller, $content, ACTION_BROWSE, true);
        });

        static::routeGet(_ajaxPath() . $packageCode . "/{controller}/{content}/{action}", function($controller, $content, $action) use ($packageCode) {
            return _loadController($packageCode, $controller, $content, $action, true);
        });

        static::routePost(_ajaxPath() . $packageCode . "/{controller}/{content}/{action}", function($controller, $content, $action) use ($packageCode) {
            return _loadController($packageCode, $controller, $content, $action, true);
        });

        if(env('APP_DEBUG')) {
            static::routePost($packageCode . "/{controller}/{content}/{action}", function($controller, $content, $action) use ($packageCode) {
                return _loadController($packageCode, $controller, $content, $action, false);
            });

            static::routeGet($packageCode . "/{controller}/{content}/{action}", function($controller, $content, $action) use ($packageCode) {
                return _loadController($packageCode, $controller, $content, $action, false);
            });
        }

    }
}