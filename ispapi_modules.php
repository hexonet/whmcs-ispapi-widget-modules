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
            "status" => true,
            "prio" => 8
        ],
        "ispapipremiumdns" => [
            "id" => "whmcs-ispapi-premiumdns",
            "name" => "Premium DNS",
            "status" => true,
            "prio" => 6
        ],
        "ispapissl" => [
            "id" => "whmcs-ispapi-ssl",
            "name" => "SSL",
            "status" => true,
            "prio" => 7
        ],
        "ispapidomaincheck" => [
            "id" => "whmcs-ispapi-domainchecker",
            "name" => "Domain Checker",
            "status" => true,
            "prio" => 9
        ],
        "ispapi" => [
            "id" => "whmcs-ispapi-registrar",
            "name" => "Registrar",
            "status" => true,
            "prio" => 10
        ],
        "ispapidpi" => [
            "id" => "whmcs-ispapi-pricingimporter",
            "name" => "Price Importer",
            "status" => true,
            "replacedby" => "ispapiimporter",
            "prio" => 5
        ],
        "ispapidomainimport" => [
            "id" => "whmcs-ispapi-domainimport",
            "name" => "Domain Importer",
            "status" => true,
            "replacedby" => "ispapiimporter",
            "prio" => 4
        ],
        "ispapiimporter" => [
            "id" => "whmcs-ispapi-importer",
            "name" => "ISPAPI Importer",
            "status" => true,
            "prio" => 3
        ],
        "ispapiwidgetaccount" => [
            "id" => "whmcs-ispapi-widget-account",
            "name" => "Account Widget",
            "status" => false,
            "prio" => 2
        ],
        "ispapiwidgetmodules" => [
            "id" => "whmcs-ispapi-widget-modules",
            "name" => "Modules Widget",
            "status" => true,
            "prio" => 0
        ],
        "ispapiwidgetmonitoring" => [
            "id" => "whmcs-ispapi-widget-monitoring",
            "name" => "Monitoring Widget",
            "status" => true,
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
        $deprecated = !$ghdata["status"];
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
        $installed_buttons = '
            <div class="" style="margin-top: 8px">
                <a href="' . $module["urls"]["documentation"] . '" target="_blank" class="label label-default" data-toggle="tooltip" data-placement="top" title="See documentation"><i class="fas fa-book"></i></a>
                <a href="/admin/configaddonmods.php" target="_blank" class="label label-danger" data-toggle="tooltip" data-placement="top" title="Deactivate"><i class="fas fa-minus-square"></i></a>
                ';
        $deprecated_buttons = ' 
            <div class="" style="margin-top: 8px">
                <a href="/admin/configaddonmods.php" target="_blank" class="label label-danger" data-toggle="tooltip" data-placement="top" title="Uninstall"><i class="fas fa-trash"></i></a>
            </div>';
        $not_installed_buttons = '
             <div class="" style="margin-top: 8px">
                <a href="' . $module["urls"]["documentation"] . '" target="_blank" class="label label-default" data-toggle="tooltip" data-placement="top" title="See documentation"><i class="fas fa-book"></i></a>
                <a href="' . $module["urls"]["download"] . '" target="_blank" class="label label-success" data-toggle="tooltip" data-placement="top" title="Install"><i class="fas fa-arrow-down"></i></a>
            </div>';
        if ($module) {
            // style="overflow: auto; white-space: nowrap;"
            $html = '<div class="col-sm-4 text-center small" style="overflow: auto; white-space: nowrap;">' .
                        '<div class="" style="min-height: 80px;border-radius: 4px; margin-top:10px; border: solid;border-width: thin;border-color: #e6e6e6;padding: 3px;">' .
                            '<span class="small">' .  $module["name"] . '</span> <hr style="margin-top:0px; margin-bottom:0px;">';
            if ($module["deprecated"]) {
                $html .= '<div class="textred small">Deprecated</div>';
                $html .= $deprecated_buttons;
            } elseif ($module["version_used"] === "0.0.0") {
                $html .= '<div class="textred small">Not Installed</div>';
                $html .= $not_installed_buttons;
            } else {
                if (version_compare($module["version_used"], $module["version_latest"]) < 0) {
                    $html .= '<div><a class="textred small" href="' . $module["urls"]["download"] . '">v' . $module["version_used"] . '</a></div>';
                    $installed_buttons .= '<a href="' . $module["urls"]["download"] . '" target="_blank" class="label label-success" data-toggle="tooltip" data-placement="top" title="Download update"><i class="fas fa-sync"></i></a>';
                } else {
                    $html .= '<div class="textgreen small">v' . $module["version_used"] . '</div>';
                }
                // $installed_buttons .= '</div>' // close the buttons tag
                $html .= $installed_buttons . '</div>';
            }
            return $html . '</div></div>';
        }
        return '<div class="col-sm-4"></div>';
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

        $content = '<div class="widget-content-padded" style="max-height: 450px">';
        $installed = '<div class="row">';
        $deprecated = '<div class="row">';
        $not_installed = '<div class="row">';
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
        $installed .= '</div>';
        $deprecated .= '</div>';
        $not_installed .= '</div>';
        $content .= '<ul class="nav nav-tabs">
                    <li class="active small"><a data-toggle="tab" href="#tab1">Installed</a></li>
                    <li class="small"><a data-toggle="tab" href="#tab2">Not Installed</a></li>
                    <li class="small"><a data-toggle="tab" href="#tab3">Deprecated</a></li>
                </ul>
                <div class="tab-content">
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
        $content .= '</div>';

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
        $not_installed_modules = [];
        foreach (array_keys($this->map) as $key) {
            if (!in_array($key, $installed_modules_ids)) {
                $not_installed_modules[] = $key;
                $md = $this->getModuleData($key, "NA");
                if ($md !== false) {
                    $modules[] = $md;
                }
            }
        }
        // var_dump($installed_modules_ids, $not_installed_modules, $modules);
        return $modules;
    }
}
