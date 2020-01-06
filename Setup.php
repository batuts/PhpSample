<?php
/**
 * Created by PhpStorm.
 * User: JDC
 * Date: 8/16/19
 * Time: 8:56 AM
 */

namespace App\Http\Controllers\Settings;


use SampleCode\Controllers\JCModuleController;

use Illuminate\Support\Facades\DB;

class Setup extends JCModuleController {
    const CRUD_OU = "ou";
    const CRUD_OULIST = "ouList";
    const CRUD_OU_LOOKUP = "ouLookup";

    const CRUD_COMPANY = "company";

    protected function getCrudList() {
        return [
            ["crudId"=>static::CRUD_OULIST, "role"=>CRUD_ROLE_MASTER, "parentCrud"=>""],
            ["crudId"=>static::CRUD_OU, "role"=>CRUD_ROLE_DETAIL, "parentCrud"=>static::CRUD_OU],
        ];
    }

    protected $publishedModules = [
        "manageOU"=>"Organizational Units",
        "companyList"=>"Company List",
    ];


    function getCRUDModel($crudId) {
        switch($crudId) {
            case static::CRUD_OU:
                return $this->Model("OU");
                break;
            case static::CRUD_OU_LOOKUP:
                $Id = $this->get("id");
                return DB::select("WITH CTE AS " .
                    "    (SELECT Id, UnitName, ParentId, CompanyId, Id as [Root], 1 Depth, " .
                    "            CAST(Id AS VARCHAR) NodePath " .
                    "     FROM OU " .
                    "     WHERE Id = '{$Id}'" .
                    "     UNION ALL " .
                    "     SELECT OU.Id, OU.UnitName, OU.ParentId, OU.CompanyId, [Root], Depth + 1, " .
                    "            CAST(NodePath + '.' + CAST(OU.Id AS VARCHAR) AS VARCHAR) " .
                    "     FROM CTE JOIN OU ON CTE.ParentId = OU.Id) " .
                    "SELECT * " .
                    "FROM CTE WHERE Id <> '{$Id}' ORDER BY Root, NodePath ");
                break;
            case static::CRUD_OULIST:
                $ownerId = auth()->user()->getOwnerKey();
                return DB::select("WITH CTE AS " .
                                  "    (SELECT Id, UnitName, ParentId, CompanyId, Id as [Root], 1 Depth, " .
                                  "            CAST(Id AS VARCHAR) NodePath " .
                                  "     FROM OU " .
                                  "     WHERE ISNULL(ParentId, 0) = 0 AND " . env('OWNER_COLUMN_NAME', "OwnerId") . " = '{$ownerId}'" .
                                  "     UNION ALL " .
                                  "     SELECT OU.Id, OU.UnitName, OU.ParentId, OU.CompanyId, [Root], Depth + 1, " .
                                  "            CAST(NodePath + '.' + CAST(OU.Id AS VARCHAR) AS VARCHAR) " .
                                  "     FROM CTE JOIN OU ON CTE.Id = OU.ParentId) " .
                                  "SELECT * " .
                                  "FROM CTE ORDER BY Root, NodePath ");
                    break;
        }
    }

    protected function onPageInitialize() {
    }

    protected function getTitle() {
        return "Application Setup";
    }

    protected function getContent() {
        // removed
    }

