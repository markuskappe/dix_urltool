<?php
namespace DIX\DixUrltool\Controller;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2016 Markus Kappe <markus.kappe@dix.at>, DIX web.solutions
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
use TYPO3\CMS\Core\Utility\GeneralUtility;


use PhpParser\Error;
use PhpParser\ParserFactory;

include_once(PATH_typo3conf.'ext/dix_urltool/vendor/vendor/autoload.php');

/**
 * UrltoolController
 */
class UrltoolController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController {
	
	private $localconfFile = 'typo3conf/AdditionalConfiguration.php';
	private $urltool404File = 'typo3conf/urltoolconf_404.php';
	private $urltoolrealurlFile = 'typo3conf/urltoolconf_realurl.php';
	private $urltoolrealurlDefaultFile = 'typo3conf/ext/dix_urltool/Resources/Public/defaultrealurl.txt';
	

	//$GLOBALS['TYPO3_CONF_VARS']['FE']['pageNotFound_handling'] = 'USER_FUNCTION:EXT:dix_urltool/Classes/Controller/UrltoolController.php:DIX\\DixUrltool\\Controller\\UrltoolController->render404';
	public function render404($params, &$pObj) {
		$url = sprintf('index.php?id=%s&L=%d', urlencode($GLOBALS['TYPO3_CONF_VARS']['FE']['pageNotFound_handling_pageId']), GeneralUtility::_GP('L'));
		return $pObj->pageErrorHandler($url, '', $params['reason']);
	}
	
	public function initializeAction() {
		$this->localconfFile = constant('PATH_site') . $this->localconfFile;
		$this->urltool404File = constant('PATH_site') . $this->urltool404File;
		$this->urltoolrealurlFile = constant('PATH_site') . $this->urltoolrealurlFile;
		$this->urltoolrealurlDefaultFile = constant('PATH_site') . $this->urltoolrealurlDefaultFile;
	}

	/**
	 * action list
	 *
	 * @return void
	 */
	public function indexAction() {
		$this->secure();
	}

	public function configAction() {
		$this->secure();

		$vars['activated'] = $this->isActivated('urltoolconf_realurl.php');
		$vars['cfg'] = $this->request->hasArgument('configdata') ? $this->request->getArgument('configdata')['cfg'] : GeneralUtility::getURL($this->urltoolrealurlFile);
		$this->view->assign('values', $vars);
	}
	
	// check AdditionalConfiguration.php for include
	private function isActivated($filename) {
		$filecontent=GeneralUtility::getURL($this->localconfFile);		
		return (strpos($filecontent, "include(PATH_typo3conf.'$filename')") !== false);
	}
	
	public function dnfAction() { // 404
		$this->secure();

		$vars['activated'] = $this->isActivated('urltoolconf_404.php');		

		$vars['header'] = $GLOBALS['TYPO3_CONF_VARS']['FE']['pageNotFound_handling_statheader'];
		$vars['headersel'] = ($vars['header'] == 'HTTP/1.0 404 Not Found' || $vars['header'] == '') ? '404' : 'custom';
		
		$temp = $GLOBALS['TYPO3_CONF_VARS']['FE']['pageNotFound_handling'];
		if ($temp === '') {
			$vars['mode'] = 'rootline';
		} elseif ($temp === '1') {
			$vars['mode'] = 'error';
		} elseif (strstr($temp, 'REDIRECT:')) {
			$vars['mode'] = 'redirect';
			$vars['adddata'] = substr($temp, 9);
		} elseif (strstr($temp, 'READFILE:')) {
			$vars['mode'] = 'file';
			$vars['adddata'] = substr($temp, 9);
		} elseif (strstr($temp, 'USER_FUNCTION:EXT:dix_urltool')) {
			$vars['mode'] = 'page';
			$vars['adddata'] = $GLOBALS['TYPO3_CONF_VARS']['FE']['pageNotFound_handling_pageId'];
		} elseif (strstr($temp, 'USER_FUNCTION:')) {
			$vars['mode'] = 'userfunc';
			$vars['adddata'] = substr($temp, 14);
		} else {
			$vars['mode'] = 'rootline';
		}
		
		$this->view->assign('values', $vars);
	}
	
	public function initializeGetdefaultconfigAction() {
		$this->defaultViewObjectName = 'TYPO3\CMS\Extbase\Mvc\View\JsonView';
	}

