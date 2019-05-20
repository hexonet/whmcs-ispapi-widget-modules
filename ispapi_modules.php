<?php
namespace ISPAPIWIDGET;

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

$module_version = "1.1.0";

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

    private function getModuleData($moduleid)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'ISPAPI MODULES WIDGET');
        curl_setopt($ch, CURLOPT_URL, "https://raw.githubusercontent.com/hexonet/whmcs-ispapi-" . $moduleid . "/master/release.json");
        $d = curl_exec($ch);
        curl_close($ch);
        if ($d !== false) {
            $d = json_decode($d, true);
            $d["url"] = "https://github.com/hexonet/whmcs-ispapi-" . $moduleid . "/raw/master/whmcs-ispapi-" . $moduleid . "-latest.zip";
            $d["imgurl"] = "https://raw.githubusercontent.com/hexonet/whmcs-ispapi-" . $moduleid . "/master/module.png";
            return $d;
        }
        return false;
    }

    private function getModuleHTML($module)
    {
        if ($module) {
            $diff = version_compare($module["version_used"], $module["version_latest"]);
            return (
                '<div class="col-sm-4 text-center">' .
                    '<div class="thumbnail">' .
                        '<img style="width:120px; height: 120px" src="' . $module["imgurl"] . '" alt="' .  $module["title"] . '"/>' .
                        (($diff < 0) ?
                            '<div class="caption"><a class="textred" href="' . $module["url"] . '">v' . $module["version_used"] . '</a></div>' :
                            '<div class="caption"><p class="textgreen">v' . $module["version_used"] . '</p></div>'
                        ) .
                    '</div>' .
                '</div>'
            );
        }
        return '<div class="col-sm-4"></div>';
    }

    /**
     * Fetch data that will be provided to generateOutput method
     * @return array|null data array or null in case of an error
     */
    public function getData()
    {
        $modules = array();

        // ####################################
        // Registrar Version Check
        // ####################################
        $d = $this->getModuleData("registrar");
        if ($d !== false) {
            $path = ROOTDIR."/modules/registrars/ispapi/ispapi.php";
            if (file_exists($path)) {
                require_once($path);
                if (function_exists('ispapi_GetISPAPIModuleVersion')) {
                    $modules[] = array(
                        "title" => "ISPAPI Registrar Module",
                        "version_used" => call_user_func('ispapi_GetISPAPIModuleVersion'),
                        "version_latest" => $d["version"],
                        "url" => $d["url"],
                        "imgurl" => $d["imgurl"]
                    );
                }
            }
        }

        // ####################################
        // Domainchecker Version Check
        // ####################################
        $d = $this->getModuleData("domainchecker");
        if ($d !== false) {
            $path = ROOTDIR."/modules/addons/ispapidomaincheck/ispapidomaincheck.php";
            if (file_exists($path)) {
                require_once($path);
                $modules[] = array(
                    "title" => "ISPAPI High Performance DomainChecker Module",
                    "version_used" => $module_version,
                    "version_latest" => $d["version"],
                    "url" => $d["url"],
                    "imgurl" => $d["imgurl"]
                );
            }
        }

        // ####################################
        // Backorder Version Check
        // ####################################
        $d = $this->getModuleData("backorder");
        if ($d !== false) {
            $path = ROOTDIR."/modules/addons/ispapibackorder/ispapibackorder.php";
            if (file_exists($path)) {
                require_once($path);
                $modules[] = array(
                    "title" => "ISPAPI Backorder Module",
                    "version_used" => $module_version,
                    "version_latest" => $d["version"],
                    "url" => $d["url"],
                    "imgurl" => $d["imgurl"]
                );
            }
        }

        // ####################################
        // PricingImporter Version Check
        // ####################################
        $d = $this->getModuleData("pricingimporter");
        if ($d !== false) {
            $path = ROOTDIR."/modules/addons/ispapidpi/ispapidpi.php";
            if (file_exists($path)) {
                require_once($path);
                $modules[] = array(
                    "title" => "ISPAPI Pricing Importer Module",
                    "version_used" => $module_version,
                    "version_latest" => $d["version"],
                    "url" => $d["url"],
                    "imgurl" => $d["imgurl"]
                );
            }
        }

        // ####################################
        // SSL Version Check
        // ####################################
        $d = $this->getModuleData("ssl");
        if ($d !== false) {
            $path = ROOTDIR."/modules/servers/ispapissl/ispapissl.php";
            if (file_exists($path)) {
                require_once($path);
                $modules[] = array(
                    "title" => "ISPAPI SSL Module",
                    "version_used" => $module_version,
                    "version_latest" => $d["version"],
                    "url" => $d["url"],
                    "imgurl" => $d["imgurl"]
                );
            }
        }

        // ####################################
        // Premium DNS Version Check
        // ####################################
        $d = $this->getModuleData("premiumdns");
        if ($d !== false) {
            $path = ROOTDIR."/modules/servers/ispapipremiumdns/ispapipremiumdns.php";
            if (file_exists($path)) {
                require_once($path);
                $modules[] = array(
                    "title" => "ISPAPI Premium DNS Module",
                    "version_used" => $module_version,
                    "version_latest" => $d["version"],
                    "url" => $d["url"],
                    "imgurl" => $d["imgurl"]
                );
            }
        }

        // ####################################
        // Domain Import Module Version Check
        // ####################################
        $d = $this->getModuleData("domainimport");
        if ($d !== false) {
            $path = ROOTDIR."/modules/addons/ispapidomainimport/ispapidomainimport.php";
            if (file_exists($path)) {
                require_once($path);
                $modules[] = array(
                    "title" => "ISPAPI Domain Import Module",
                    "version_used" => $module_version,
                    "version_latest" => $d["version"],
                    "url" => $d["url"],
                    "imgurl" => $d["imgurl"]
                );
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