    /*
     * Display company details
     */
    protected function companies($action) {
        return [
            huxPaneType=>PANE_TYPE_FORM, hhbAction=>$action, hhbModel=>$this->getCRUDModel(static::CRUD_COMPANY),
            "crudId"=>static::CRUD_COMPANY, "dataId"=>"1",
            "validate"=>[
                "CompanyCode"=>["formName"=>"CompanyCode", "type"=>FORM_STRING, "validate"=>"required|string|max:8"],
                "CompanyName"=>["formName"=>"CompanyName", "type"=>FORM_STRING, "validate"=>"required|string|max:50"],
                "FirstName"=>["formName"=>"FirstName", "type"=>FORM_STRING, "validate"=>"required|string|max:50"],
                "MiddleName"=>["formName"=>"MiddleName", "type"=>FORM_STRING, "validate"=>"required|string|max:50"],
                "LastName"=>["formName"=>"LastName", "type"=>FORM_STRING, "validate"=>"required|string|max:50"],
                "Email"=>["formName"=>"Email", "type"=>FORM_STRING, "validate"=>"required|email|max:50"],
            ],
            hhbPanes=>[
                [
                    huxPaneType=>"fields", "border"=>false, hhbWidth=>[4, 8],
                    hhbPanes=>[
                        [hhbTitle=>"Company Code", "type"=>FORM_EDIT, hhbFieldName=>"CompanyCode"],
                        [hhbTitle=>"Company Name", "type"=>FORM_EDIT, hhbFieldName=>"CompanyName"],
                        [hhbTitle=>"First Name", "type"=>FORM_EDIT, hhbFieldName=>"FirstName"],
                        [hhbTitle=>"Middle Name", "type"=>FORM_EDIT, hhbFieldName=>"MiddleName"],
                        [hhbTitle=>"Last Name", "type"=>FORM_EDIT, hhbFieldName=>"LastName"],
                        [hhbTitle=>"Email", "type"=>FORM_EMAIL, hhbFieldName=>"Email"],
                    ]
                ]
            ]
        ];
    }

    protected function companyList($action) {
        return [
            huxPaneType=>PANE_TYPE_COLUMN,
            hhbPanes=>[
                [
                    huxPaneType=>PANE_TYPE_COLUMN, hhbWidth=>5, hhbPanes=>[
                        [ huxPaneType=>PANE_TYPE_TITLE, hhbTitle=>"Company", "isFirst"=>true,
                            hhbPanes=>[
                                [ huxPaneType=>PANE_TYPE_CRUDTOOLBAR, hhbCrudId=>static::CRUD_COMPANY, hhbPath=>$this->getBaseActionPath(), ],
                            ]
                        ],
                        [ huxPaneType=>PANE_TYPE_GRID, "id"=>static::CRUD_COMPANY . "grid",
                            hhbCrudId=>static::CRUD_COMPANY,
                            hhbOptions=>[goRowSelect=>true, goReadOnly=>true, goShowSelection=>false,
                                goHideFooter=>true, goShowActive=>false, goPageSize=>25],
                            hhbCrudDef=>[
                                crudDefMode=>crudModeNav,
                                crudDefRole=>CRUD_ROLE_MASTER,
                                crudDefKeying=>crudKeyNone,
                            ],
                            dtdParentKey=>$this->get(dtdParentKey),
                            hhbPanes=>[
                                [hhbFieldName=>"CompanyName", hhbTitle=>"Company Name",
                                    "header"=>["class"=>"col-md-12"],
                                    "options"=>["CanEdit"=>true, "Disabled"=>false]
                                ],
                            ],
                            hhbDataDef=>[
                                dtdSource=>DATASOURCE_MODEL,
                                dtdData=>$this->getModelPath("Company"),
                                dtdDataKey=>"CompanyCode",
                                dtdLiveMode=>false, // moved from root
                            ]
                        ],
                    ],
                ],
                [
                    // Content of this page is automatically loaded thru ajax call
                    huxPaneType=>PANE_TYPE_AJAX, hhbWidth=>5, hhbCrudId=>static::CRUD_COMPANY, hhbPanes => [],
                ],
                [
                    huxPaneType=>PANE_TYPE_COLUMN, hhbWidth=>2, hhbPanes=>[ ],
                ],
            ]
        ];
    }

