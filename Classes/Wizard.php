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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageRendererResolver;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Lang\LanguageService;

/**
 * OpenID selection wizard for the backend
 */
class Wizard extends OpenidService
{
    /**
     * OpenID of the user after authentication
     *
     * @var string
     */
    protected $claimedId;

    /**
     * Name of the form element this wizard should write the OpenID into
     *
     * @var string
     */
    protected $parentFormItemName;

    /**
     * Name of the function that needs to be called after setting the value
     *
     * @var string
     */
    protected $parentFormFieldChangeFunc;

    /**
     * Injects the request object for the current request or subrequest
     * Process the wizard and render HTML to response
     *
     * @param ServerRequestInterface $request the current request
     * @param ResponseInterface $response
     * @return ResponseInterface the response with the content
     */
    public function mainAction(ServerRequestInterface $request, ResponseInterface $response)
    {
        $post = $request->getParsedBody();
        $get = $request->getQueryParams();
        $p = $get['P'];
        if (isset($p['itemName'])) {
            $this->parentFormItemName = $p['itemName'];
        }
        if (isset($p['fieldChangeFunc']['TBE_EDITOR_fieldChanged'])) {
            $this->parentFormFieldChangeFunc = $p['fieldChangeFunc']['TBE_EDITOR_fieldChanged'];
        }

        if ($get['tx_openid_mode'] === 'finish' && $this->openIDResponse === null) {
            $this->includePHPOpenIDLibrary();
            $openIdConsumer = $this->getOpenIDConsumer();
            $this->openIDResponse = $openIdConsumer->complete($this->getReturnUrl(''));
            $this->handleResponse();
        } elseif (!empty($post['openid_url'])) {
            $openIDIdentifier = $post['openid_url'];
            $this->sendOpenIDRequest($openIDIdentifier);

            // When sendOpenIDRequest() returns, there was an error
            $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
            $flashMessage = GeneralUtility::makeInstance(
                FlashMessage::class,
                sprintf(
                    $this->getLanguageService()->sL('LLL:EXT:openid/Resources/Private/Language/locallang.xlf:error.setup'),
                    htmlspecialchars($openIDIdentifier)
                ),
                $this->getLanguageService()->sL('LLL:EXT:openid/Resources/Private/Language/locallang.xlf:title.error'),
                FlashMessage::ERROR
            );
            $flashMessageService->getMessageQueueByIdentifier()->enqueue($flashMessage);
        }

        $response->getBody()->write($this->renderContent());
        return $response;
    }

    /**
     * Return URL that shall be called by the OpenID server
     *
     * @param string $claimedIdentifier The OpenID identifier for discovery and auth request
     * @return string Full URL with protocol and hostname
     */
    protected function getReturnUrl($claimedIdentifier)
    {
        $parameters = [
            'tx_openid_mode' => 'finish',
            'P[itemName]' => $this->parentFormItemName,
            'P[fieldChangeFunc][TBE_EDITOR_fieldChanged]' => $this->parentFormFieldChangeFunc
        ];
        return GeneralUtility::getIndpEnv('TYPO3_REQUEST_HOST') . BackendUtility::getModuleUrl('wizard_openid', $parameters);
    }

    /**
     * Check OpenID response and set flash messages depending on its state
     */
    protected function handleResponse()
    {
        /** @var $flashMessageService FlashMessageService */
        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $defaultFlashMessageQueue = $flashMessageService->getMessageQueueByIdentifier();

        $lang = $this->getLanguageService();
        if (!$this->openIDResponse instanceof \Auth_OpenID_ConsumerResponse) {
            $flashMessage = GeneralUtility::makeInstance(
                FlashMessage::class,
                $lang->sL('LLL:EXT:openid/Resources/Private/Language/locallang.xlf:error.no-response'),
                $lang->sL('LLL:EXT:openid/Resources/Private/Language/locallang.xlf:title.error'),
                FlashMessage::ERROR
            );
        } elseif ($this->openIDResponse->status == Auth_OpenID_SUCCESS) {
            // all fine
            $this->claimedId = $this->getSignedParameter('openid_claimed_id');
            $flashMessage = GeneralUtility::makeInstance(
                FlashMessage::class,
                sprintf(
                    $lang->sL('LLL:EXT:openid/Resources/Private/Language/locallang.xlf:youropenid'),
                    $this->claimedId
                ),
                $lang->sL('LLL:EXT:openid/Resources/Private/Language/locallang.xlf:title.success'),
                FlashMessage::OK
            );
        } elseif ($this->openIDResponse->status == Auth_OpenID_CANCEL) {
            $flashMessage = GeneralUtility::makeInstance(
                FlashMessage::class,
                $lang->sL('LLL:EXT:openid/Resources/Private/Language/locallang.xlf:error.cancelled'),
                $lang->sL('LLL:EXT:openid/Resources/Private/Language/locallang.xlf:title.error'),
                FlashMessage::ERROR
            );
        } else {
            // another failure. show error message and form again
            $flashMessage = GeneralUtility::makeInstance(
                FlashMessage::class,
                sprintf(
                    $lang->sL('LLL:EXT:openid/Resources/Private/Language/locallang.xlf:error.general'),
                    htmlspecialchars($this->openIDResponse->status),
                    ''
                ),
                $lang->sL('LLL:EXT:openid/Resources/Private/Language/locallang.xlf:title.error'),
                FlashMessage::ERROR
            );
        }

        $defaultFlashMessageQueue->enqueue($flashMessage);
    }

    /**
     * Render HTML with message and OpenID form
     *
     * @return string
     */
    protected function renderContent()
    {
        // use FLUID standalone view for wizard content
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->getRequest()->setControllerExtensionName('openid');
        $view->setTemplateRootPaths(['EXT:openid/Resources/Private/Templates/']);
        $view->setTemplate('Wizard/Content.html');

        /** @var $flashMessageService FlashMessageService */
        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $flashMessages = $flashMessageService->getMessageQueueByIdentifier()->getAllMessagesAndFlush();

        $renderedFlashMessages = GeneralUtility::makeInstance(FlashMessageRendererResolver::class)
                                               ->resolve()
                                               ->render($flashMessages);

        $view->assign('messages', $renderedFlashMessages);
        $view->assign('formAction', BackendUtility::getModuleUrl('wizard_openid'));
        $view->assign('claimedId', $this->claimedId);
        $view->assign('parentFormItemName', $this->parentFormItemName);
        $view->assign('parentFormItemNameNoHr', strtr($this->parentFormItemName, ['_hr' => '']));
        $view->assign('parentFormFieldChangeFunc', $this->parentFormFieldChangeFunc);
        $view->assign('showForm', true);
        if (isset($_REQUEST['openid_url'])) {
            $view->assign('openid_url', $_REQUEST['openid_url']);
        }

        return $view->render();
    }

    /**
     * @return LanguageService
     */
    protected function getLanguageService()
    {
        return $GLOBALS['LANG'];
    }
}
