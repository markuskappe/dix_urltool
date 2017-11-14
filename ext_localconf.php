<?php

if (!defined('TYPO3_MODE')) {
	die('Access denied.');
}

$TYPO3_CONF_VARS['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearCachePostProc']['dixurltool'] = 'DixFlushRealurlCache';

if (!function_exists('DixFlushRealurlCache')) {
	
	function DixFlushRealurlCache(&$params, &$pObj) {
		if ($params['cacheCmd'] == 'system') {
			$cache = \DmitryDulepov\Realurl\Cache\CacheFactory::getCache();
			$cache->clearUrlCache();
		}
	}
}


$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects']['DmitryDulepov\\Realurl\\Utility'] = array(
   'className' => 'DIX\\DixUrltool\\Utility'
);