    protected function ou($action) {
        return [
            huxPaneType=>"form", "mode"=>$action, "model"=>$this->getCRUDModel(static::CRUD_OU),
            "crudId"=>static::CRUD_OU, "dataId"=>"1",
            "fields"=>[
                "UnitName"=>["formName"=>"UnitName", "type"=>FORM_STRING, "validate"=>"required|string|max:50"],
                "Head1"=>["formName"=>"Head1", "type"=>FORM_STRING, "validate"=>"required|string|max:50"],
                "Head2"=>["formName"=>"Head2", "type"=>FORM_STRING, "validate"=>"required|string|max:50"],
                "CompanyId"=>["formName"=>"CompanyId", "type"=>FORM_STRING, "validate"=>"required"],
                "ParentId"=>["formName"=>"ParentId", "type"=>FORM_STRING, "validate"=>"required"],
            ],
            hhbPanes=>[
                [
                    huxPaneType=>PANE_TYPE_TITLE, hhbTitle=>"&nbsp;", hhbIsFirst=>true,
                ],
                [
                    huxPaneType=>"fields", "border"=>false, hhbWidth=>[3, 9],
                    hhbPanes=>[
                        [hhbTitle=>"Unit Name", hhbFieldType=>FORM_EDIT, hhbFieldName=>"UnitName"],
                        [hhbTitle=>"Unit Appraiser", hhbFieldType=>FORM_DROPDOWN, hhbFieldName=>"Head1",
                            hhbDataDef=>[
                                dtdSource=>DATASOURCE_MODEL,
                                dtdPreLoad=>true,
                                dtdData=>$this->getCRUDModel(static::CRUD_OU_LOOKUP),
                                dtdDataKey=>"Id",
                                dtdDataDesc=>"UnitName",
                            ],
                        ],
                        [hhbTitle=>"Unit Approver", hhbFieldType=>FORM_DROPDOWN, hhbFieldName=>"Head2",
                            hhbDataDef=>[
                                dtdSource=>DATASOURCE_MODEL,
                                dtdPreLoad=>true,
                                dtdData=>$this->getCRUDModel(static::CRUD_OU_LOOKUP),
                                dtdDataKey=>"Id",
                                dtdDataDesc=>"UnitName",
                            ],
                        ],
                        [hhbFieldType=>FORM_HIDDEN, hhbFieldName=>"CompanyId", "default"=>$this->get("companyId")],
                        [hhbFieldType=>FORM_HIDDEN, hhbFieldName=>"ParentId", "default"=>$this->get("newParentId")],
                    ]
                ]
            ]
        ];
    }

    protected function manageOU($action) {
        return [huxPaneType=>PANE_TYPE_COLUMN,
            hhbPanes=>[
                [huxPaneType=>PANE_TYPE_COLUMN, hhbWidth=>"5",
                    hhbPanes=>[
                        [
                            huxPaneType=>PANE_TYPE_TITLE, hhbTitle=>"Organizational Units", hhbIsFirst=>true,
                            hhbPanes=>[
                                [
                                    huxPaneType=>PANE_TYPE_CRUD, hhbCrudId=>static::CRUD_OU, hhbPath=>$this->getBaseActionPath(),
                                ],
                            ]
                        ],
                        [
                            huxPaneType=>PANE_TYPE_TREEVIEW, hhbClass=>"md-content",
                            hhbCrudId=>static::CRUD_OU,
                            hhbDataDef=>[
                                dtdData=>$this->getCRUDModel(static::CRUD_OULIST),
                                dtdSource=>DATASOURCE_MODEL,
                                dtdPreLoad=>true,
                                dtdDataKey=>"Id",
                                dtdDataDesc=>"UnitName",
                                dtdParentKey=>"ParentId",
                                dtdExtData=>["parentId"=>"ParentId", "companyId"=>"CompanyId", "newParentId"=>"Id"],
                            ]
                        ],
                    ],
                ],
                [
                    huxPaneType=>PANE_TYPE_AJAX, hhbWidth=>7, hhbCrudId=>static::CRUD_OU, hhbPanes => [],
                ],
            ]
        ];
    }

}