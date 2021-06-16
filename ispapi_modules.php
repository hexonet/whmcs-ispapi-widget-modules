<?php

/**
 * WHMCS ISPAPI Modules Dashboard Widget
 *
 * This Widget allows to display your installed ISPAPI modules and check for new versions.
 *
 * @see https://github.com/hexonet/whmcs-ispapi-widget-modules/wiki/
 *
 * @copyright Copyright (c) Kai Schwarz, HEXONET GmbH, 2019
 * @license https://github.com/hexonet/whmcs-ispapi-widget-modules/blob/master/LICENSE/ MIT License
 */

namespace WHMCS\Module\Widget;

use App;
use WHMCS\Module\Registrar\Ispapi\Ispapi;

add_hook('AdminHomeWidgets', 1, function () {
    return new IspapiModulesWidget();
});

/**
 * ISPAPI Modules Widget.
 */
class IspapiModulesWidget extends \WHMCS\Module\AbstractWidget
{
    protected $title = 'HEXONET ISPAPI Modules Overview';
    protected $description = '';
    protected $weight = 150;
    protected $columns = 1;
    protected $cache = false;
    protected $cacheExpiry = 120;
    protected $requiredPermission = '';
    protected $map = [
        "ispapibackorder" => [
            "id" => "whmcs-ispapi-backorder",
            "name" => "Backorder",
            "type" => "addon", // type (registrar, addon)
            "class" => "",
            "deprecated" => false,
            "prio" => 8
        ],
        "ispapipremiumdns" => [
            "id" => "whmcs-ispapi-premiumdns",
            "name" => "Premium DNS",
            "type" => "server",
            "class" => "",
            "deprecated" => true,
            "prio" => 6
        ],
        "ispapissl" => [
            "id" => "whmcs-ispapi-ssl",
            "name" => "SSL",
            "type" => "addon",
            "class" => "",
            "deprecated" => false,
            "prio" => 7
        ],
        "ispapidomaincheck" => [
            "id" => "whmcs-ispapi-domainchecker",
            "name" => "Domain Checker",
            "type" => "addon",
            "class" => "",
            "deprecated" => [
                "notice" => "Module is no longer maintained as of the new \"Registrar TLD Sync Feature\" Feature of WHMCS. ",
                "url" => "https://docs.whmcs.com/Registrar_TLD_Sync",
                "case" => "whmcs",
                "whmcs" => "7.10",
                "replacement" => "whmcs-ispapi-registrar"
                ],
            "prio" => 9
        ],
        "ispapidpi" => [
            "id" => "whmcs-ispapi-pricingimporter",
            "name" => "Price Importer",
            "type" => "addon",
            "class" => "",
            "deprecated" => [
                "case" => "product", # case of product deprecation
                "notice" => "Product stopped on 1st of April 2021. You can still manage your existing Premium DNS Zones and their Resource Records. Ordering new ones will fail.",
                "url" => "https://www.hexonet.net/blog/dns-to-serve-you-better",
                "replacement" => "whmcs-dns"
            ],
            "replacedby" => "ispapiimporter",
            "prio" => 5
        ],
        "ispapi" => [
            "id" => "whmcs-ispapi-registrar",
            "name" => "Registrar",
            "type" => "registrar",
            "class" => "",
            "deprecated" => false,
            "prio" => 10
        ],
        "ispapidomainimport" => [
            "id" => "whmcs-ispapi-domainimport",
            "name" => "Domain Importer",
            "type" => "addon",
            "class" => "",
            "deprecated" => true,
            "replacedby" => "ispapiimporter",
            "prio" => 4
        ],
        "ispapiimporter" => [
            "id" => "whmcs-ispapi-importer",
            "name" => "ISPAPI Importer",
            "type" => "addon",
            "class" => "",
            "deprecated" => false,
            "prio" => 3
        ],
        "ispapiwidgetaccount" => [
            "id" => "whmcs-ispapi-widget-account",
            "name" => "Account Widget",
            "type" => "widget",
            "class" => "IspapiAccountWidget",
            "deprecated" => false,
            "prio" => 2
        ],
        "ispapiwidgetmodules" => [
            "id" => "whmcs-ispapi-widget-modules",
            "name" => "Modules Widget",
            "type" => "widget",
            "class" => "IspapiModulesWidget",
            "deprecated" => false,
            "prio" => 0
        ],
        "ispapiwidgetmonitoring" => [
            "id" => "whmcs-ispapi-widget-monitoring",
            "name" => "Monitoring Widget",
            "type" => "widget",
            "class" => "IspapiMonitoringWidget",
            "deprecated" => false,
            "prio" => 1
        ]
    ];
    const VERSION = "2.1.1";

