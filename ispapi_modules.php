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
use ZipArchive;
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
            "name" => "Backorder Add-on",
            "type" => "addon", // type (registrar, addon)
            "deprecated" => false,
            "cleanup_files" => ['/modules/addons/ispapibackorder'],
            "install_files" => ['/modules/addons/ispapibackorder'],
            "dependencies" => [
                "required" => [
                    "ispapi"
                ]
            ],
            "prio" => 8
        ],
        "ispapipremiumdns" => [
            "id" => "whmcs-ispapi-premiumdns",
            "name" => "Premium DNS Server",
            "type" => "server",
            "deprecated" => [
                "case" => "product", # case of product deprecation
                "notice" => "Product stopped on 1st of April 2021. You can still manage your existing Premium DNS Zones and their Resource Records. Ordering new ones will fail.",
                "url" => "https://www.hexonet.net/blog/dns-to-serve-you-better",
                "replacement" => "whmcs-dns"
            ],
            "dependencies" => [
                "required" => [
                    "ispapi"
                ]
            ],
            "cleanup_files" => ['/modules/addons/ispapipremiumdns'],
            "install_files" => ['/modules/addons/ispapipremiumdns'],
            "prio" => 6
        ],
        "ispapissl" => [
            "id" => "whmcs-ispapi-ssl",
            "name" => "SSL Add-on",
            "type" => "addon",
            "deprecated" => true,
            "cleanup_files" => ['/modules/addons/ispapissl_addon', '/modules/servers/ispapissl'],
            "install_files" => ['/modules/addons/ispapissl_addon', '/modules/servers/ispapissl'],
            "dependencies" => [
                "required" => [
                    "ispapi"
                ]
            ],
            "prio" => 7
        ],
        "ispapidomaincheck" => [
            "id" => "whmcs-ispapi-domainchecker",
            "name" => "Domain Checker Add-on",
            "type" => "addon",
            "deprecated" => false,
            "cleanup_files" => ['/modules/addons/ispapidomaincheck'],
            "install_files" => ['/modules/addons/ispapidomaincheck'],
            "dependencies" => [
                "required" => [
                    "ispapi",
                    "ispapibackorder" // for testing only. TODO: remove this line
                ]
            ],
            "prio" => 9
        ],
        "ispapidpi" => [
            "id" => "whmcs-ispapi-pricingimporter",
            "name" => "Price Importer Add-on",
            "type" => "addon",
            "deprecated" => [
                "notice" => "Module is no longer maintained as of the new \"Registrar TLD Sync Feature\" Feature of WHMCS. ",
                "url" => "https://docs.whmcs.com/Registrar_TLD_Sync",
                "case" => "whmcs",
                "whmcs" => "7.10",
                "replacement" => "ispapi"
                ],
            "cleanup_files" => ["/modules/addons/ispapidpi"],
            "install_files" => ["/modules/addons/ispapidpi"],
            "dependencies" => [
                "required" => [
                    "ispapi"
                ]
            ],
            "replacedby" => "ispapiimporter",
            "prio" => 5
        ],
        "ispapi" => [
            "id" => "whmcs-ispapi-registrar",
            "name" => "Registrar Module",
            "type" => "registrar",
            "deprecated" => false,
            "cleanup_files" => ['/modules/registrar/ispapi'],
            "install_files" => ['/modules/registrar/ispapi'],
            "dependencies" => [
                "required" => []
            ],
            "prio" => 10
        ],
        "ispapidomainimport" => [
            "id" => "whmcs-ispapi-domainimport",
            "name" => "Domain Importer Add-on",
            "type" => "addon",
            "deprecated" => true,
            "cleanup_files" => ['/modules/addons/ispapidomainimport'],
            "install_files" => ['/modules/addons/ispapidomainimport'],
            "dependencies" => [
                "required" => [
                    "ispapi"
                ]
            ],
            "prio" => 4
        ],
        "ispapiimporter" => [
            "id" => "whmcs-ispapi-importer",
            "name" => "ISPAPI Importer Add-on",
            "type" => "addon",
            "deprecated" => false,
            "cleanup_files" => ['/modules/addons/ispapiimporter'],
            "install_files" => ['/modules/addons/ispapiimporter'],
            "dependencies" => [
                "required" => [
                    "ispapi"
                ]
            ],
            "prio" => 3
        ],
        "ispapiwidgetaccount" => [
            "id" => "whmcs-ispapi-widget-account",
            "name" => "Account Widget",
            "type" => "widget",
            "deprecated" => false,
            "cleanup_files" => ['/modules/widgets/ispapi_account.php'],
            "install_files" => ['/modules/widgets/ispapi_account.php'],
            "dependencies" => [
                "required" => [
                    "ispapi"
                ]
            ],
            "prio" => 2
        ],
        "ispapiwidgetmodules" => [
            "id" => "whmcs-ispapi-widget-modules",
            "name" => "Modules Widget",
            "type" => "widget",
            "deprecated" => false,
            "cleanup_files" => ['/modules/widgets/ispapi_modules.php'],
            "install_files" => ['/modules/widgets/ispapi_modules.php'],
            "dependencies" => [
                "required" => ["ispapi"]
            ],
            "prio" => 0
        ],
        "ispapiwidgetmonitoring" => [
            "id" => "whmcs-ispapi-widget-monitoring",
            "name" => "Monitoring Widget",
            "type" => "widget",
            "deprecated" => false,
            "cleanup_files" => ['/modules/widgets/ispapi_monitoring.php'],
            "install_files" => ['/modules/widgets/ispapi_monitoring.php'],
            "dependencies" => [
                "required" => [
                    "ispapi"
                ]
            ],
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
    private function getModuleData($whmcsmoduleid, $status)
    {
        $ghdata = $this->getGHModuleData($whmcsmoduleid);
        $moduleid = $ghdata["id"];
        $priority = $ghdata["prio"];
        $name = $ghdata["name"];
        $type = $ghdata["type"];
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
                    "status" => $status,
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
        // var_dump($this->downloadUnzipGetContents('ispapiwidgetaccount'));
        // $this->downloadUnzipGetContents('ispapidomaincheck');
        // die();
        // var_dump($this->getDependenciesMap());

        $action = App::getFromRequest('action');
        if ($action !== "") {
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
            } elseif ($action == "installModule") {
                $results = $this->downloadUnzipGetContents($module);
                if ($results['msg'] === 'success') {
                    return [
                        "success" => true,
                        "module" => $module,
                        "result" => 'success'
                    ];
                } else {
                    return [
                        "success" => false,
                        "module" => $module,
                        "result" => 'Error in ' . $module . ': ' . $results['msg']
                    ];
                }
            } elseif ($action == "removeModule") {
                $result = [];
                try {
                    $dirs = $this->map[$module]['install_files'];
                    if (!empty($dirs)) {
                        // check if files in all dirs are removable
                        foreach ($dirs as $dir) {
                            $dir_files = $this->checkDirAndFileRemovable(ROOTDIR . $dir, []);
                            // the check permission
                            $permission_check = $this->checkResults($dir_files);
                            if ($permission_check['result'] == false) {
                                return [
                                    "success" => false,
                                    "data" => $permission_check['msg']
                                ];
                            }
                        }
                        // when files are remoable, then delete them
                        $all_delete_files = [];
                        foreach ($dirs as $dir) {
                            $delete_results = $this->delTree(ROOTDIR . $dir, []);
                            // add deleted files, in case the user want to see them
                            $all_delete_files[$dir] = $delete_results;
                            // check if files were deleted
                            $results_check = $this->checkResults($delete_results);
                            if ($permission_check['result'] == false) {
                                return [
                                    "success" => false,
                                    "data" => $results_check['msg']
                                ];
                            }
                            // return success to the user
                            return [
                                "success" => true,
                                "data" => $all_delete_files
                            ];
                        }
                    } else {
                        return [
                            "success" => false,
                            "data" => "No files were found!"
                        ];
                    }
                } catch (Exception $e) {
                    return [
                        "success" => false,
                        "data" => $e
                    ];
                }
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
            $installed = [];
            $not_active_or_installed = [];
            $deprecated = [];

            while (!empty($modules)) {
                $module = array_shift($modules);
                $data = [];
                $data['name'] = $module["name"];
                $data['type'] = $module["type"];
                $data['token'] = generate_token("link");
                $data['whmcsmoduleid'] = $module["whmcsid"];
                $data['version_used'] = $module["version_used"];
                $data['version_latest'] = $module["version_latest"];
                $data['no_latest_used'] = (version_compare($module["version_used"], $module["version_latest"]) < 0);
                $data['documentation_link'] = $module["urls"]["documentation"];
                $data['download_link'] = $module["urls"]["download"];
                $data['status'] = $module['status'];
                // check module type
                if (gettype($module["deprecated"]) == "boolean" && $module["deprecated"] === true) {
                    $data['case'] = 'default';
                    $deprecated[] = $data;
                } elseif (gettype($module["deprecated"]) == "array") {
                    // prepare data
                    $notice = $module["deprecated"]["notice"];
                    $url = $module["deprecated"]["url"];
                    $replacement = $module["deprecated"]["replacement"];
                    $case = $module["deprecated"]["case"];
                    // case 1: Product Deprecation.
                    if ($case == 'product') {
                        $data['case'] = $case;
                        $data['notice'] = $notice;
                        $data['url'] = $url;
                        $data['replacement'] = $replacement;
                        $deprecated[] = $data;
                    }
                    // case 2: Deprecation since WHMCS vX.Y.Z
                    if ($case == 'whmcs') {
                        $whmcs_version = $module["deprecated"]["whmcs"];
                        $current_whmcs_version = $GLOBALS["CONFIG"]["Version"];
                        $version = implode(".", array_slice(explode(".", $whmcs_version), 0, 2));
                        if (version_compare($whmcs_version, $current_whmcs_version) === -1) {
                            // $current_whmcs_version is lower than whmcs_version
                            // var_dump($whmcs_version, $current_whmcs_version);
                            $data['case'] = $case;
                            $data['whmcs_version'] = $module["deprecated"]["whmcs"];
                            $data['notice'] = $notice;
                            $data['url'] = $url;
                            $data['replacement'] = $replacement;
                            $deprecated[] = $data;
                        }
                    }
                } elseif ($module["status"] == "not-active" || $module["status"] == "not-installed") {
                    // not active
                    // not installed
                    $not_active_or_installed[] = $data;
                } else {
                    // active
                    $installed[] = $data;
                }
            }
            // var_dump($deprecated, $not_active, $not_installed, $active);
            $content = $this->getSmartyHTML($installed, $not_active_or_installed, $deprecated);
            return $content;
        }
    }
    private function downloadUnzipGetContents($mapkey)
    {
        $copied_files = [];
        $msg = '';
        $moduleid = $this->map[$mapkey]['id'];
        $dirs = $this->map[$mapkey]['install_files'];
        $url = "https://github.com/hexonet/" . $moduleid . "/raw/master/" . $moduleid . "-latest.zip";
        $zipfile = ROOTDIR . tempnam(sys_get_temp_dir(), 'zipfile') . $moduleid . "-latest.zip";
        // download data from url
        $download = file_put_contents($zipfile, fopen($url, 'r'));
        if ($download > 0) {
            // extract zip file
            $zip = new ZipArchive();
            $res = $zip->open($zipfile);
            if ($res) {
                $entries = [];
                foreach ($dirs as $dir) {
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $filename = $zip->getNameIndex($i);
                        $fileinfo = pathinfo($filename);
                        if (($fileinfo['extension'] != null) && str_starts_with(DIRECTORY_SEPARATOR . $filename, $dir)) {
                            $entries[] = $filename;
                        }
                    }
                }
                // extract files
                $extract = $zip->extractTo(ROOTDIR . DIRECTORY_SEPARATOR, $entries);
                if ($extract) {
                    $msg = 'success';
                } else {
                    $msg = 'Failed to extract files!';
                }
            }
            $zip->close();
        } else {
            $msg = 'Failed to download zip file.';
        }
        unlink($zipfile);
        return ['msg' => $msg, 'data' => $copied_files];
    }

    private function delTree($dir, $results)
    {
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $fullpath = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($fullpath)) {
                // var_dump($fullpath);
                $results = $this->delTree($fullpath, $results);
            } else {
                $result = unlink($fullpath);
                $results[$fullpath] = $result;
            }
        }
        $dir_delete = rmdir($dir);
        $results[$dir] = $dir_delete;
        return $results;
    }

    private function checkDirAndFileRemovable($dir, $results)
    {
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $fullpath = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($fullpath)) {
                $results = $this->checkDirAndFileRemovable($fullpath, $results);
            } else {
                // check if files are removable
                if (is_writable(dirname($fullpath))) {
                    $results[$fullpath] = true;
                } else {
                    $results[$fullpath] = false;
                }
            }
        }
        // check if the directory is removable
        if (is_writable(dirname($dir))) {
            $results[$dir] = true;
        } else {
            $results[$dir] = false;
        }
        return $results;
    }

    private function checkResults($results)
    {
        foreach ($results as $key => $value) {
            if ($value == false) {
                return ["result" => false, "msg" => $key . " Permission Denied"];
            }
        }
        return ["result" => true,"msg" => "success"];
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
                    $md = $this->getModuleData($module, 'active');
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
                $md = $this->getModuleData($module, 'active');
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
                $md = $this->getModuleData($module, 'active');
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
                $md = $this->getModuleData(str_replace("_", "widget", $module), 'active');
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
                $md = $this->getModuleData($key, 'not-active');
                if ($md !== false) {
                    $modules[] = $md;
                }
            } elseif (!in_array($key, $installed_modules_ids)) {
                // not installed modules
                // checked based on version number
                $not_installed_modules[] = $key;
                $md = $this->getModuleData($key, 'not-installed');
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
        $modules = [];
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
    private function getDependenciesMap($not_installed_modules, $installed_modules)
    {
        // get the module dependencies, and check if they are installed
        $dependencies_arr = [];
        foreach ($not_installed_modules as $not_installed) {
            // get its dependencies
            $id = $not_installed['whmcsmoduleid'];
            $dependencies = $this->map[$id]['dependencies']['required'];
            if (sizeof($dependencies) > 0) {
                foreach ($dependencies as $dependcy) {
                    $dependencies_arr[$id][$dependcy] = false;
                    foreach ($installed_modules as $installed) {
                        if ($installed['whmcsmoduleid'] === $dependcy) {
                            $dependencies_arr[$id][$dependcy] = true;
                            continue; // continue to next dependency
                        }
                    }
                }
            }
        }
        return $dependencies_arr;
        // return $return;
    }
    private function getInstalledModules()
    {
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
        $jscript = self::generateOutputJS($not_active_or_installed, $installed);
        $smarty->assign('jscript', $jscript);
        // get modals
        $modals = self::generateModals();
        $smarty->assign('modals', $modals);
        // numner of not installed/activated
        $installed_count = '<span class="small bg-success" style="border-radius:50%; padding: 0px 5px 0px 5px;">
                        ' . sizeof($installed) . '
                    </span>';
        $not_installed_count = '<span class="small bg-danger" style="border-radius:50%; padding: 0px 5px 0px 5px;">
                        ' . sizeof($not_active_or_installed) . '
                    </span>';
        $deprecated_size = 0;
        foreach ($deprecated as $module) {
            if ($module['status'] != 'not-installed') {
                $deprecated_size++;
            }
        }
        $deprecated_count = '<span class="small bg-warning" style="border-radius:50%; padding: 0px 5px 0px 5px;">
                        ' . $deprecated_size . '
                    </span>';
        $smarty->assign('installed_count', $installed_count);
        $smarty->assign('not_installed_count', $not_installed_count);
        $smarty->assign('deprecated_count', $deprecated_count);
        $smarty->assign('deprecated_size', $deprecated_size);
        // parse content
        $content = '<div class="widget-content-padded" style="/*max-height: 450px*/">
                        <div class="row small">
                            <ul class="nav nav-tabs">
                                <li class="active"><a data-toggle="tab" href="#tab1">Installed {$installed_count}</a></li>
                                <li class=""><a data-toggle="tab" href="#tab2">Not Installed/Activated {$not_installed_count}</a></li>
                                <li class=""><a data-toggle="tab" href="#tab3">Deprecated {$deprecated_count}</a></li>
                            </ul>
                            <div class="tab-content small">
                                <div id="tab1" class="tab-pane fade in active">
                                    {if $installed}
                                        <table class="table table-bordered table-condensed" style="margin-top: 4px; margin-bottom: 10px">
                                            <thead>
                                                <tr>
                                                    <th scope="col" style="width: 5%"><input onChange="selectUnselectCheckboxs(this, \'upgrade\');" type="checkbox" class="form-check-input" id="checkallUpgrade"></th>
                                                    <th scope="col" style="width: 35%">Name</th>
                                                    <th scope="col" style="width: 30%">Version</th>
                                                    <th scope="col" style="width: 30%">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="installationTbody">
                                                {foreach $installed as $module}
                                                    <tr>
                                                        <td>
                                                            {if $module.no_latest_used}
                                                                <input type="checkbox" class="upgrade-checkbox" onChange="checkboxChange(this, \'upgrade\');" id="{$module.whmcsmoduleid}">
                                                            {/if}
                                                        </td>
                                                        <td>{$module.name}</td>
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
                                                                <button class="btn btn-danger btn-xs deactivatebtn" m-action="deactivate" m-type="{$module.type}" module="{$module.whmcsmoduleid}" token="{$module.token}" data-toggle="tooltip" data-placement="top" title="Deactivate">
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
                                                {/foreach}
                                            </tbody>
                                        </table>
                                       <div class="">
                                            <div class="col-sm-12" style="display: inline-flex; padding: 0px;">
                                                <button disabled class="btn btn-success btn-sm" onclick="installUpgradeModules(\'upgrade\');" id="btn-upgrade">Upgrade Selected <i class="fas fa-arrow-right"></i></button>
                                                <div class="text-warning" id="upgrade-div" style="display:none ;padding: 7px 0px 0px 10px;font-size: 10px;">
                                                    <i class="fas fa-spinner fa-spin"></i>
                                                    <span id="upgrade-notice" >Please wait, Upgrading x </span>
                                                </div>
                                            </div>
                                        </div>
                                    {else}
                                        <div class="widget-content-padded">
                                            <div class="text-center">No modules found.</div>
                                        </div>
                                    {/if}
                                </div>
                                <div id="tab2" class="tab-pane fade">
                                    {if $not_active_or_installed}
                                        <table class="table table-bordered table-condensed" style="margin-top: 4px; margin-bottom: 10px">
                                            <thead>
                                                <tr>
                                                    <th scope="col" style="width: 5%"><input onChange="selectUnselectCheckboxs(this, \'install\');" type="checkbox" class="form-check-input" id="checkall"></th>
                                                    <th scope="col" style="width: 35%">Name</th>
                                                    <th scope="col" style="width: 30%">Status</th>
                                                    <th scope="col" style="width: 30%">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="notActiveOrInstalled">
                                                {foreach $not_active_or_installed as $module}
                                                    <tr>
                                                        <td>
                                                            {if $module.status == \'not-installed\'}
                                                                <input type="checkbox" class="install-checkbox" onChange="checkboxChange(this, \'install\');" id="{$module.whmcsmoduleid}">
                                                            {/if}
                                                        </td>
                                                        <td>{$module.name}</td>
                                                        {if $module.status == \'not-active\'}
                                                            <td class="textred small">Not Acitve</td>
                                                            <td>
                                                                <button class="btn btn-default btn-xs" onclick="window.open(\'{$module.documentation_link}\');" data-toggle="tooltip" data-placement="top" title="See documentation" >
                                                                    <i class="fas fa-book"></i>
                                                                </button>
                                                                <button class="btn btn-success btn-xs activatebtn" m-action="activate" m-type="{$module.type}" module="{$module.whmcsmoduleid}" token="{$module.token}" data-toggle="tooltip" data-placement="top" title="Activate">
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
                                        <div class="">
                                            <div class="col-sm-12" style="display: inline-flex; padding: 0px;">
                                                <button disabled class="btn btn-success btn-sm" onclick="installUpgradeModules(\'install\');" id="btn-install">Install Selected <i class="fas fa-arrow-right"></i></button>
                                                <div class="text-warning" id="installation-div" style="display:none ;padding: 7px 0px 0px 10px;font-size: 10px;">
                                                    <i class="fas fa-spinner fa-spin"></i>
                                                    <span id="installation-notice" >Please wait, Installing x </span>
                                                </div>
                                            </div>
                                        </div>
                                    {else}
                                        <div class="widget-content-padded">
                                            <div class="text-center">No modules found.</div>
                                        </div>
                                    {/if}
                                </div>
                                <div id="tab3" class="tab-pane fade">
                                    {if $deprecated_size > 0}
                                        <table class="table table-bordered table-condensed" style="margin-top: 4px;">
                                            <thead>
                                                <tr>
                                                    <th scope="col" style="width: 40%">Name</th>
                                                    <th scope="col" style="width: 30%">Status</th>
                                                    <th scope="col" style="width: 30%">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {foreach $deprecated as $module}
                                                    {if $module.status != \'not-installed\'}
                                                        <tr>
                                                            <td>{$module.name}</td>
                                                            <td>
                                                                {if $module.status == \'active\'}
                                                                    <span class="textorange small">Activated/ Installed/</span>
                                                                {/if}
                                                                {if $module.status == \'not-active\'}
                                                                    <span class="textorange small">Not Activated/</span>
                                                                {/if}
                                                                {if $module.status == \'not-installed\'}
                                                                    <span class="textorange small">Not Installed/</span>
                                                                {/if}
                                                                <span class="textred small">Deprecated</span>
                                                            </td>
                                                            <td>
                                                                {if $module.status != \'not-installed\'}
                                                                    <button class="btn btn-danger btn-xs removebtn" m-status="{$module.status}" m-action="remove" m-type="{$module.type}" module="{$module.whmcsmoduleid}" token="{$module.token}" data-toggle="tooltip" data-placement="top" title="Remove">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                {/if}
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
                                                    {/if}
                                                {foreachelse}
                                                    <span class="text-center">No modules found.</span>
                                                {/foreach}
                                            </tbody>
                                        </table>
                                    {else}
                                        <div class="widget-content-padded">
                                            <div class="text-center">No modules found.</div>
                                        </div>
                                    {/if}
                                </div>
                            </div>
                        </div>
                    </div>
                    {$jscript}
                    {$modals}
                    ';
        return $smarty->fetch('eval:' . $content);
    }

    private function generateOutputJS($not_installed_modules, $installed_modules_ids)
    {
        $dependencies_arr = $this->getDependenciesMap($not_installed_modules, $installed_modules_ids); // get dependencies for not installed modules
        $dependencies_arr[] = $this->getDependenciesMap($installed_modules_ids, []); // get dependencies for installed modules
        $dependencies_arr = json_encode($dependencies_arr);
        return <<<EOF
        <script type="text/javascript">
            var dependency_map = $dependencies_arr;
            const loadingIcon = '<i class="fas fa-spinner fa-spin"></i>';
            // activate/deactivate logic
            $('.activatebtn, .deactivatebtn').on('click', function (event) {
                // set loading icon
                $(this).html(loadingIcon);
                const defaultIcon = $(this).html();
                // prepare data
                const type = $(this).attr("m-type");
                const module = $(this).attr("module");
                const token = $(this).attr("token");
                const action = $(this).attr("m-action");
                // if registrar: user internal API
                if (type == 'registrar'){
                    activateDeactivate(type, module, action, token).then(function(result){
                        if ( result.success ){
                            refreshWidget('IspapiModulesWidget', 'refresh=1');
                        }
                        else{
                            const msg = 'An error occured, couldn\'t activate module: ' + module;
                            // Add response in Modal body
                            $('.modal-body-alert').html(msg);
                            // Display Modal
                            $('#alertModalOther').modal('show');
                        }
                    });
                }
                else if (type == 'addon'){
                    activateDeactivate(type, module, action, token).then(function(result){
                        if ( result ){
                            refreshWidget('IspapiModulesWidget', 'refresh=1');
                        }
                        else{
                            const msg = 'An error occured, couldn\'t activate module: ' + module;
                            // Add response in Modal body
                            $('.modal-body-alert').html(msg);
                            // Display Modal
                            $('#alertModalOther').modal('show');
                        }
                    });
                }
                else{
                    const msg = type + ' not supported';
                    // Add response in Modal body
                    $('.modal-body-alert').html(msg);
                    // Display Modal
                    $('#alertModalOther').modal('show');
                }
            })
            // toggle the details view
            $('.toggleDetailsView').on('click', function (event) {
                const module = $(this).attr("m-type");
                $("#"+module).fadeToggle();
                $(this).children('.fa-caret-up, .fa-caret-down').toggleClass("fa-caret-up fa-caret-down");
            });
            $('.removebtn').on('click', function (event) {
                const loadingIcon = '<i class="fas fa-spinner fa-spin"></i>';
                const defaultIcon = $(this).html();
                // set loading icon
                $(this).html(loadingIcon);
                // prepare data
                const type = $(this).attr("m-type");
                const module = $(this).attr("module");
                const token = $(this).attr("token");
                const status = $(this).attr("m-status");
                if ( status == 'active'){
                    // deactivate the module
                    activateDeactivate(type, module, 'deactivate', token).then(function(result){
                        if ( result ){
                            // remove from the system
                            removeModule(module).then(function(result){
                                if (result.success){
                                    refreshWidget('IspapiModulesWidget', 'refresh=1');
                                    return true;
                                }
                                else {
                                    const msg = "could not remove module: " + module;
                                    // Add response in Modal body
                                    $('.modal-body').html(msg);
                                    // Display Modal
                                    $('#alertModalOther').modal('show');
                                }
                            })
                        }
                        else{
                            const msg = 'An error occured, couldn\'t activate module: ' + module;
                            // Add response in Modal body
                            $('.modal-body').html(msg);
                            // Display Modal
                            $('#alertModalOther').modal('show');
                        }
                    });
                }
                else {
                    // remove from the system
                    removeModule(module).then(function(result){
                        if (result.success){
                            const data = JSON.parse(result.widgetOutput);
                            if(data.success){
                                var flag_failed = false;
                                var deleted_files= "";
                                var failed_files= "";
                                // console.log(data.data);
                                for (const [key, value] of Object.entries(data.data)) {
                                    for (const [subkey, subvalue] of Object.entries(value)) {
                                        console.log(key, subvalue);
                                        if (subvalue == true){
                                            deleted_files += subkey + "\\n";
                                        } else {
                                            flag_failed = true;
                                            failed_files += subkey + "\\n";
                                        }
                                    }
                                }
                                if(flag_failed){
                                    const msg = "Operations failed with error: \\n files failed to delete: \\n " + failed_files;
                                    // Add response in Modal body
                                    $('.modal-body').html(msg);
                                    // Display Modal
                                    $('#alertModal').modal('show'); 
                                }
                                else{
                                    const msg = "Operation completed with Success!";
                                    // Add response in Modal body
                                    $('.modal-body').html(msg);
                                    // Display Modal
                                    $('#alertModal').modal('show');
                                }
                            }
                            else {
                                const msg = "An error occured on server side: \\n\\n" + data.data;
                                // Add response in Modal body
                                $('.modal-body').html(msg);
                                // Display Modal
                                $('#alertModal').modal('show');
                            }
                        }
                        else{
                            const msg = "Server error, check your internet connection.";
                            // Add response in Modal body
                            $('.modal-body').html(msg);
                            // Display Modal
                            $('#alertModal').modal('show');
                        }
                        //refreshWidget('IspapiModulesWidget', 'refresh=1');
                    })
                }
            });
            $(document).on('hidden.bs.modal','#alertModal', function () {
                refreshWidget('IspapiModulesWidget', 'refresh=1');
            })
            async function removeModule(module){
                const url = WHMCS.adminUtils.getAdminRouteUrl('/widget/refresh&widget=IspapiModulesWidget&module='+ module + '&action=removeModule');
                    const result = await $.ajax({
                        url: url,
                        type: 'GET',
                        success: function (data) { return true;},
                        error: function (jqXHR, textStatus, errorThrown) { return false; }
                    });
                    return result;
            }
            async function activateDeactivate(type, module, action, token = 0){
                if (type == 'registrar'){
                    const url = WHMCS.adminUtils.getAdminRouteUrl('/widget/refresh&widget=IspapiModulesWidget&module='+ module + '&type=' + type + '&action=' + action);
                    const result = await $.ajax({
                        url: url,
                        type: 'GET',
                        data: {},
                        datatype: 'json'
                    });
                    return result;
                }
                else if( type == 'addon'){
                    const url= '/admin/configaddonmods.php?action='+ action +'&module=' + module + token;
                    const result = await $.ajax({
                        url: url,
                        type: 'GET',
                        data: {},
                        datatype: 'json'
                    })
                    return result;
                }
                else{
                    return false;
                }
            }
            async function selectUnselectCheckboxs(selector, operation_type){
                var checkboxes = operation_type == 'install'? $('tbody#notActiveOrInstalled input:checkbox') : $('tbody#installationTbody input:checkbox');
                if($(selector).is(':checked')) {
                    for(const checkbox of checkboxes) {
                        $(checkbox).prop('checked', true);
                        module_id = $(checkbox).attr('id');
                        let result = await checkDependency(module_id, 'select');
                    }
                }
                else{
                    for(const checkbox of checkboxes) {
                        $(checkbox).removeAttr('checked');
                        module_id = $(checkbox).attr('id');
                        let result = await checkDependency(module_id, 'unselect');
                    }
                }
                enableDisableBtn(operation_type);
            }
            async function checkboxChange(reference, operation_type){
                if (operation_type == 'install'){

                }
                else{
                }

                let module_id = $(reference).attr('id');
                if($(reference).is(':checked')) {
                    checkDependency(module_id, 'select');
                }
                else{
                    checkDependency(module_id, 'unselect');
                }
                // operation button check
                enableDisableBtn(operation_type);
            }
            async function enableDisableBtn(operation_type){
                let checkboxs = [];
                let referenceBtn = undefined;
                if (operation_type == 'install'){
                    checkboxs = $('.install-checkbox:checkbox:checked');
                    referenceBtn = $('#btn-install');
                }
                else{
                    checkboxs = $('.upgrade-checkbox:checkbox:checked');
                    referenceBtn = $('#btn-upgrade');
                }
                if(checkboxs.length == 0){
                    referenceBtn.prop('disabled', true);
                }
                else{
                    referenceBtn.prop('disabled', false);
                }
            }
            async function installUpgradeModules(operation){
                let modules = [];
                let success = true;
                let checkboxs =  operation == 'install'? $('.install-checkbox:checkbox:checked') : $('.upgrade-checkbox:checkbox:checked');
                for (const checkbox of checkboxs){
                    // get module id from the checkbox
                    let module = $(checkbox).attr('id');
                    // install the module
                    let result = await installSingleModule(module, operation);
                    if (typeof result != "boolean"){
                        success = false;
                        $('.modal-body-alert').html(result);
                        $('#alertModalOther').modal('show');
                        operation == 'install'? $('#installation-div').slideUp(100) : $('#upgrade-div').slideUp(100);
                    }
                }
                if (success){
                    const msg = operation == 'install'? "Installation finished successfully!" : "Upgrade finished successfully!";
                    $('.modal-body').html(msg);
                    $('#alertModal').modal('show');
                }
            }
            async function installSingleModule(module_id, operation){
                // show & update notification message
                operation == 'install'? $('#installation-div').slideDown(500) : $('#upgrade-div').slideDown(500);
                operation == 'install'? $('#installation-notice').html('Please wait, installing: ' + module_id) : $('#upgrade-notice').html('Please wait, upgrading: ' + module_id);
                // send xhr request
                const url = WHMCS.adminUtils.getAdminRouteUrl('/widget/refresh&widget=IspapiModulesWidget&module='+ module_id + '&action=installModule');
                const result = await $.ajax({
                    url: url,
                    type: 'GET',
                    success: function (data) { return true;},
                    error: function (jqXHR, textStatus, errorThrown) { return false; }
                });
                // hide notification message
                operation == 'install'? $('#installation-div').slideUp(100) : $('#upgrade-div').slideUp(100);
                // check results
                const data = JSON.parse(result.widgetOutput);
                if (data.success){
                    return true;
                }
                else{
                    const msg = data.result;
                    return msg;
                }
            }
            async function checkDependency(module_id, mode){
                const dependency_list = dependency_map[module_id];
                // check if the module have at least one dependecy
                if (dependency_list != undefined){
                    for (var key in dependency_list) {
                        if(dependency_list[key] == false){
                            if(mode == 'select'){
                                $('#'+key).prop({'checked':true, 'disabled': true});
                            }
                            else{
                                $('#'+key).prop({'checked':false, 'disabled': false});
                            }
                        }
                    }
                }
                return true;
            }
        </script>
        EOF;
    }
    private static function generateModals()
    {
        return <<<EOF
            <!-- Modal for Deprecation alerts-->
            <div class="modal fade" id="alertModal" tabindex="-1" role="dialog" aria-labelledby="alertModalTitle" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        ...
                    </div>
                    <div class="modal-footer">
                        <button type="button" id="alertModalDismiss" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    </div>
                    </div>
                </div>
            </div>
            <!-- Modal for other alerts -->
            <div class="modal fade" id="alertModalOther" tabindex="-1" role="dialog" aria-labelledby="alertModalTitle" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body-alert">
                        ...
                    </div>
                    <div class="modal-footer">
                        <button type="button" id="alertModalDismiss" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    </div>
                    </div>
                </div>
            </div>
        EOF;
    }
}
