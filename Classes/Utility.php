<?php
namespace DIX\DixUrltool;

class Utility extends \DmitryDulepov\Realurl\Utility {
	/**
	 * Converts a given string to a string that can be used as a URL segment.
	 * The result is not url-encoded.
	 *
	 * @param string $processedTitle
	 * @param string $spaceCharacter
	 * @param bool $strToLower
	 * @return string
	 */
	public function convertToSafeString($processedTitle, $spaceCharacter = '-', $strToLower = true) {
		$extConf = isset($GLOBALS["TYPO3_CONF_VARS"]["EXT"]["extConf"]["dix_urltool"]) ? unserialize($GLOBALS["TYPO3_CONF_VARS"]["EXT"]["extConf"]["dix_urltool"]) : array();
		if ($strToLower) {
			$processedTitle = $this->csConvertor->conv_case('utf-8', $processedTitle, 'toLower');
		}
		$processedTitle = strip_tags($processedTitle);
		$processedTitle = preg_replace('/[ \t\x{00A0}\-+_]+/u', $spaceCharacter, $processedTitle);

		/** @todo */
		if ($extConf['allowUmlauts']) { // check condition: if umlaut-feature enabled
			// do not change umlauts
		} else {
			$processedTitle = $this->csConvertor->specCharsToASCII('utf-8', $processedTitle);
		}
		$processedTitle = preg_replace('/[^\p{L}0-9' . preg_quote($spaceCharacter) . ']/u', '', $processedTitle);
		$processedTitle = preg_replace('/' . preg_quote($spaceCharacter) . '{2,}/', $spaceCharacter, $processedTitle);
		$processedTitle = trim($processedTitle, $spaceCharacter);

		// TODO Post-processing hook here

		if ($strToLower) {
			$processedTitle = strtolower($processedTitle);
		} 
		return $processedTitle;
	}
}