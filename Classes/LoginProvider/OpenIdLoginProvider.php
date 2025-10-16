<?php

namespace FoT3\Openid\LoginProvider;

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

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\LoginProvider\LoginProviderInterface;
use TYPO3\CMS\Core\View\ViewInterface;
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * Class OpenIdLoginProvider
 */
class OpenIdLoginProvider implements LoginProviderInterface
{
    /** @inheritdoc */
    public function modifyView(ServerRequestInterface $request, ViewInterface $view): string
    {
        $queryParams = $request->getQueryParams();
        $view->assign('presetOpenId', $queryParams['openid_url'] ?? '');

        return 'OpenidLogin.html';
    }
}
