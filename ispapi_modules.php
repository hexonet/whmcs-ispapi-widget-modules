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
            "deprecated" => false,
            "prio" => 8
        ],
        "ispapipremiumdns" => [
            "id" => "whmcs-ispapi-premiumdns",
            "name" => "Premium DNS",
            "deprecated" => true,
            "prio" => 6
        ],
        "ispapissl" => [
            "id" => "whmcs-ispapi-ssl",
            "name" => "SSL",
            "deprecated" => false,
            "prio" => 7
        ],
        "ispapidomaincheck" => [
            "id" => "whmcs-ispapi-domainchecker",
            "name" => "Domain Checker",
            "deprecated" => false,
            "prio" => 9
        ],
        "ispapi" => [
            "id" => "whmcs-ispapi-registrar",
            "name" => "Registrar",
            "deprecated" => false,
            "prio" => 10
        ],
        "ispapidpi" => [
            "id" => "whmcs-ispapi-pricingimporter",
            "name" => "Price Importer",
            "deprecated" => true,
            "replacedby" => "ispapiimporter",
            "prio" => 5
        ],
        "ispapidomainimport" => [
            "id" => "whmcs-ispapi-domainimport",
            "name" => "Domain Importer",
            "deprecated" => false,
            "replacedby" => "ispapiimporter",
            "prio" => 4
        ],
        "ispapiimporter" => [
            "id" => "whmcs-ispapi-importer",
            "name" => "ISPAPI Importer",
            "deprecated" => false,
            "prio" => 3
        ],
        "ispapiwidgetaccount" => [
            "id" => "whmcs-ispapi-widget-account",
            "name" => "Account Widget",
            "deprecated" => false,
            "prio" => 2
        ],
        "ispapiwidgetmodules" => [
            "id" => "whmcs-ispapi-widget-modules",
            "name" => "Modules Widget",
            "deprecated" => false,
            "prio" => 0
        ],
        "ispapiwidgetmonitoring" => [
            "id" => "whmcs-ispapi-widget-monitoring",
            "name" => "Monitoring Widget",
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
    private function getModuleData($whmcsmoduleid, $moduletype)
    {
        $ghdata = $this->getGHModuleData($whmcsmoduleid);
        $moduleid = $ghdata["id"];
        $priority = $ghdata["prio"];
        $name = $ghdata["name"];
        $deprecated = $ghdata["deprecated"];
        $active = ($moduletype == 'NotActive') ? false : true;
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
            // $logopath = implode(DIRECTORY_SEPARATOR, [ ROOTDIR, "modules", $moduletype, $whmcsmoduleid, "module.png" ]);
            // if (!file_exists($logopath)) {
            //     $logopath = "https://raw.githubusercontent.com/hexonet/" . $moduleid . "/master/module.png";
            // } else {
            //     $logopath = \DI::make("asset")->getWebRoot() . "/modules/" . $moduletype . "/" . $whmcsmoduleid . "/module.png";
            // }
            $d = json_decode($d, true);//404 could happen and will be returned as string
            $latest_version = $d["version"];
            if ($d !== null) {
                return [
                    "id" => $moduleid,
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

    /**
     * get html for the given module data
     */
    private function getModuleHTML($module)
    {
        $token = generate_token("link");
        $whmcsmoduleid = $module["whmcsid"];
        $documentation_link = $module["urls"]["documentation"];
        $download_link = $module["urls"]["download"];
        $activate_button = <<<EOF
            <a style="margin: 3px;" href="/admin/configaddonmods.php?action=activate&module={$whmcsmoduleid}{$token}" class="label label-success" data-toggle="tooltip" data-placement="top" title="Activate">
                <i class="fas fa-check"></i>
            </a>
            EOF;
        $deactivate_button = <<<EOF
            <a style="margin: 3px;" href="/admin/configaddonmods.php?action=deactivate&module={$whmcsmoduleid}{$token}" class="label label-danger" data-toggle="tooltip" data-placement="top" title="Deactivate">
                <i class="fas fa-minus-square"></i>
            </a>
            EOF;
        $documentation_button = <<<EOF
            <a style="margin: 3px;" href="$documentation_link" target="_blank" class="label label-default" data-toggle="tooltip" data-placement="top" title="See documentation" >
                <i class="fas fa-book"></i>
            </a>
            EOF;
        $download_button = <<<EOF
            <a style="margin: 3px;" href="$download_link" target="_blank" class="label label-success" data-toggle="tooltip" data-placement="top" title="Download"><i class="fas fa-arrow-down"></i></a>
            EOF;
        $update_button = <<<EOF
            <a style="margin: 3px;" href="$download_link" target="_blank" class="label label-success" data-toggle="tooltip" data-placement="top" title="Download update"><i class="fas fa-sync"></i></a>
            EOF;

        $installed_buttons .= $documentation_button;
        $installed_buttons .= ($module["module_type"] != 'widgets') ? $deactivate_button : '';

        $deprecated_buttons = '<a style="margin: 3px;" href="/admin/configaddonmods.php" target="_blank" class="label label-danger" data-toggle="tooltip" data-placement="top" title="Uninstall"><i class="fas fa-trash"></i></a>';
        $not_installed_buttons = <<<EOF
                <a style="margin: 3px;" href="$documentation_link" target="_blank" class="label label-default" data-toggle="tooltip" data-placement="top" title="See documentation"><i class="fas fa-book"></i></a>
                <a style="margin: 3px;" href="$download_link" target="_blank" class="label label-success" data-toggle="tooltip" data-placement="top" title="Download"><i class="fas fa-arrow-down"></i></a>
            EOF;
        if ($module) {
            // style="overflow: auto; white-space: nowrap;"
            $tr = '<tr><td>' . $module["name"] . '</td>';
            if ($module["deprecated"]) {
                $tr .= '<td class="textred small">Deprecated</td>';
                $tr .= '<td>' . $deprecated_buttons . '</td>';
            } elseif ($module["version_used"] === "0.0.0") {
                if ($module["active"]) {
                    $tr .= '<td class="textred small">Not Installed</td>';
                    $tr .= '<td>' . $not_installed_buttons . '</td>';
                } else {
                    $tr .= '<td class="textred small">Not Acitve</td>';
                    $tr .= '<td> ' . $activate_button . '</td>';
                }
            } else {
                if (version_compare($module["version_used"], $module["version_latest"]) < 0) {
                    $tr .= '<td><a class="textred small" href="' . $download_link . '">v' . $module["version_used"] . '</a></td>';
                    $installed_buttons .= $update_button;
                } else {
                    $tr .= '<td class="textgreen small">v' . $module["version_used"] . '</td>';
                }
                // $installed_buttons .= '</div>' // close the buttons tag
                $installed_buttons .= '</div>';
                $tr .= '<td>' . $installed_buttons . '</td>';
            }
            return $tr . '</tr>';
        }
        return '<tr></tr>';
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
        if (empty($modules)) {
            return $this->returnError('No active ISPAPI Modules found.');
        }

        usort($modules, [$this, "orderByPriority"]);

        $content = '<div class="widget-content-padded" style="max-height: 450px"><div class="row small">';
        $table_start = '<table class="table table-bordered table-condensed">
                        <thead>
                            <tr>
                            <th scope="col">Widget</th>
                            <th scope="col">Version</th>
                            <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>';
        $installed .= $table_start;
        $deprecated .= $table_start;
        $not_installed .= $table_start;
        while (!empty($modules)) {
            $module = array_shift($modules);
            if ($module["deprecated"]) {
                $deprecated .= $this->getModuleHTML($module);
            } elseif ($module["version_used"] === "0.0.0") {
                $not_installed .= $this->getModuleHTML($module);
            } else {
                $installed .= $this->getModuleHTML($module);
            }
        }
        $table .= '</tbody></table>';
        $installed .= $table;
        $deprecated .= $table;
        $not_installed .= $table;
        $content .= '<ul class="nav nav-tabs">
                    <li class="active"><a data-toggle="tab" href="#tab1">Installed</a></li>
                    <li class=""><a data-toggle="tab" href="#tab2">Not Installed/Activated</a></li>
                    <li class=""><a data-toggle="tab" href="#tab3">Deprecated</a></li>
                </ul>
                <div class="tab-content small">
                    <div id="tab1" class="tab-pane fade in active">
                        ' . $installed . '
                    </div>
                    <div id="tab2" class="tab-pane fade">
                        ' . $not_installed . '
                    </div>
                    <div id="tab3" class="tab-pane fade">
                        ' . $deprecated . '
                    </div>
                </div>';
        // $content .= $installed . $deprecated . $not_installed;
        $content .= '</div></div>';

        return $content;
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
                    $md = $this->getModuleData($module, "registrars");
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
                $md = $this->getModuleData($module, "addons");
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
                $md = $this->getModuleData($module, "servers", $server);
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
                $md = $this->getModuleData(str_replace("_", "widget", $module), "widgets");
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
                $md = $this->getModuleData($key, "NotActive");
                if ($md !== false) {
                    $modules[] = $md;
                }
            } elseif (!in_array($key, $installed_modules_ids)) {
                $not_installed_modules[] = $key;
                $md = $this->getModuleData($key, "NA");
                if ($md !== false) {
                    $modules[] = $md;
                }
            } else {
            // do nothing, it's a third party module
            }
        }
        // add non active modules
        // var_dump($installed_modules_ids, $not_installed_modules, $modules);
        return $modules;
    }
    private function getModulesFiles()
    {
        $addon_modules = $addonmodulehooks = array();
        if (is_dir(ROOTDIR . "/modules/addons/")) {
            $dh = opendir(ROOTDIR . "/modules/addons/");
            while (false !== ($file = readdir($dh))) {
                $modfilename = ROOTDIR . "/modules/addons/" . $file . "/" . $file . ".php";
                if (is_file($modfilename)) {
                    // require $modfilename;
                    // $configarray = call_user_func($file . "_config");
                    $addon_modules[] = $file;
                }
            }
        }
        return $addon_modules;
    }
}
