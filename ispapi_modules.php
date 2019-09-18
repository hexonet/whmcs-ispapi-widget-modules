<?php
namespace ISPAPI;

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
    const VERSION = "1.2.1";

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

    private function getGHModuleData($moduleid)
    {
        $map = array(
            "ispapibackorder" => array(
                "id" => "whmcs-ispapi-backorder",
                "status" => true
            ),
            "ispapipremiumdns" => array(
                "id" => "whmcs-ispapi-premiumdns",
                "status" => true
            ),
            "ispapissl" => array(
                "id" => "whmcs-ispapi-ssl",
                "status" => true
            ),
            "ispapidomaincheck" => array(
                "id" => "whmcs-ispapi-domainchecker",
                "status" => true
            ),
            "ispapi" => array(
                "id" => "whmcs-ispapi-registrar",
                "status" => true
            ),
            "ispapidpi" => array(
                "id" => "whmcs-ispapi-pricingimporter",
                "status" => true,
                "replacedby" => "ispapiimporter"
            ),
            "ispapidomainimport" => array(
                "id" => "whmcs-ispapi-domainimport",
                "status" => true,
                "replacedby" => "ispapiimporter"
            ),
            "ispapiimporter" => array(
                "id" => "whmcs-ispapi-importer",
                "status" => true
            ),
            "ispapi_account" => array(
                "id" => "whmcs-ispapi-widget-account",
                "status" => true
            ),
            "ispapi_modules" => array(
                "id" => "whmcs-ispapi-widget-modules",
                "status" => true
            )
        );
        if (!array_key_exists($moduleid, $map)) {
            return $moduleid;
        }
        return $map[$moduleid];
    }

    private function getWHMCSModuleVersion($whmcsmoduleid, $moduletype, $whmcslist)
    {
        switch ($moduletype) {
            case "registrars":
                $whmcslist->load($whmcsmoduleid);
                $v = call_user_func($whmcsmoduleid . '_Get' . strtoupper($whmcsmoduleid) . 'ModuleVersion');
                if (empty($v)) {
                    $v = "0.0.0";
                }
                break;
            case "addons":
                $v = (\WHMCS\Module\Addon\Setting::module($whmcsmoduleid)->pluck("value", "setting"))["version"];
                break;
            case "servers":
                $whmcslist->load($whmcsmoduleid);
                $v = $whmcslist->getMetaDataValue("MODULEVersion");
                if (empty($v)) {//old module
                    $v = "0.0.0";
                }
                break;
            case "widgets":
                $whmcslist->load($whmcsmoduleid);
                $tmp = explode("_", $whmcsmoduleid);
                $widgetClass = "\\ISPAPI\\" . ucfirst($tmp[0]) . ucfirst($tmp[1]) . "Widget";
                $mname=$tmp[0]."widget".$tmp[1];
                if (class_exists($widgetClass) && defined("$widgetClass::VERSION")) {
                    $v = $widgetClass::VERSION;
                } else {
                    $v = "0.0.0";
                }
                break;
            default:
                $v = "n/a";
                break;
        }
        return $v;
    }

    private function getModuleData($whmcsmoduleid, $moduletype, $whmcslist)
    {
        $ghdata = $this->getGHModuleData($whmcsmoduleid);
        $moduleid = $ghdata["id"];
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_TIMEOUT => 3,
            CURLOPT_HEADER => 0,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_USERAGENT => 'ISPAPI MODULES WIDGET',
            CURLOPT_URL => "https://raw.githubusercontent.com/hexonet/" . $moduleid . "/master/release.json"
        ));
        $d = curl_exec($ch);
        curl_close($ch);
        if ($d !== false) {
            $logopath = ROOTDIR. "/modules/" . $moduletype . "/" . $whmcsmoduleid ."/module.png";
            if (!file_exists($logopath)) {
                $logopath = "https://raw.githubusercontent.com/hexonet/" . $moduleid . "/master/module.png";
            } else {
                $logopath = \DI::make("asset")->getWebRoot() . "/modules/" . $moduletype . "/" . $whmcsmoduleid ."/module.png";
            }
            $d = json_decode($d, true);//404 could happen and will be returned as string
            if ($d !== null) {
                return array(
                    "id" => $moduleid,
                    "version_latest" => $d["version"],
                    "version_used" => $this->getWHMCSModuleVersion($whmcsmoduleid, $moduletype, $whmcslist),
                    "deprecated" => !$ghdata["status"],
                    "urls" => array(
                        "logo" => $logopath,
                        "github" =>  "https://github.com/hexonet/" . $moduleid,
                        "download" => "https://github.com/hexonet/" . $moduleid . "/raw/master/" . $moduleid . "-latest.zip"
                    )
                );
            }
        }
        return false;
    }

    private function getModuleHTML($module)
    {
        if ($module) {
            $html = '<div class="col-sm-4 text-center">' .
                        '<div class="thumbnail">' .
                            '<img style="width:120px; height: 120px" src="' . $module["urls"]["logo"] . '" alt="' .  $module["id"] . '"/>';
            if ($module["deprecated"]) {
                $html .= '<div class="textred">DEPRECATED</div>';
            } elseif ($module["version_used"]==="n/a") {
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

    /**
     * Fetch data that will be provided to generateOutput method
     * @return array|null data array or null in case of an error
     */
    public function getData()
    {
        global $CONFIG;
        $modules = array();

        // get registrar module versions
        $registrar = new \WHMCS\Module\Registrar();
        foreach ($registrar->getList() as $module) {
            if (preg_match("/^ispapi/i", $module)) {
                $registrar->load($module);
                if ($registrar->isActivated()) {
                    $md = $this->getModuleData($module, "registrars", $registrar);
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
                $md = $this->getModuleData($module, "addons", $addon);
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
                $md = $this->getModuleData($module, "widgets", $widget);
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