	public function getdefaultconfigAction() {
		$filecontent = GeneralUtility::getURL($this->urltoolrealurlDefaultFile);
		
		$result = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'sys_domain', 'hidden=0');
		$multidomaincnt = "\n\$domains = array(\n	'_DEFAULT' => '1',\n";
		if ($GLOBALS['TYPO3_DB']->sql_num_rows($result)) {
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result)) {
				$multidomaincnt .= "	'" . $row['domainName'] . "' => '" . $row['pid'] . "',\n";
			}
		}
		$multidomaincnt .= ");\n";
		$multidomaincnt .= "foreach (\$domains as \$domain=>\$pid) {" . "\n";
		$multidomaincnt .= "	\$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl'][\$domain] = \$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['_DEFAULT'];" . "\n";
		$multidomaincnt .= "	\$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl'][\$domain]['pagePath']['rootpage_id'] = \$pid;" . "\n";
		$multidomaincnt .= "}" . "\n";
		$multidomaincnt .= "?>" . "\n";

		return json_encode($filecontent . $multidomaincnt);
	}

	public function setconfigAction() {
		$this->secure();
		$vars = $this->request->getArgument('configdata');

		$success = $this->writeConfiguration($this->urltoolrealurlFile, $vars['cfg']);

		if ($success) {
			$this->writeLocalConfiguration('urltoolconf_realurl.php', 'RealUrl-Configuration inserted by extension dix_urltool', $vars['activate'] && is_file($this->urltoolrealurlFile));
		}
		//$this->redirect('config', 'Urltool', 'dix_urltool', array('configdata'=>$vars));
		$this->forward('config');
	}

	public function setdnfAction() { // 404
		$this->secure();
		$vars = $this->request->getArgument('dnfdata');
		$addline = '';
		switch($vars['mode']) {
			case 'rootline':
				$errurl = '';
				break;
			case 'error':
				$errurl = true;
				break;
			case 'page':
				$d = $vars['adddata'];
				$errurl = 'USER_FUNCTION:EXT:dix_urltool/Classes/Controller/UrltoolController.php:DIX\\DixUrltool\\Controller\\UrltoolController->render404';
				$addline = '$'."GLOBALS['TYPO3_CONF_VARS']['FE']['pageNotFound_handling_pageId'] = ". "'".addslashes($d)."';\n";
				$GLOBALS['TYPO3_CONF_VARS']['FE']['pageNotFound_handling_pageId'] = $d;
				break;
			case 'redirect':
				$errurl = 'REDIRECT:'.$vars['adddata'];
				break;
			case 'file':
				$errurl = 'READFILE:'.$vars['adddata'];
				break;
			case 'userfunc':
				$errurl = 'USER_FUNCTION:'.$vars['adddata'];
				break;
		}
		$header = $vars['headersel'] == '404' ? 'HTTP/1.0 404 Not Found' : $vars['customheader'];
			
		$filecontent = "<?php \n";
		$filecontent .= '$'."GLOBALS['TYPO3_CONF_VARS']['FE']['pageNotFound_handling'] = ". "'".addslashes($errurl)."';\n";
		$filecontent .= '$'."GLOBALS['TYPO3_CONF_VARS']['FE']['pageNotFound_handling_statheader'] = ". "'".addslashes($header)."';\n";
		$filecontent .= $addline;
		$filecontent .= "?>";

		$success = $this->writeConfiguration($this->urltool404File, $filecontent);

		if ($success) {
			$this->writeLocalConfiguration('urltoolconf_404.php', '404-Handling inserted by extension dix_urltool', $vars['activate'] && is_file($this->urltool404File));
			$GLOBALS['TYPO3_CONF_VARS']['FE']['pageNotFound_handling'] = $errurl;
			$GLOBALS['TYPO3_CONF_VARS']['FE']['pageNotFound_handling_statheader'] = $header;
		}

		$this->forward('dnf');
	}
	
	private function writeConfiguration($filename, $filecontent) {
		if ($this->checkSyntax($filecontent) ) {    
			$fh = fopen($filename,'w');
			if (fwrite($fh,$filecontent)) {
				$this->addFlashMessage('Config saved');
				fclose($fh);
			} else {
				$this->addFlashMessage('Problem saving config ', '', \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR);
				return false;
			}
			return true;
		}
		return false;
	}
	
	private function writeLocalConfiguration($filename, $comment, $activate) {
		// remove include/php-end-tag in localconf.php
		$filecontent= GeneralUtility::getURL($this->localconfFile);
		if (!$filecontent) {
			$filecontent = "<?php\n";
		}
		$lines=explode("\n",$filecontent);
		foreach ($lines as $line_num => $line) {
			if (strpos($line, $comment)) {
				unset($lines[$line_num]);
			}
			if (trim($line)=='?>') {
				unset($lines[$line_num]);
				if( trim($lines[$line_num-1])=='' )
					unset($lines[$line_num-1]);
				if( trim($lines[$line_num-2])=='' )
					unset($lines[$line_num-2]);
				if( trim($lines[$line_num-3])=='' )
					unset($lines[$line_num-3]);
			}
		}			
	
		// add include to localconf.php
		$filecontent=implode("\n",$lines);
		if ($activate) {
			$filecontent .= "\n@include(PATH_typo3conf.'$filename'); // $comment";
		}
		$filecontent .= "\n?>\n";

		if ($this->checkSyntax($filecontent) ) {    
			$fh = fopen($this->localconfFile,'w');
			if (fwrite($fh,$filecontent)) {
				if ($activate) {
					$this->addFlashMessage('Configuration activated');
				}
				fclose($fh);
			} else {
				$this->addFlashMessage('Problem writing configuration file', '', \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR);
			}
		}
	}

	private function checkSyntax($code) {
		$parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
		try {
			$parser->parse($code); // throws error if syntax is not ok
		} catch (Error $e) {
			// error in syntax !
			$this->addFlashMessage('Error in syntax: '. htmlspecialchars($e->getMessage()), '', \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR);
			return false;
		}
		return true;
	}
	
	private function secure() {
		if (!isset($GLOBALS['BE_USER']) || !$GLOBALS['BE_USER']->isAdmin()) { throw new \Exception('You have to be logged in as an Admin in TYPO3 backend to use the UrlTool'); }
	}

}