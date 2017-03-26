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

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

require_once \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('openid') . 'lib/php-openid/Auth/OpenID/Interface.php';

/**
 * This class is a TYPO3-specific OpenID store.
 */
class OpenidStore extends \Auth_OpenID_OpenIDStore
{
    const ASSOCIATION_TABLE_NAME = 'tx_openid_assoc_store';
    const NONCE_TABLE_NAME = 'tx_openid_nonce_store';
    /* 2 minutes */
    const ASSOCIATION_EXPIRATION_SAFETY_INTERVAL = 120;
    /* 10 days */
    const NONCE_STORAGE_TIME = 864000;

    /**
     * Sores the association for future use
     *
     * @param string $serverUrl Server URL
     * @param \Auth_OpenID_Association $association OpenID association
     * @return void
     */
    public function storeAssociation($serverUrl, $association)
    {
        $builder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::ASSOCIATION_TABLE_NAME);
        $builder->getRestrictions()->removeAll();

        $builder->getConnection()->beginTransaction();

        $existingAssociations = $builder
            ->count('*')
            ->from(self::ASSOCIATION_TABLE_NAME)
            ->where(
                $builder->expr()->eq('server_url', $builder->createNamedParameter($serverUrl)),
                $builder->expr()->eq('assoc_handle', $builder->createNamedParameter($association->handle)),
                $builder->expr()->eq('expires', $builder->createNamedParameter(time(), \PDO::PARAM_INT))
            )
            ->execute()
            ->fetchColumn();

        if ($existingAssociations) {
            $builder
                ->update(self::ASSOCIATION_TABLE_NAME)
                ->values([
                    'content' => base64_encode(serialize($association)),
                    'tstamp' => time()
                ])
                ->where(
                    $builder->expr()->eq('server_url', $builder->createNamedParameter($serverUrl)),
                    $builder->expr()->eq('assoc_handle', $builder->createNamedParameter($association->handle)),
                    $builder->expr()->eq('expires', $builder->createNamedParameter(time(), \PDO::PARAM_INT))
                )
                ->execute();
        } else {
            // In the next query we can get race conditions. sha1_hash prevents many associations from being stored for one server
            $builder
                ->insert(self::ASSOCIATION_TABLE_NAME)
                ->values([
                    'assoc_handle' => $association->handle,
                    'content' => base64_encode(serialize($association)),
                    'crdate' => $association->issued,
                    'tstamp' => time(),
                    'expires' => $association->issued + $association->lifetime - self::ASSOCIATION_EXPIRATION_SAFETY_INTERVAL,
                    'server_url' => $serverUrl
                ])
                ->execute();
        }

        $builder->getConnection()->commit();
    }

    /**
     * Removes all expired associations.
     *
     * @return int A number of removed associations
     */
    public function cleanupAssociations()
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::ASSOCIATION_TABLE_NAME);
        $queryBuilder->getRestrictions()->removeAll();
        return $queryBuilder->delete(self::ASSOCIATION_TABLE_NAME)->where('expires <= ' . time())->execute()->rowCount();
    }

    /**
     * Obtains the association to the server
     *
     * @param string $serverUrl Server URL
     * @param string $handle Association handle (optional)
     * @return \Auth_OpenID_Association
     */
    public function getAssociation($serverUrl, $handle = null)
    {
        $this->cleanupAssociations();
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::ASSOCIATION_TABLE_NAME);
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder->select('uid', 'content')->from(self::ASSOCIATION_TABLE_NAME)->where(
            $queryBuilder->expr()->eq('server_url', $queryBuilder->createNamedParameter($serverUrl)),
            $queryBuilder->expr()->eq('expires', $queryBuilder->createNamedParameter(time(), \PDO::PARAM_INT))
        );
        if ($handle !== null) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq('assoc_handle', $queryBuilder->createNamedParameter($handle)));
        } else {
            $queryBuilder->orderBy('tstamp', 'DESC');
        }
        $row = $queryBuilder->execute()->fetch();
        $result = null;
        if (is_array($row)) {
            $result = @unserialize(base64_decode($row['content']));
            if ($result === false) {
                $result = null;
            } else {
                $queryBuilder
                    ->update(self::ASSOCIATION_TABLE_NAME)
                    ->values(['tstamp' => time()])
                    ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($row['uid'], \PDO::PARAM_INT)))
                    ->execute();
            }
        }
        return $result;
    }

    /**
     * Removes the association
     *
     * @param string $serverUrl Server URL
     * @param string $handle Association handle (optional)
     * @return bool TRUE if the association existed
     */
    public function removeAssociation($serverUrl, $handle)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::ASSOCIATION_TABLE_NAME);
        $queryBuilder->getRestrictions()->removeAll();
        $deletedCount = $queryBuilder
            ->delete(self::ASSOCIATION_TABLE_NAME)->where(
                $queryBuilder->expr()->eq('server_url', $queryBuilder->createNamedParameter($serverUrl)),
                $queryBuilder->expr()->eq('assoc_handle', $queryBuilder->createNamedParameter($handle))
            )->execute()->rowCount();

        return $deletedCount > 0;
    }

    /**
     * Removes old nonces
     *
     * @return void
     */
    public function cleanupNonces()
    {
        $where = 'crdate < ' . (time() - self::NONCE_STORAGE_TIME);
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::NONCE_TABLE_NAME);
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder->delete(self::NONCE_TABLE_NAME)->where($where)->execute();
    }

    /**
     * Checks if this nonce was already used
     *
     * @param string $serverUrl Server URL
     * @param int $timestamp Time stamp
     * @param string $salt Nonce value
     * @return bool TRUE if nonce was not used before anc can be used now
     */
    public function useNonce($serverUrl, $timestamp, $salt)
    {
        $result = false;
        if (abs($timestamp - time()) < $GLOBALS['Auth_OpenID_SKEW']) {
            $values = [
                'crdate' => time(),
                'salt' => $salt,
                'server_url' => $serverUrl,
                'tstamp' => $timestamp
            ];
            $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable(self::NONCE_TABLE_NAME);
            $affectedRows = $connection->createQueryBuilder()->insert(self::NONCE_TABLE_NAME)->values($values)->execute()->rowCount();
            $result = $affectedRows > 0;
        }
        return $result;
    }

    /**
     * Resets the store by removing all data in it
     *
     * @return void
     */
    public function reset()
    {
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $connectionPool->getConnectionForTable(self::ASSOCIATION_TABLE_NAME)->truncate(self::ASSOCIATION_TABLE_NAME);
        $connectionPool->getConnectionForTable(self::NONCE_TABLE_NAME)->truncate(self::NONCE_TABLE_NAME);
    }
}
