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

use Doctrine\DBAL\ArrayParameterType;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Authentication\AbstractUserAuthentication;
use TYPO3\CMS\Core\Authentication\AuthenticationService;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\SecurityAspect;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Crypto\Random;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;

require_once ExtensionManagementUtility::extPath('openid') . 'lib/php-openid/Auth/OpenID/Interface.php';

/**
 * Service "OpenID Authentication" for the "openid" extension.
 */
class OpenidService extends AuthenticationService implements LoggerAwareInterface, SingletonInterface
{
    use LoggerAwareTrait;

    /**
     * Login data as passed to initAuth()
     *
     * @var array
     */
    protected array $loginData = [];

    /**
     * Additional authentication information provided by AbstractUserAuthentication.
     * We use it to decide what database table contains user records.
     *
     * @var array
     */
    protected array $authenticationInformation = [];

    /**
     * OpenID response object. It is initialized when OpenID provider returns
     * with success/failure response to us.
     *
     * @var ?\Auth_OpenID_ConsumerResponse
     */
    protected \Auth_OpenID_ConsumerResponse|null $openIDResponse = null;

    /**
     * A reference to the calling object
     *
     * @var AbstractUserAuthentication
     */
    protected AbstractUserAuthentication $parentObject;

    /**
     * If set to TRUE, than libraries are already included.
     *
     * @var bool
     */
    protected static bool $openIDLibrariesIncluded = false;

    /**
     * Checks if service is available,. In case of this service we check that
     * prerequisites for "PHP OpenID" libraries are fulfilled:
     * - GMP or BCMATH PHP extensions are installed and functional
     * - set_include_path() PHP function is available
     *
     * @return bool TRUE if service is available
     */
    public function init(): bool
    {
        $available = false;
        if (extension_loaded('gmp')) {
            $available = is_callable('gmp_init');
        } elseif (extension_loaded('bcmath')) {
            $available = is_callable('bcadd');
        } else {
            $this->logger->warning('Neither bcmath, nor gmp PHP extension found. OpenID authentication will not be available.');
        }
        // We also need set_include_path() PHP function
        if (!is_callable('set_include_path')) {
            $available = false;
            $this->logger->warning('set_include_path() PHP function is not available. OpenID authentication is disabled.');
        }

        // Check that we can actually authenticate
        if (str_ends_with($this->info['requestedServiceSubType'], 'BE') && $GLOBALS['TYPO3_CONF_VARS']['BE']['cookieSameSite'] !== 'lax') {
            $available = false;
            $this->logger->alert('You need to set $GLOBALS[\'TYPO3_CONF_VARS\'][\'BE\'][\'cookieSameSite\'] to \'lax\' to use EXT:openid.');
        }

        return $available && parent::init();
    }

    /**
     * Initializes authentication for this service.
     *
     * @param string $mode: Subtype for authentication (either "getUserFE" or "getUserBE")
     * @param array $loginData: Login data submitted by user and preprocessed by AbstractUserAuthentication
     * @param array $authenticationInformation: Additional TYPO3 information for authentication services (unused here)
     * @param AbstractUserAuthentication $parentObject Calling object
     */
    public function initAuth($mode, $loginData, $authenticationInformation, $parentObject): void
    {
        $this->authenticationInformation = $authenticationInformation;
        $this->loginData = $loginData;
        $this->parentObject = $parentObject;

        // If we are here after authentication by the OpenID server, get its response.
        if (($_GET['tx_openid_mode'] ?? '') === 'finish' && $this->openIDResponse === null) {
            $this->includePHPOpenIDLibrary();
            $openIDConsumer = $this->getOpenIDConsumer();
            $this->openIDResponse = $openIDConsumer->complete($this->getReturnURL($_GET['tx_openid_claimed']));
        }
    }

