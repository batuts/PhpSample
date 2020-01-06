<?php
/**
 * Created by PhpStorm.
 * User: JDC
 * Date: 7/31/19
 * Time: 2:24 PM
 */

namespace HappyUX\Controllers;

use HappyUX\Controllers\Traits\PageBuilder;
use HappyUX\UXRender\Support\BuilderDefCreatorTrait;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;

abstract class JCPageController extends JCController {
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    use PageBuilder, BuilderDefCreatorTrait;

    protected $viewName = "fullwidth";
}

