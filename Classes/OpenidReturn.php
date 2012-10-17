<?php
/**
 * This class is the OpenID return script for the TYPO3 Backend.
 *
 * @author 	Dmitry Dulepov <dmitry@typo3.org>
 */
class tx_openid_return {

	/**
	 * Processed Backend session creation and redirect to backend.php
	 *
	 * @return 	void
	 */
	public function main() {
		if ($GLOBALS['BE_USER']->user['uid']) {
			t3lib_div::cleanOutputBuffers();
			$backendURL = (t3lib_div::getIndpEnv('TYPO3_SITE_URL') . TYPO3_mainDir) . 'backend.php';
			t3lib_utility_Http::redirect($backendURL);
		}
	}

}

?>