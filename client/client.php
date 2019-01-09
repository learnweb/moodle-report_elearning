<?php
// This client for local_wstemplate is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//

/**
 * XMLRPC client for Moodle 2 - local_wstemplate
 *
 * This script does not depend of any Moodle code,
 * and it can be called from a browser.
 *
 * @authorr Jerome Mouneyrac
 */

/// MOODLE ADMINISTRATION SETUP STEPS
// 1- Install the plugin
// 2- Enable web service advance feature (Admin > Advanced features)
// 3- Enable XMLRPC protocol (Admin > Plugins > Web services > Manage protocols)
// 4- Create a token for a specific user and for the service 'My service' (Admin > Plugins > Web services > Manage tokens)
// 5- Run this script directly from your browser: you should see 'Hello, FIRSTNAME'

/// SETUP - NEED TO BE CHANGED
$token = 'b04208c2eeb176012282999b87c1e0c4';
$domainname = 'http://172.17.0.1/moodle';

/// FUNCTION NAME
$functionname = 'report_elearning_prometheus_endpoint';

/// PARAMETER TWEAKS
// set to a categoryid other than 0 to just fetch that category
$categoryid = 0;
// set this to true to skip hidden activities
$onlyvisible = false;
// set this to true to disable the count of the news forum
$nonews = false;

///// XML-RPC CALL
header('Content-Type: text/plain');
$serverurl = $domainname . '/webservice/xmlrpc/server.php'. '?wstoken=' . $token;
require_once('./curl.php');
$curl = new curl;
$post = xmlrpc_encode_request($functionname, array($categoryid, $onlyvisible, $nonews));
$resp = xmlrpc_decode($curl->post($serverurl, $post));
print_r($resp);