    /**
     * return html code for error case specified by given error message
     * @param string $errMsg error message to show
     * @return string html code
     */
    private function returnError($errMsg)
    {
        return <<<EOF
                <div class="widget-content-padded widget-billing">
                    <div class="color-pink">$errMsg</div>
                </div>
                EOF;
    }

    /**
     * get github module data by module id
     * @param string $moduleid github repository id e.g. whmcs-ispapi-ssl
     */
    private function getGHModuleData($moduleid)
    {
        if (!array_key_exists($moduleid, $this->map)) {
            return $moduleid;
        }
        return $this->map[$moduleid];
    }

    /**
     * get whmcs module version by given module id
     * @param string $whmcsmoduleid whmcs module id e.g. ispapidpi
     * @return string
     */
    private function getWHMCSModuleVersion($whmcsmoduleid)
    {
        static $modules = null;

        if (is_null($modules)) {
            $modules = Ispapi::getModuleVersions();
        }

        if (empty($modules[$whmcsmoduleid])) {
            return "0.0.0";
        }

        return $modules[$whmcsmoduleid];
    }

    /**
     * get module data
     * @param string $whmcsmoduleid whmcs module id
     * @param string $moduletype whmcs module type (registrars, addons, servers, widgets)
     * @return array|boolean
     */
    private function getModuleData($whmcsmoduleid, $moduletype, $active = true)
    {
        $ghdata = $this->getGHModuleData($whmcsmoduleid);
        $moduleid = $ghdata["id"];
        $priority = $ghdata["prio"];
        $name = $ghdata["name"];
        $type = $ghdata["type"];
        $class = $ghdata["class"];
        $deprecated = $ghdata["deprecated"];
        $current_version = $this->getWHMCSModuleVersion($whmcsmoduleid);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_TIMEOUT => 3,
            CURLOPT_HEADER => 0,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_USERAGENT => 'ISPAPI MODULES WIDGET',
            CURLOPT_URL => "https://raw.githubusercontent.com/hexonet/$moduleid/master/release.json"
        ]);
        // get current version number and date from github
        // example: {
        //     "version": "1.6.7",
        //     "date": "2021-03-30"
        //  }
        $d = curl_exec($ch);
        curl_close($ch);
        if ($d !== false) {
            $d = json_decode($d, true);//404 could happen and will be returned as string
            $latest_version = $d["version"];
            if ($d !== null) {
                return [
                    "id" => $moduleid,
                    "type" => $type,
                    "class" => $class,
                    "active" => $active,
                    "module_type" => $moduletype,
                    "whmcsid" => $whmcsmoduleid,
                    "prio" => $priority,
                    "name" => $name,
                    "version_latest" => $latest_version,
                    "version_used" => $current_version,
                    "deprecated" => $deprecated,
                    "urls" => [
                        //"logo" => $logopath,
                        "github" =>  "https://github.com/hexonet/" . $moduleid,
                        "download" => "https://github.com/hexonet/" . $moduleid . "/raw/master/" . $moduleid . "-latest.zip",
                        "documentation" => "https://centralnic-reseller.github.io/centralnic-reseller/docs/hexonet/whmcs/" . $moduleid
                    ]
                ];
            }
        }
        return false;
    }

    private function orderByPriority($a, $b)
    {
        if ($a["prio"] == $b["prio"]) {
            return 0;
        }
        return ($a["prio"] < $b["prio"]) ? 1 : -1;
    }

    /**
     * generate widget's html output
     * @param array $data input data (from getData method)
     * @return string html code
     */
    public function generateOutput($modules)
    {
        $action = App::getFromRequest('action');
        if ($action !== "") {
            //$setting = \WHMCS\Config\Setting::setValue("ispapiMonitoringWidget", $status);
            //$success = $setting::getValue("ispapiMonitoringWidget") === $status;
            $module = App::getFromRequest('module');
            $type = App::getFromRequest('type');
            // Activate
            if ($action == "activate") {
                $command = 'ActivateModule';
                $postData = array(
                    'moduleName' => $module,
                    'moduleType' => $type,
                );
                $results = localAPI($command, $postData);
                // todo - check the response
                return [
                    "success" => true,
                    "module" => $module,
                    "type" => $type,
                    "result" => $results
                ];
            } elseif ($action == "deactivate") {
                $command = 'DeactivateModule';
                $postData = array(
                    'moduleName' => $module,
                    'moduleType' => $type
                );
                $results = localAPI($command, $postData);
                // todo - check the response
                return [
                    "success" => true,
                    "type" => $type,
                    "module" => $module,
                    "result" => $results
                ];
            } else {
                return [
                    "success" => false,
                    "msg" => 'action unknown'
                ];
            }
        } else {
            if (empty($modules)) {
                return $this->returnError('No active ISPAPI Modules found.');
            }

            usort($modules, [$this, "orderByPriority"]);
            // get modules by state
            $installed = array();
            $not_active_or_installed = array();
            $deprecated = array();

            while (!empty($modules)) {
                $module = array_shift($modules);
                $data = array();
                $data['name'] = $module["name"];
                $data['type'] = $module["type"];
                $data['token'] = generate_token("link");
                $data['class'] = $module["class"];
                $data['whmcsmoduleid'] = $module["whmcsid"];
                $data['version_used'] = $module["version_used"];
                $data['version_latest'] = $module["version_latest"];
                $data['no_latest_used'] = (version_compare($module["version_used"], $module["version_latest"]) < 0);
                $data['documentation_link'] = $module["urls"]["documentation"];
                $data['download_link'] = $module["urls"]["download"];
                $data['active'] = $module['active'];
                // check module type
                if (gettype($module["deprecated"]) == "boolean" && $module["deprecated"] === true) {
                    $data['case'] = 'default';
                    $deprecated[] = $data;
                }
                elseif (gettype($module["deprecated"]) == "array"){
                    // prepare data
                    $notice = $module["deprecated"]["notice"];
                    $url = $module["deprecated"]["url"];
                    $replacement = $module["deprecated"]["replacement"];
                    $case = $module["deprecated"]["case"];
                    // case 1: Product Deprecation.
                    if($case == 'product'){
                        $data['case'] = $case;
                        $data['notice'] = $notice;
                        $data['url'] = $url;
                        $data['replacement'] = $replacement;
                        $deprecated[] = $data;
                    }
                    // case 2: Deprecation since WHMCS vX.Y.Z
                    if($case == 'whmcs'){
                        $data['case'] = $case;
                        $data['whmcs_version'] = $module["deprecated"]["whmcs"];
                        $data['notice'] = $notice;
                        $data['url'] = $url;
                        $data['replacement'] = $replacement;
                        $deprecated[] = $data;
                    }
                } 
                elseif (!$module["active"] || ($module["version_used"] === "0.0.0")) {
                    // not active
                    // not installed
                    $not_active_or_installed[] = $data;
                } 
                else {
                    // active
                    $installed[] = $data;
                }
            }
            // var_dump($deprecated, $not_active, $not_installed, $active);
            $content = $this->getSmartyHTML($installed, $not_active_or_installed, $deprecated);
            return $content;
        }
    }
    private function getSmartyHTML($installed, $not_active_or_installed, $deprecated)
    {
        // TODO: handle the case where there is not modules in a specific type
        $smarty = new \WHMCS\Smarty(true);
        // assign input values
        $smarty->assign('installed', $installed);
        $smarty->assign('not_active_or_installed', $not_active_or_installed);
        $smarty->assign('deprecated', $deprecated);
        // get required js code
        $jscript = self::generateOutputJS();
        $smarty->assign('jscript', $jscript);
        // parse content
        $content = '<div class="widget-content-padded" style="max-height: 450px">
                        <div class="row small">
                            <ul class="nav nav-tabs">
                                <li class="active"><a data-toggle="tab" href="#tab1">Installed</a></li>
                                <li class=""><a data-toggle="tab" href="#tab2">Not Installed/Activated</a></li>
                                <li class=""><a data-toggle="tab" href="#tab3">Deprecated</a></li>
                            </ul>
                            <div class="tab-content small">
                                <div id="tab1" class="tab-pane fade in active">
                                    <table class="table table-bordered table-condensed" style="margin-top: 4px;">
                                        <thead>
                                            <tr>
                                            <th scope="col">Name</th>
                                            <th scope="co">Type</th>
                                            <th scope="col">Version</th>
                                            <th scope="col">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {foreach $installed as $module}
                                                <tr>
                                                   <td>{$module.name}</td>
                                                   <td>{$module.type}</td>
                                                   <td>
                                                        {if $module.no_latest_used}
                                                            <a class="textred small" href="{$module.download_link}">v{$module.version_used}</a>
                                                        {else}
                                                            <span class="textgreen small">v{$module.version_used}
                                                        {/if}
                                                    </td>
                                                   <td>
                                                        <button class="btn btn-default btn-xs" onclick="window.open(\'{$module.documentation_link}\');" data-toggle="tooltip" data-placement="top" title="See documentation" >
                                                                <i class="fas fa-book"></i>
                                                        </button>
                                                        {if $module.type != \'widget\'}
                                                            <button class="btn btn-danger btn-xs deactivatebtn" m-class="{$module.class}" m-type="{$module.type}" module="{$module.whmcsmoduleid}" token="{$module.token}" data-toggle="tooltip" data-placement="top" title="Deactivate">
                                                                <i class="fas fa-minus-square"></i>
                                                            </button>
                                                        {/if}
                                                        {if $module.no_latest_used}
                                                            <button class="btn btn-success btn-xs" onclick="window.open(\'{$module.download_link}\');" data-toggle="tooltip" data-placement="top" title="Download update">
                                                                <i class="fas fa-arrow-down"></i>
                                                            </button>
                                                        {/if}
                                                    </td>
                                               </tr>
                                            {foreachelse}
                                                <span class="text-center">No modules found.</span>
                                            {/foreach}
                                        </tbody>
                                    </table>
                                </div>
                                <div id="tab2" class="tab-pane fade">
                                    <table class="table table-bordered table-condensed" style="margin-top: 4px;">
                                        <thead>
                                            <tr>
                                            <th scope="col">Name</th>
                                            <th scope="co">Type</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                             {foreach $not_active_or_installed as $module}
                                                <tr>
                                                   <td>{$module.name}</td>
                                                   <td>{$module.type}</td>    
                                                    {if not $module.active}
                                                        <td class="textred small">Not Acitve</td>
                                                        <td>
                                                            <button class="btn btn-default btn-xs" onclick="window.open(\'{$module.documentation_link}\');" data-toggle="tooltip" data-placement="top" title="See documentation" >
                                                                <i class="fas fa-book"></i>
                                                            </button>
                                                            <button class="btn btn-success btn-xs activatebtn" m-class="{$module.class}" m-type="{$module.type}" module="{$module.whmcsmoduleid}" token="{$module.token}" data-toggle="tooltip" data-placement="top" title="Activate">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                        </td>
                                                    {else}
                                                        <td class="textred small">Not Installed</td>
                                                        <td>
                                                            <button class="btn btn-default btn-xs" onclick="window.open(\'{$module.documentation_link}\');" data-toggle="tooltip" data-placement="top" title="See documentation" >
                                                                <i class="fas fa-book"></i>
                                                            </button>
                                                            <button class="btn btn-success btn-xs" onclick="window.open(\'{$module.download_link}\');" data-toggle="tooltip" data-placement="top" title="Download">
                                                                <i class="fas fa-arrow-down"></i>
                                                            </button>
                                                        </td>
                                                    {/if}
                                                </tr>
                                            {foreachelse}
                                                <span class="text-center">No modules found.</span>
                                            {/foreach}
                                        </tbody>
                                    </table>
                                </div>
                                <div id="tab3" class="tab-pane fade">
                                     <table class="table table-bordered table-condensed" style="margin-top: 4px;">
                                        <thead>
                                            <tr>
                                            <th scope="col">Name</th>
                                            <th scope="co">Type</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {foreach $deprecated as $module}
                                                <tr>
                                                    <td>{$module.name}</td>
                                                    <td>{$module.type}</td>
                                                    <td><span class="textred small">Deprecated</span></td>
                                                    <td>
                                                        <button class="btn btn-danger btn-xs" onclick="window.open(\'/admin/configaddonmods.php\');" data-toggle="tooltip" data-placement="top" title="Uninstall">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                        {if $module.case != \'default\'}
                                                            <button class="btn btn-warning btn-xs toggleDetailsView" m-type = "{$module.whmcsmoduleid}-details" data-toggle="tooltip" data-placement="top" title="Show Details">
                                                                <i class="fas fa-caret-down"></i>
                                                            </button>
                                                        {/if}
                                                    </td>
                                                </tr>
                                                {if $module.case != \'default\'}
                                                    <tr>
                                                        <td id="{$module.whmcsmoduleid}-details" class="bg-warning" colspan="4" style="display: none;">
                                                            {if $module.case == \'product\'}
                                                                {$module.notice}.
                                                                Read more: <a href="{$module.url}" target=_blank>here.</a>
                                                                {if $module.replacement}
                                                                Replacement available: {$module.replacement}.
                                                            {/if}
                                                            {else} 
                                                                Deprecated since WHMCS {$module.whmcs_version}. 
                                                                {$module.notice}
                                                                Read more: <a href="{$module.url}" target=_blank>here.</a>
                                                                {if $module.replacement}
                                                                    Replacement available: {$module.replacement}.
                                                                {/if}
                                                            {/if}
                                                        </td>
                                                    </tr>
                                                {/if}
                                            {foreachelse}
                                                <span class="text-center">No modules found.</span>
                                            {/foreach}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    {$jscript}
                    ';
        return $smarty->fetch('eval:' . $content);
    }
    /**
     * Fetch data that will be provided to generateOutput method
     * @return array|null data array or null in case of an error
     */
    public function getData()
    {
        global $CONFIG;
        $modules = [];
        $installed_modules_ids = [];

        // get registrar module versions
        $registrar = new \WHMCS\Module\Registrar();
        foreach ($registrar->getList() as $module) {
            if (preg_match("/^ispapi/i", $module)) {
                $registrar->load($module);
                if ($registrar->isActivated()) {
                    $md = $this->getModuleData($module, "registrars", true);
                    if ($md !== false) {
                        $modules[] = $md;
                        $installed_modules_ids[] = $module;
                    }
                }
            }
        }

        // get addon module versions
        $activemodules = array_filter(explode(",", $CONFIG["ActiveAddonModules"]));
        $addon = new \WHMCS\Module\Addon();
        foreach ($addon->getList() as $module) {
            if (in_array($module, $activemodules) && preg_match("/^ispapi/i", $module) && !preg_match("/\_addon$/i", $module)) {
                $md = $this->getModuleData($module, "addons", true);
                if ($md !== false) {
                    $modules[] = $md;
                    $installed_modules_ids[] = $module;
                }
            }
        }

        // get server module versions
        $server = new \WHMCS\Module\Server();
        foreach ($server->getList() as $module) {
            if (preg_match("/^ispapi/i", $module)) {
                $md = $this->getModuleData($module, "servers", true);
                if ($md !== false) {
                    $modules[] = $md;
                    $installed_modules_ids[] = $module;
                }
            }
        }

        // get widget module versions
        $widget = new \WHMCS\Module\Widget();
        foreach ($widget->getList() as $module) {
            if (preg_match("/^ispapi/i", $module)) {
                $md = $this->getModuleData(str_replace("_", "widget", $module), "widgets", true);
                if ($md !== false) {
                    $modules[] = $md;
                    $installed_modules_ids[] = str_replace("_", "widget", $module);
                }
            }
        }
        // add not installed modules
        $ourmodules = $this->getModulesFiles();
        $not_installed_modules = [];
        foreach (array_keys($this->map) as $key) {
            // not activated modules
            if (!in_array($key, $installed_modules_ids) && in_array($key, $ourmodules)) {
                $not_installed_modules[] = $key;
                $md = $this->getModuleData($key, "NA", false);
                if ($md !== false) {
                    $modules[] = $md;
                }
            } elseif (!in_array($key, $installed_modules_ids)) {
                // not installed modules
                // checked based on version number
                $not_installed_modules[] = $key;
                $md = $this->getModuleData($key, "NA", true);
                if ($md !== false) {
                    $modules[] = $md;
                }
            } else {
            // do nothing, it's a third party module
            }
        }
        return $modules;
    }
    private function getModulesFiles()
    {
        // of type= registrar, addon, widget
        $modules = array();
        // addons
        if (is_dir(ROOTDIR . "/modules/addons/")) {
            $dh = opendir(ROOTDIR . "/modules/addons/");
            while (false !== ($file = readdir($dh))) {
                $modfilename = ROOTDIR . "/modules/addons/" . $file . "/" . $file . ".php";
                if (is_file($modfilename)) {
                    $modules[] = $file;
                }
            }
        }
        // registrars
        if (is_dir(ROOTDIR . "/modules/registrars/")) {
            $dh = opendir(ROOTDIR . "/modules/registrars/");
            while (false !== ($file = readdir($dh))) {
                $modfilename = ROOTDIR . "/modules/registrars/" . $file . "/" . $file . ".php";
                if (is_file($modfilename)) {
                    $modules[] = $file;
                }
            }
        }
        // widgets
        if (is_dir(ROOTDIR . "/modules/widgets/")) {
            $dh = opendir(ROOTDIR . "/modules/widgets/");
            while (false !== ($file = readdir($dh))) {
                $modfilename = ROOTDIR . "/modules/widgets/" . $file . ".php";
                if (is_file($modfilename)) {
                    $modules[] = $file;
                }
            }
        }
        return $modules;
    }
    private static function generateOutputJS()
    {
        return <<<EOF
        <script type="text/javascript">
            const loadingIcon = '<i class="fas fa-spinner fa-spin"></i>';
            const defaultIcon = $(this).html();
            // deactivate a modules
            $('.deactivatebtn').on('click', function (event) {
                // set loading icon
                $(this).html(loadingIcon);
                //event.preventDefault();
                const type = $(this).attr("m-type");
                // case registrar: user internal API
                if (type == 'registrar'){
                    WHMCS.http.jqClient.post(
                    WHMCS.adminUtils.getAdminRouteUrl('/widget/refresh&widget=IspapiModulesWidget&module='+ $(this).attr("module") + '&type=' + $(this).attr("m-type") + '&action=deactivate'),
                    function (data) {
                        refreshWidget('IspapiModulesWidget', 'refresh=1');
                        const results = JSON.parse(data.widgetOutput);
                        console.log(results);
                    },
                    'json'
                )
                }
                else if (type == 'addon'){
                    const module = $(this).attr("module");
                    const token = $(this).attr("token");
                    const url= '/admin/configaddonmods.php?action=deactivate&module=' + module + token;
                     var jqxhr = $.get( url, function() {
                            refreshWidget('IspapiModulesWidget', 'refresh=1');
                        })
                        .fail(function(error) {
                            alert( "error: " + error );
                        })
                }
                else if (type == 'widget'){
                    const module = $(this).attr("m-class");
                    const url= 'index.php?rp=/admin/widget/display/toggle/' + module;
                     var jqxhr = $.get( url, function() {
                            location.reload();  
                            // refreshWidget(module, 'refresh=1');
                            // if (module != 'IspapiModulesWidget') {
                            //     refreshWidget('IspapiModulesWidget', 'refresh=1');
                            // }
                        })
                        .fail(function(error) {
                            alert( "error: " + error );
                        })
                }
                else{
                    $(this).html(defaultIcon);
                    alert(type + ' not supported');
                }
            })
            // activate logic
            $('.activatebtn').on('click', function (event) {
                // set loading icon
                $(this).html(loadingIcon);
                //event.preventDefault();
                const type = $(this).attr("m-type");
                // case registrar: user internal API
                if (type == 'registrar'){
                    WHMCS.http.jqClient.post(
                    WHMCS.adminUtils.getAdminRouteUrl('/widget/refresh&widget=IspapiModulesWidget&module='+ $(this).attr("module") + '&type=' + $(this).attr("m-type") + '&action=activate'),
                    function (data) {
                        refreshWidget('IspapiModulesWidget', 'refresh=1');
                        const results = JSON.parse(data.widgetOutput);
                        console.log(results);
                    },
                    'json'
                )
                }
                else if (type == 'addon'){
                    const module = $(this).attr("module");
                    const token = $(this).attr("token");
                    const url= '/admin/configaddonmods.php?action=activate&module=' + module + token;
                     var jqxhr = $.get( url, function() {
                            refreshWidget('IspapiModulesWidget', 'refresh=1');
                        })
                        .fail(function(error) {
                            alert( "error: " + error );
                        })
                }
                else if (type == 'widget'){
                    const module = $(this).attr("m-class");
                    const url= 'index.php?rp=/admin/widget/display/toggle/' + module;
                     var jqxhr = $.get( url, function() {
                            location.reload();
                            // refreshWidget(module, 'refresh=1');
                            // if (module != 'IspapiModulesWidget') {
                            //     refreshWidget('IspapiModulesWidget', 'refresh=1');
                            // }
                        })
                        .fail(function(error) {
                            alert( "error: " + error );
                        })
                }
                else{
                    $(this).html(defaultIcon);
                    alert(type + ' not supported');
                }
            })
            // toggle the details view
            $('.toggleDetailsView').on('click', function (event) {
                const module = $(this).attr("m-type");
                $("#"+module).fadeToggle();
                $(this).children('.fa-caret-up, .fa-caret-down').toggleClass("fa-caret-up fa-caret-down");
            })
            function toggleDetailsView() {
                
            }
        </script>
        EOF;
    }
}