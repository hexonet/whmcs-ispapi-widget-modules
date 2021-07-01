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
            "status" => true,
            "prio" => 8
        ],
        "ispapipremiumdns" => [
            "id" => "whmcs-ispapi-premiumdns",
            "status" => true,
            "prio" => 6
        ],
        "ispapissl" => [
            "id" => "whmcs-ispapi-ssl",
            "status" => true,
            "prio" => 7
        ],
        "ispapidomaincheck" => [
            "id" => "whmcs-ispapi-domainchecker",
            "status" => true,
            "prio" => 9
        ],
        "ispapi" => [
            "id" => "whmcs-ispapi-registrar",
            "status" => true,
            "prio" => 10
        ],
        "ispapidpi" => [
            "id" => "whmcs-ispapi-pricingimporter",
            "status" => true,
            "replacedby" => "ispapiimporter",
            "prio" => 5
        ],
        "ispapidomainimport" => [
            "id" => "whmcs-ispapi-domainimport",
            "status" => true,
            "replacedby" => "ispapiimporter",
            "prio" => 4
        ],
        "ispapiimporter" => [
            "id" => "whmcs-ispapi-importer",
            "status" => true,
            "prio" => 3
        ],
        "ispapiwidgetaccount" => [
            "id" => "whmcs-ispapi-widget-account",
            "status" => true,
            "prio" => 2
        ],
        "ispapiwidgetmodules" => [
            "id" => "whmcs-ispapi-widget-modules",
            "status" => true,
            "prio" => 0
        ],
        "ispapiwidgetmonitoring" => [
            "id" => "whmcs-ispapi-widget-monitoring",
            "status" => true,
            "prio" => 1
        ]
    ];
    const VERSION = "2.2.0";

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
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_TIMEOUT => 3,
            CURLOPT_HEADER => 0,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_USERAGENT => 'ISPAPI MODULES WIDGET',
            CURLOPT_URL => "https://raw.githubusercontent.com/hexonet/$moduleid/master/release.json"
        ]);
        $d = curl_exec($ch);
        curl_close($ch);
        if ($d !== false) {
            $logopath = implode(DIRECTORY_SEPARATOR, [ ROOTDIR, "modules", $moduletype, $whmcsmoduleid, "module.png" ]);
            if (!file_exists($logopath)) {
                $logopath = "https://raw.githubusercontent.com/hexonet/" . $moduleid . "/master/module.png";
            } else {
                $logopath = \DI::make("asset")->getWebRoot() . "/modules/" . $moduletype . "/" . $whmcsmoduleid . "/module.png";
            }
            $d = json_decode($d, true);//404 could happen and will be returned as string
            if ($d !== null) {
                return [
                    "id" => $moduleid,
                    "prio" => $ghdata["prio"],
                    "version_latest" => $d["version"],
                    "version_used" => $this->getWHMCSModuleVersion($whmcsmoduleid),
                    "deprecated" => !$ghdata["status"],
                    "urls" => [
                        "logo" => $logopath,
                        "github" =>  "https://github.com/hexonet/" . $moduleid,
                        "download" => "https://github.com/hexonet/" . $moduleid . "/raw/master/" . $moduleid . "-latest.zip"
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
        if ($module) {
            $html = '<div class="col-sm-4 text-center">' .
                        '<div class="thumbnail">' .
                            '<img style="width:120px; height: 120px" src="' . $module["urls"]["logo"] . '" alt="' .  $module["id"] . '"/>';
            if ($module["deprecated"]) {
                $html .= '<div class="textred">DEPRECATED</div>';
            } elseif ($module["version_used"] === "n/a") {
                $html .= '<div class="textred">NOT INSTALLED</div>';
            } else {
                $html .= (
                    (version_compare($module["version_used"], $module["version_latest"]) < 0) ?
                    '<div><a class="textred" href="' . $module["urls"]["download"] . '">v' . $module["version_used"] . '</a></div>' :
                    '<div class="textgreen">v' . $module["version_used"] . '</div>'
                );
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
     * Fetch data that will be provided to generateOutput method
     * @return array|null data array or null in case of an error
     */
    public function getData()
    {
        global $CONFIG;
        $modules = [];

        // get registrar module versions
        $registrar = new \WHMCS\Module\Registrar();
        foreach ($registrar->getList() as $module) {
            if (preg_match("/^ispapi/i", $module)) {
                $registrar->load($module);
                if ($registrar->isActivated()) {
                    $md = $this->getModuleData($module, "registrars");
                    if ($md !== false) {
                        $modules[] = $md;
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
                }
            }
        }
        return $modules;
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
        while (!empty($modules)) {
            $content .= '<div class="row">';
            $content .= $this->getModuleHTML(array_shift($modules));
            $content .= $this->getModuleHTML(array_shift($modules));
            $content .= $this->getModuleHTML(array_shift($modules));
            $content .= '</div>';
        }
        $content .= '</div>';
        return $content;
    }
}
