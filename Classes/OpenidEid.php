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
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Utility\EidUtility;

/**
 * This class is the OpenID return script for the TYPO3 Frontend.
 */
class OpenidEid
{
    /**
     * Process request
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface|null
     */
    public function processRequest(ServerRequestInterface $request, ResponseInterface $response)
    {
        // Due to the nature of OpenID (redirections, etc) we need to force user
        // session fetching if there is no session around. This ensures that
        // our service is called even if there is no login data in the request.
        // Inside the service we will process OpenID response and authenticate
        // the user.
        $GLOBALS['TYPO3_CONF_VARS']['SVCONF']['auth']['setup']['FE_fetchUserIfNoSession'] = true;
        // Initialize Frontend user
        EidUtility::initFeUser();
        // Redirect to the original location in any case (authenticated or not)
        @ob_end_clean();

        $post = $request->getParsedBody();
        $get = $request->getQueryParams();
        $location = isset($post['tx_openid_location']) ? $post['tx_openid_location'] : $get['tx_openid_location'];
        $signature = isset($post['tx_openid_location_signature']) ? $post['tx_openid_location_signature'] : $get['tx_openid_location_signature'];
        if (GeneralUtility::hmac($location, 'openid') === $signature) {
            $response = $response->withHeader('Location', GeneralUtility::locationHeaderUrl($location))->withStatus(303);
        }

        return $response;
    }
}
