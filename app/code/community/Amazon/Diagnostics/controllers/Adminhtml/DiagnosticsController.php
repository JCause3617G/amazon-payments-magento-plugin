<?php

/**
 * Amazon Diagnostics
 *
 * @category    Amazon
 * @package     Amazon_Diagnostics
 * @copyright   Copyright (c) 2015 Amazon.com
 * @license     http://opensource.org/licenses/Apache-2.0  Apache License, Version 2.0
 */
class Amazon_Diagnostics_Adminhtml_DiagnosticsController extends Mage_Adminhtml_Controller_Action {

    private $_basepath = "";
    private $_designpath = "";
    private $_layoutpath = "";
    private $_logpath = "";
    private $_apppath = "";
    private $_modules = array();
    private $_logs = array();
    private $_global_results = array();

    public function checkAction() {

        ini_set("auto_detect_line_endings", true);

        $this->_basepath = Mage::getBaseDir('base');
        $this->_apppath = Mage::getBaseDir('app');
        $this->_designpath = Mage::getBaseDir('design');
        $this->_logpath = Mage::getBaseDir('log');
        $this->_layoutpath = $this->_designpath . "/frontend/base/default/layout";

        /* do some diagnostics */
        $this->getModules();
        $this->getLogs();

        /* set some settings variables */
        $login_client_id = Mage::getStoreConfig('amazon_login/settings/client_id');
        $login_client_secret = Mage::getStoreConfig('amazon_login/settings/client_secret');
        if (strlen($login_client_secret) > 6) {
            $login_client_secret = substr($login_client_secret, 0, 3) . "..." . substr($login_client_secret, strlen($login_client_secret - 3), 3);
        }
        $payments_secret_key = Mage::getStoreConfig('payment/amazon_payments/access_secret');
        if (strlen($payments_secret_key) > 6) {
            $payments_secret_key = substr($payments_secret_key, 0, 3) . "..." . substr($payments_secret_key, strlen($payments_secret_key - 3), 3);
        }
        $payments_seller_id = Mage::getStoreConfig('payment/amazon_payments/seller_id');
        $payments_access_key = Mage::getStoreConfig('payment/amazon_payments/access_key');


        /* set global results */
        $this->_global_results['magento_version'] = Mage::getVersion();
        $this->_global_results['magento_edition'] = Mage::getEdition();
        $this->_global_results['base_path'] = $this->_basepath;

        $this->_global_results['login_client_id'] = "'" . $login_client_id . "'";
        if (preg_match('/\s/', $login_client_id)) {
            $this->_global_results['login_client_id'] = " ** white space detected **";
        }
        $this->_global_results['login_client_secret'] = "'" . $login_client_secret . "'";
        if (preg_match('/\s/', $login_client_secret)) {
            $this->_global_results['login_client_secret'] .= "** white space detected **";
        }
        $this->_global_results['login_button_type'] = Mage::getStoreConfig('amazon_login/settings/button_type');

        $this->_global_results['payments_seller_id'] = "'" . $payments_seller_id . "'";
        if (preg_match('/\s/', $payments_seller_id)) {
            $this->_global_results['payments_seller_id'] .= " ** white space detected **";
        }
        $this->_global_results['payments_access_key'] = "'" . $payments_access_key . "'";
        if (preg_match('/\s/', $payments_access_key)) {
            $this->_global_results['payments_access_key'] .= " ** white space detected **";
        }
        $this->_global_results['payments_secret_key'] = "'" . $payments_secret_key . "'";
        if (preg_match('/\s/', $payments_secret_key)) {
            $this->_global_results['payments_secret_key'] .= "** white space detected **";
        }
        $this->_global_results['payments_sandbox'] = (Mage::getStoreConfig('payment/amazon_payments/sandbox') == 1 ? 'yes' : 'no');

        $this->_global_results['enabled'] = (Mage::getStoreConfig('amazon_login/settings/enabled') == 1 ? 'yes' : 'no');
        $this->_global_results['secure_cart'] = (Mage::getSingleton('amazon_payments/config')->isSecureCart() == 1 ? 'yes' : 'no');
        $this->_global_results['modules'] = $this->_modules;
        $this->_global_results['logs'] = $this->_logs;

        /* send the response */
        Mage::app()->getResponse()->setBody(print_r($this->_global_results, true));
    }

    private function getModules() {

        $modules_folder = $this->_apppath . "/etc/modules";

        try {
            /* get list of modules */
            if ($h = opendir($modules_folder)) {

                /* loop through the modules */
                while (false !== ($entry = readdir($h))) {

                    /* we don't want . and .. */
                    if ($entry !== "." && $entry !== "..") {

                        /* get file extension */
                        $ext = pathinfo($modules_folder . "/" . $entry, PATHINFO_EXTENSION);

                        /* make sure it's xml */
                        if ($ext == "xml") {

                            /* load the module xml */
                            $xml = simplexml_load_file($modules_folder . "/" . $entry);

                            /* convert xml to associative array */
                            $xml = json_encode($xml);
                            $xml = json_decode($xml, true);

                            foreach ($xml['modules'] as $k => $v) {

                                /* filter out core modules and */
                                if ($v['codePool'] !== 'core') {

                                    /* get status */
                                    $this->_modules[$k]['active'] = $v['active'];

                                    /* get codepool */
                                    $this->_modules[$k]['pool'] = $v['codePool'];

                                    /* parse the module config.xml */
                                    $modulepath = implode("/", explode("_", $k));
                                    $mxml = simplexml_load_file($this->_apppath . "/code/" . $v['codePool'] . "/" . $modulepath . "/etc/config.xml");

                                    /* convert to associative array */
                                    $mxml = json_encode($mxml);
                                    $mxml = json_decode($mxml, true);

                                    /* get version */
                                    $this->_modules[$k]['version'] = $mxml['modules'][$k]['version'];

                                    /* get global blocks */
                                    if (isset($mxml['global']['blocks'])) {
                                        foreach ($mxml['global']['blocks'] as $mk => $mv) {
                                            $this->_modules[$k]['blocks'][] = $mk;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            /* log any errors */
            $this->_global_results['errors'][] = $r->getMessage();
        }
        @closedir($h);
    }

    private function getLogs() {

        try {
            /* get list of log files */
            if ($h = opendir($this->_logpath)) {

                /* loop through the files */
                while (false !== ($entry = readdir($h))) {

                    /* we don't want . and .. */
                    if ($entry !== "." && $entry !== "..") {

                        /* not using due to exceeding memory limits on large files */
                        //$filearray = file($this->_logpath . "/" . $entry, FILE_SKIP_EMPTY_LINES);

                        $filearray = array();

                        /* get last 15 lines of any logs */
                        $lh = @fopen($this->_logpath . "/" . $entry, "r");
                        if ($lh) {
                            while (($buffer = fgets($lh, 8192)) !== false) {
                                if (count($filearray) < 15) {
                                    array_push($filearray, trim($buffer));
                                } else {
                                    $junk = array_pop($filearray);
                                    array_push($filearray, trim($buffer));
                                }
                            }
                            $this->_logs[pathinfo($this->_logpath . "/" . $entry, PATHINFO_FILENAME)] = $filearray;
                        } else {

                            /* couldn't read the file */
                            $this->_logs[pathinfo($this->_logpath . "/" . $entry, PATHINFO_FILENAME)] = "Could not read file.";
                        }
                        @fclose($lh);
                    }
                }
            }
        } catch (Exception $e) {
            /* log any errors */
            $this->_global_results['errors'][] = $r->getMessage();
        }
        @closedir($h);
    }

}