    /**
     * Process the submitted OpenID URL if valid.
     *
     * @param array $loginData Credentials that are submitted and potentially modified by other services
     * @param string $passwordTransmissionStrategy Keyword of how the password has been hashed or encrypted before submission
     * @return int
     */
    public function processLoginData(array &$loginData, $passwordTransmissionStrategy)
    {
        $isProcessed = 0;
        if ($this->openIDResponse) {
            if ($this->openIDResponse instanceof \Auth_OpenID_ConsumerResponse &&
                $this->openIDResponse->status === Auth_OpenID_SUCCESS
            ) {
                $isProcessed = is_array($this->getUserRecord($this->getFinalOpenIDIdentifier())) ? 200 : 0;
            }
        } elseif (empty($loginData['uident_text'])) {
            try {
                $openIdUrl = $_POST['openid_url'] ?? '';
                if (!empty($openIdUrl)) {
                    $loginData['uident_openid'] = $this->normalizeOpenID($openIdUrl);
                    $isProcessed = is_array($this->getUserRecord($loginData['uident_openid'])) ? 200 : 0;
                } elseif (!empty($loginData['uname'])) {
                    // It might be the case that during frontend login the OpenID URL is submitted in the username field
                    // Since we are a low priority service, and no password has been submitted it is OK to just assume
                    // we might have gotten an OpenID URL
                    $loginData['uident_openid'] = $this->normalizeOpenID($loginData['uname']);
                    $isProcessed = is_array($this->getUserRecord($loginData['uident_openid'])) ? 200 : 0;
                }
            } catch (\Exception $exception) {
                $this->logger->error(
                    sprintf(
                        '[%d] "%s" %s',
                        $exception->getCode(),
                        $exception->getMessage(),
                        $exception->getTraceAsString()
                    )
                );
            }
        }
        // Fill in other fields because TYPO3 checks them even though they are not needed for us and not submitted
        $loginData = array_map(function ($value) {
            return $value ?? '';
        }, $loginData);

        return $isProcessed;
    }

    /**
     * This function returns the user record back to the AbstractUserAuthentication.
     * It does not mean that user is authenticated, it means only that user is found. This
     * function makes sure that user cannot be authenticated by any other service
     * if user tries to use OpenID to authenticate.
     *
     * @return mixed User record (content of fe_users/be_users as appropriate for the current mode)
     */
    public function getUser()
    {
        if ($this->loginData['status'] !== 'login') {
            return null;
        }
        $userRecord = null;
        if ($this->openIDResponse instanceof \Auth_OpenID_ConsumerResponse) {
            // We are running inside the OpenID return script
            // Note: we cannot use $this->openIDResponse->getDisplayIdentifier()
            // because it may return a different identifier. For example,
            // LiveJournal server converts all underscore characters in the
            // original identfier to dashes.
            if ($this->openIDResponse->status === Auth_OpenID_SUCCESS) {
                $openIDIdentifier = $this->getFinalOpenIDIdentifier();
                if ($openIDIdentifier) {
                    $userRecord = $this->getUserRecord($openIDIdentifier);
                    if (!empty($userRecord) && is_array($userRecord)) {
                        // The above function will return user record from the OpenID. It means that
                        // user actually tried to authenticate using his OpenID. In this case
                        // we must change the password in the record to a long random string so
                        // that this user cannot be authenticated with other service.
                        $userRecord[$this->authenticationInformation['db_user']['userident_column']] = GeneralUtility::makeInstance(Random::class)->generateRandomHexString(42);
                        $this->logger->debug(
                            sprintf(
                                'User \'%s\' logged in with OpenID \'%s\'',
                                $userRecord[$this->parentObject->formfield_uname],
                                $openIDIdentifier
                            )
                        );
                    } else {
                        $this->logger->debug(sprintf('Failed to login user using OpenID \'%s\'', $openIDIdentifier));
                    }
                }
            }
        } elseif (!empty($this->loginData['uident_openid'])) {
            $this->sendOpenIDRequest($this->loginData['uident_openid']);
        }
        return $userRecord;
    }

    /**
     * Authenticates user using OpenID.
     *
     * @param array $userRecord User record
     * @return int Code that shows if user is really authenticated.
     */
    public function authUser(array $userRecord): int
    {
        $result = 100;
        // 100 means "we do not know, continue"
        if ($userRecord['tx_openid_openid'] !== '') {
            // Check if user is identified by the OpenID
            if ($this->openIDResponse instanceof \Auth_OpenID_ConsumerResponse) {
                // If we have a response, it means OpenID server tried to authenticate
                // the user. Now we just look what is the status and provide
                // corresponding response to the caller
                if ($this->openIDResponse->status === Auth_OpenID_SUCCESS) {
                    // Success (code 200)
                    $result = 200;
                } else {
                    // It was OpenID but it failed, do not continue with outher services
                    $result = -1;
                    $this->logger->warning(sprintf('OpenID authentication failed with code \'%s\'.', $this->openIDResponse->status));
                }
            }
        }
        return $result;
    }

