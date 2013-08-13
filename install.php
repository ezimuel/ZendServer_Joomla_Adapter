<?php
/**
 * Zend Server cache adapters for Joomla! installer
 *
 * @todo manage different Joomla! versions
 *
 * @author  Enrico Zimuel (enrico@zimuel.it)
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
  
if (!isset($argv[1])) {
    printf("Usage: install.php <path_to_joomla>\n");
    exit(2);
}
    
$basePath = $argv[1];

$versionFile = "$basePath/libraries/cms/version/version.php";
define ('_JEXEC', true);

if (file_exists($versionFile)) {
    include_once "$versionFile";
    $ver = new JVersion();
    $version = $ver->getShortVersion();
    printf("Recognized a Joomla! $version installation\n");
    if (substr($version, 0, 1) === '3') {
        printf("Installing the Zend Server cache adapter...\n");
        // copy the files
        if (!copy("joomla3/zendserverdisk.php", "$basePath/libraries/joomla/cache/storage/zendserverdisk.php")) {
            printf("Error occurs during the copy of zendserverdisk.php\n");
        }    
        if (!copy("joomla3/zendservershm.php", "$basePath/libraries/joomla/cache/storage/zendservershm.php")) {
            printf("Error occurs during the copy of zendservershm.php\n");
        }
        $adminView = "$basePath/administrator/language/en-GB/en-GB.lib_joomla.ini"; 
        // add the zend server cache adapters in the view
        $config  = parse_ini_file($adminView);
        $toWrite = '';
        if (!isset($config['JLIB_FORM_VALUE_CACHE_ZENDSERVERDISK'])) {
            $toWrite .= "JLIB_FORM_VALUE_CACHE_ZENDSERVERDISK=\"Zend Server Disk\"\n";
        }
        if (!isset($config['JLIB_FORM_VALUE_CACHE_ZENDSERVERSHM'])) {
            $toWrite .= "JLIB_FORM_VALUE_CACHE_ZENDSERVERSHM=\"Zend Server Shm\"\n";
        }
        if (!empty($toWrite)) {
            file_put_contents($adminView, file_get_contents($adminView) . ";Zend Server cache adapters\n" . $toWrite);
        }
    }
    printf("...done.\n");
}
