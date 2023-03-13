<?php

namespace FoT3\Openid\Event;

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

use TYPO3\CMS\Core\Authentication\Event\BeforeRequestTokenProcessedEvent;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Security\RequestToken;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This class contains an event listener that restores login form token after
 * OpenID redirections.
 *
 * Security-related:
 * ---
 * Token ID is never seen outside of server communication
 * between TYPO3 and OpenID server, so it cannot be intercepted by the person,
 * who tries to login, or any 3rd party.
 *
 * According to the documentation (see link below), this is correct usage of the event:
 * > Scenarios that are not using a login callback without having the possibility
 * > to submit a request-token, \TYPO3\CMS\Core\Authentication\Event\BeforeRequestTokenProcessedEvent
 * > can be used to generate the token individually.
 *
 * Note that we could simply generate the token here to let the process proceed
 * as shown in the documentation but this would undermine the idea that the token
 * should be valid. So we return previously submitted token to continue the process.
 * This is not token reuse because the token was not check prior to this point!
 *
 * @author Dmitry Dulepov <dmitry.dulepov@gmail.com>
 * @see \TYPO3\CMS\Core\Authentication\AbstractUserAuthentication::checkAuthentication()
 * @see https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/12.0/Feature-97305-IntroduceCSRF-likeRequest-tokenHandling.html?highlight=beforerequesttokenprocessedevent#intercept-adjust-request-token
 */
class RestoreRequestToken
{
    /**
     * Handles the request token.
     *
     * @param \TYPO3\CMS\Core\Authentication\Event\BeforeRequestTokenProcessedEvent $event
     * @throws \Doctrine\DBAL\Exception
     */
    public function __invoke(BeforeRequestTokenProcessedEvent $event): void
    {
        $tokenId = GeneralUtility::_GET('tx_openid_token');
        if ($tokenId) {
            $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('tx_openid_token');

            self::cleanUpExpiredTokens($connection);
            $token = self::getToken($connection, $tokenId);
            if ($token instanceof RequestToken) {
                self::removeToken($connection, $tokenId);
                $event->setRequestToken($token);
            }
        }
    }

    /**
     * Removes expired tokens (after 10 minutes).
     *
     * @param \TYPO3\CMS\Core\Database\Connection $connection
     */
    protected static function cleanUpExpiredTokens(Connection $connection): void
    {
        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder->delete('tx_openid_token')
            ->where(
                $queryBuilder->expr()->lt('crdate', $queryBuilder->createNamedParameter(time() - 600))
            )
            ->executeStatement()
        ;
    }

    /**
     * Fetches the token by id.
     *
     * @param \TYPO3\CMS\Core\Database\Connection $connection
     * @param string $tokenId
     * @return \TYPO3\CMS\Core\Security\RequestToken|null
     * @throws \Doctrine\DBAL\Exception
     */
    protected static function getToken(Connection $connection, string $tokenId): ?RequestToken
    {
        $queryBuilder = $connection->createQueryBuilder();
        $serizlizedToken = $queryBuilder->select('token')
            ->from('tx_openid_token')
            ->where(
                $queryBuilder->expr()->eq('token_id', $queryBuilder->createNamedParameter($tokenId))
            )
            ->executeQuery()
            ->fetchOne()
        ;

        return $serizlizedToken ? @unserialize($serizlizedToken) : null;
    }

    /**
     * Removes the token by id.
     *
     * @param \TYPO3\CMS\Core\Database\Connection $connection
     * @param string $tokenId
     */
    protected static function removeToken(Connection $connection, string $tokenId): void
    {
        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder->delete('tx_openid_token')
            ->where(
                $queryBuilder->expr()->eq('token_id', $queryBuilder->createNamedParameter($tokenId))
            )
            ->executeStatement()
        ;
    }
}