    /**
     * Includes necessary files for the PHP OpenID library
     */
    protected function includePHPOpenIDLibrary(): void
    {
        if (self::$openIDLibrariesIncluded) {
            return;
        }
        // Prevent further calls
        self::$openIDLibrariesIncluded = true;
        // PHP OpenID libraries requires adjustments of path settings
        $oldIncludePath = get_include_path();
        $phpOpenIDLibPath = ExtensionManagementUtility::extPath('openid') . 'lib/php-openid';
        @set_include_path(($phpOpenIDLibPath . PATH_SEPARATOR . $phpOpenIDLibPath . PATH_SEPARATOR . 'Auth' . PATH_SEPARATOR . $oldIncludePath));
        // Make sure that random generator is properly set up. Constant could be
        // defined by the previous inclusion of the file
        if (!defined('Auth_OpenID_RAND_SOURCE')) {
            if (Environment::isWindows()) {
                // No random generator on Windows!
                define('Auth_OpenID_RAND_SOURCE', null);
            } elseif (!is_readable('/dev/urandom')) {
                if (is_readable('/dev/random')) {
                    define('Auth_OpenID_RAND_SOURCE', '/dev/random');
                } else {
                    define('Auth_OpenID_RAND_SOURCE', null);
                }
            }
        }
        // Include files
        require_once $phpOpenIDLibPath . '/Auth/OpenID/Consumer.php';
        // Restore path
        @set_include_path($oldIncludePath);
        if (!isset($_SESSION)) {
            // Yadis requires session but session is not initialized when
            // processing Backend authentication
            @session_start();
            $this->logger->debug('Session is initialized');
        }
    }

