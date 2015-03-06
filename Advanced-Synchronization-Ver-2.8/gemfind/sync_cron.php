<?php
//Error reporting
error_reporting(E_ALL);
ini_set('display_errors','On');

require_once(dirname(__FILE__)."/../app/Mage.php");
umask(0);
Mage::app();
	
// log notification to finished of sync process
Mage::log('Begining of gemfind/sync_cron.php..........', null, 'gemfind_csv.log');
if (is_cli()) {
	Mage::log('Before sync at gemfind/sync_cron.php..........', null, 'gemfind_csv.log');
	$synchronization = Mage::getModel("synchronization/synchronization");	
	$synchronization->runSynchronization();
	Mage::log('End of gemfind/sync_cron.php..........', null, 'gemfind_csv.log');
} 	
	

function is_cli()
{	Mage::log('At Cli to check sapi name of gemfind/sync_cron.php..........'.php_sapi_name(), null, 'gemfind_csv.log');
    return php_sapi_name() === 'cli';
}


?>