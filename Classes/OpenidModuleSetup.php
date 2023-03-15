<?php

namespace FoT3\Openid;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Service\ImageService;

/**
 * This class is the OpenID return script for the TYPO3 Backend (used in the user-settings module).
 */
class OpenidModuleSetup
{
    /**
     * Checks weather BE user has access to change its OpenID identifier
     *
     * @return bool Whether it is allowed to modify the given field
     */
    public function accessLevelCheck()
    {
        $setupConfig = $this->getBackendUser()->getTSConfig();
        return empty($setupConfig['setup.']['fields.']['tx_openid_openid.']['disabled']);
    }

    /**
     * Render OpenID identifier field for user setup
     *
     * @return string HTML input field to change the OpenId
     */
    public function renderOpenID()
    {
        $openid = $this->getBackendUser()->user['tx_openid_openid'];
        $add = htmlspecialchars(
            $this->getLanguageService()->sL('LLL:EXT:openid/Resources/Private/Language/locallang.xlf:addopenid')
        );

        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $parameters = ['P[itemName]' => 'data[be_users][tx_openid_openid]'];
        $popUpUrl = GeneralUtility::quoteJSvalue($uriBuilder->buildUriFromRoute('wizard_openid', $parameters));

        $imageService = GeneralUtility::makeInstance(ImageService::class);
        $image = $imageService->getImage('EXT:openid/Resources/Public/Icons/login-icon.svg', null, false);

        return '<div class="input-group">' .
            '<input id="field_tx_openid_openid"' .
            ' class="form-control"' .
            ' type="text" name="data[be_users][tx_openid_openid]"' .
            ' value="' . htmlspecialchars($openid) . '" />' .
            '<div class="input-group-addon">' .
                '<a href="#" onclick="' .
                'vHWin=window.open(' . $popUpUrl . ',null,\'width=800,height=600,status=0,menubar=0,scrollbars=0\');' .
                'vHWin.focus();return false;' .
                '">' .
                    '<img src="' . htmlspecialchars($image->getPublicUrl()) . '" alt="' . $add . '" title="' . $add . '" width="16" height="16"/>' .
                '</a>' .
            '</div>' .
            '</div>';
    }

    /**
     * @return BackendUserAuthentication
     */
    protected function getBackendUser()
    {
        return $GLOBALS['BE_USER'];
    }

    /**
     * @return \TYPO3\CMS\Core\Localization\LanguageService
     */
    protected function getLanguageService()
    {
        return $GLOBALS['LANG'];
    }
}