    /**
     * Gets user record for the user with the OpenID provided by the user
     *
     * @param string $openIDIdentifier OpenID identifier to search for
     * @return ?array Database fields from the table that corresponds to the current login mode (FE/BE)
     */
    protected function getUserRecord(string $openIDIdentifier): ?array
    {
        $record = null;
        try {
            $openIDIdentifier = $this->normalizeOpenID($openIDIdentifier);
            // $openIDIdentifier always has a trailing slash
            // but tx_openid_openid field possibly not so check for both alternatives in database
            /** @var QueryBuilder $queryBuilder */
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($this->authenticationInformation['db_user']['table']);
            $queryBuilder->getRestrictions()->removeAll();
            $record = $queryBuilder
                ->select('*')
                ->from($this->authenticationInformation['db_user']['table'])
                ->where(
                    $queryBuilder->expr()->in(
                        'tx_openid_openid',
                        $queryBuilder->createNamedParameter(
                            [$openIDIdentifier, rtrim($openIDIdentifier, '/')],
                            ArrayParameterType::STRING
                        )
                    ),
                    $this->authenticationInformation['db_user']['check_pid_clause'] ?? '',
                    $this->authenticationInformation['db_user']['enable_clause']
                )
                ->executeQuery()
                ->fetchAssociative();
            if ($record) {
                // Make sure to work only with normalized OpenID during the whole process
                $record['tx_openid_openid'] = $this->normalizeOpenID($record['tx_openid_openid']);
            }
        } catch (\Exception $exception) {
            // This should never happen and generally means hack attempt.
            // We just log it and do not return any records.
            $this->logger->error(
                sprintf(
                    '[%d] "%s" %s',
                    $exception->getCode(),
                    $exception->getMessage(),
                    $exception->getTraceAsString()
                )
            );
        }

        // Hook to modify the user record, e.g. to register a new user
        if (isset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['openid']['getUserRecord']) && is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['openid']['getUserRecord'])) {
            $_params = [
                'record' => &$record,
                'response' => $this->openIDResponse,
                'authInfo' => $this->authenticationInformation
            ];
            foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['openid']['getUserRecord'] as $funcName) {
                GeneralUtility::callUserFunction($funcName, $_params, $this);
            }
        }

        return is_array($record) ? $record : null;
    }

    /**
     * Creates OpenID Consumer object with a TYPO3-specific store. This function
     * is almost identical to the example from the PHP OpenID library.
     *
     * @return \Auth_OpenID_Consumer Consumer instance
     */
    protected function getOpenIDConsumer(): \Auth_OpenID_Consumer
    {
        /* @var $openIDStore OpenidStore */
        $openIDStore = GeneralUtility::makeInstance(OpenidStore::class);
        $openIDStore->cleanup();
        return new \Auth_OpenID_Consumer($openIDStore);
    }

    /**
     * Sends request to the OpenID server to authenticate the user with the
     * given ID. This function is almost identical to the example from the PHP
     * OpenID library. Due to the OpenID specification we cannot do a slient login.
     * Sometimes we have to redirect to the OpenID provider web site so that
     * user can enter his password there. In this case we will redirect and provide
     * a return adress to the special script inside this directory, which will
     * handle the result appropriately.
     *
     * This function does not return on success. If it returns, it means something
     * went totally wrong with OpenID.
     *
     * @param string $openIDIdentifier The OpenID identifier for discovery and auth request
     */
    protected function sendOpenIDRequest(string $openIDIdentifier): void
    {
        $this->includePHPOpenIDLibrary();
        // Initialize OpenID client system, get the consumer
        $openIDConsumer = $this->getOpenIDConsumer();
        // Begin the OpenID authentication process
        $authenticationRequest = $openIDConsumer->begin($openIDIdentifier);
        if (!$authenticationRequest) {
            // Not a valid OpenID. Since it can be some other ID, we just return
            // and let other service handle it.
            $this->logger->warning(sprintf('Could not create authentication request for OpenID identifier \'%s\'', $openIDIdentifier));
            return;
        }

        // Hook to modify the auth request object, e.g. to request additional attributes
        if (isset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['openid']['authRequest']) && is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['openid']['authRequest'])) {
            $_params = [
                'authRequest' => $authenticationRequest,
                'authInfo' => $this->authenticationInformation
            ];
            foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['openid']['authRequest'] as $funcName) {
                GeneralUtility::callUserFunction($funcName, $_params, $this);
            }
        }

        // Redirect the user to the OpenID server for authentication.
        // Store the token for this authentication so we can verify the
        // response.
        // For OpenID version 1, we *should* send a redirect. For OpenID version 2,
        // we should use a Javascript form to send a POST request to the server.
        $returnURL = $this->getReturnURL($openIDIdentifier, true);
        $trustedRoot = GeneralUtility::getIndpEnv('TYPO3_SITE_URL');
        if ($authenticationRequest->shouldSendRedirect()) {
            $redirectURL = $authenticationRequest->redirectURL($trustedRoot, $returnURL);
            // If the redirect URL can't be built, return. We can only return.
            if (\Auth_OpenID::isFailure($redirectURL)) {
                $this->logger->warning(sprintf('Authentication request could not create redirect URL for OpenID identifier \'%s\'', $openIDIdentifier));
                return;
            }
            // Send redirect. We use 303 code because it allows to redirect POST
            // requests without resending the form. This is exactly what we need here.
            // See http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html#sec10.3.4
            @ob_end_clean();
            header(HttpUtility::HTTP_STATUS_303);
            header('Location: ' . $redirectURL);
        } else {
            $formHtml = $authenticationRequest->htmlMarkup($trustedRoot, $returnURL/*, false, ['id' => 'openid_message']*/);
            // Display an error if the form markup couldn't be generated;
            // otherwise, render the HTML.
            if (\Auth_OpenID::isFailure($formHtml)) {
                // Form markup cannot be generated
                $this->logger->warning(sprintf('Could not create form markup for OpenID identifier \'%s\'', $openIDIdentifier));
                return;
            }
            @ob_end_clean();
            echo $formHtml;
        }
        // If we reached this point, we must not return!
        die;
    }

    /**
     * Creates return URL for the OpenID server. When a user is authenticated by
     * the OpenID server, the user will be sent to this URL to complete
     * authentication process with the current site. We send it to our script.
     *
     * @param string $claimedIdentifier The OpenID identifier for discovery and auth request
     * @param bool $storeRequestToken
     * @return string Return URL
     */
    protected function getReturnURL(string $claimedIdentifier, bool $storeRequestToken = false): string
    {
        $returnURL = GeneralUtility::getIndpEnv('TYPO3_SITE_URL') .
            TYPO3_mainDir .
            'index.php?login_status=login'
        ;

        if (($_GET['tx_openid_mode'] ?? '') === 'finish') {
            $requestURL = $_GET['tx_openid_location'];
        } else {
            $requestURL = GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL');
        }

        $returnURL .= '&tx_openid_claimed=' . rawurlencode($claimedIdentifier) .
            '&tx_openid_location=' . rawurlencode($requestURL) .
            '&tx_openid_location_signature=' . $this->getSignature($requestURL) .
            '&tx_openid_mode=finish' .
            '&tx_openid_signature=' . $this->getSignature($claimedIdentifier)
        ;

        if ($storeRequestToken) {
            $returnURL .= '&tx_openid_token=' . $this->storeRequestToken();
        }

        return GeneralUtility::locationHeaderUrl($returnURL);
    }

    /**
     * Signs a GET parameter.
     *
     * @param string $parameter
     * @return string
     */
    protected function getSignature(string $parameter): string
    {
        return GeneralUtility::hmac($parameter, 'openid');
    }

    /**
     * Implement normalization according to OpenID 2.0 specification
     * See http://openid.net/specs/openid-authentication-2_0.html#normalization
     *
     * @param string $openIDIdentifier OpenID identifier to normalize
     * @return string Normalized OpenID identifier
     * @throws Exception
     */
    protected function normalizeOpenID(string $openIDIdentifier): string
    {
        if (empty($openIDIdentifier)) {
            throw new Exception('Empty OpenID Identifier given.', 1381922460);
        }
        // Strip everything with and behind the fragment delimiter character "#"
        if (str_contains($openIDIdentifier, '#')) {
            $openIDIdentifier = preg_replace('/#.*$/', '', $openIDIdentifier);
        }
        // A URI with a missing scheme is normalized to a http URI
        if (!preg_match('#^https?://#', $openIDIdentifier)) {
            /** @var QueryBuilder $queryBuilder */
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($this->authenticationInformation['db_user']['table']);
            $queryBuilder->getRestrictions()->removeAll();
            $row = $queryBuilder
                ->select('tx_openid_openid')
                ->from($this->authenticationInformation['db_user']['table'])
                ->where(
                    $queryBuilder->expr()->in('tx_openid_openid', $queryBuilder->createNamedParameter(
                        [
                            'http://' . $openIDIdentifier,
                            'http://' . $openIDIdentifier . '/',
                            'https://' . $openIDIdentifier,
                            'https://' . $openIDIdentifier . '/'
                        ],
                        ArrayParameterType::STRING
                    ))
                )
                ->executeQuery()
                ->fetchAssociative();

            if (is_array($row)) {
                $openIDIdentifier = $row['tx_openid_openid'];
            } else {
                // This only happens when the OpenID provider will select the final OpenID identity
                // In this case we require a valid URL as we cannot guess the scheme
                // So we throw an Exception and do not start the OpenID handshake at all
                throw new Exception('Trying to authenticate with OpenID but identifier is neither found in a user record nor it is a valid URL.', 1381922465);
            }
        }
        // An empty path component is normalized to a slash
        // (e.g. "http://domain.org" -> "http://domain.org/")
        if (preg_match('#^https?://[^/]+$#', $openIDIdentifier)) {
            $openIDIdentifier .= '/';
        }
        return $openIDIdentifier;
    }

    /**
     * Obtains a real identifier for the user
     *
     * @return string
     */
    protected function getFinalOpenIDIdentifier(): string
    {
        $result = $this->getSignedParameter('openid_claimed_id');
        if (!$result) {
            $result = $this->getSignedParameter('openid_identity');
        }
        if (!$result) {
            $result = $this->getSignedClaimedOpenIDIdentifier();
        }
        return $result;
    }

    /**
     * Gets the signed OpenID that was sent back to this service.
     *
     * @return string The signed OpenID, if signature did not match this is empty
     */
    protected function getSignedClaimedOpenIDIdentifier(): string
    {
        $result = (string)$_GET['tx_openid_claimed'];
        $signature = $this->getSignature($result);
        if ($signature !== $_GET['tx_openid_signature']) {
            $result = '';
        }
        return $result;
    }

    /**
     * Obtains a value of the parameter if it is signed. If not signed, then
     * empty string is returned.
     *
     * @param string $parameterName Must start with 'openid_'
     * @return string
     */
    protected function getSignedParameter(string $parameterName): string
    {
        $signedParametersList = (string)$_GET['openid_signed'];
        if (GeneralUtility::inList($signedParametersList, substr($parameterName, 7))) {
            $result = $_GET[$parameterName] ?? '';
        } else {
            $result = '';
        }
        return $result;
    }

    /**
     * Stores request token for future restoratio when OpenID server returns
     * the response. This is secure because token id never visible to the
     * end user: it only exist in communication between TYPO3 and OpenID server.
     * See \FoT3\Openid\Event\RestoreRequestToken for more information.
     *
     * @return string
     * @see \FoT3\Openid\Event\RestoreRequestToken
     */
    protected function storeRequestToken(): string
    {
        $context = GeneralUtility::makeInstance(Context::class);
        $securityAspect = SecurityAspect::provideIn($context);
        $requestToken = $securityAspect->getReceivedRequestToken();

        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('tx_openid_token');

        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder->delete('tx_openid_token')
            ->where(
                $queryBuilder->expr()->lt('crdate', $queryBuilder->createNamedParameter(time() - 600))
            )
            ->executeStatement()
        ;

        $tokenId = GeneralUtility::makeInstance(Random::class)->generateRandomHexString(24);
        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder->insert('tx_openid_token')
            ->values([
                'crdate' => time(),
                'token' => serialize($requestToken),
                'token_id' => $tokenId,
            ])
            ->executeStatement()
        ;
        return $tokenId;
    }
}
