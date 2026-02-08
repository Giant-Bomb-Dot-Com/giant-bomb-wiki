<?php

namespace MediaWiki\Extension\GbSessionProvider;

use MediaWiki\Request\WebRequest;
use MediaWiki\Session\ImmutableSessionProviderWithCookie;
use MediaWiki\Session\UserInfo;
use MediaWiki\Session\SessionInfo;
use MediaWiki\Session\SessionManager;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserFactory;
use MediaWiki\User\User;
use MediaWiki\User\UserGroupManager;
use Firebase\JWT\JWT;
use Firebase\JWT\CachedKeySet;
use UnexpectedValueException;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Phpfastcache\CacheManager;
use Phpfastcache\Config\ConfigurationOption;

class GbSessionProvider extends ImmutableSessionProviderWithCookie
{
    public const string PREMIUM_GROUP_NAME = "subscriber";
    public const string PREMIUM_RIGHT = "gb-premium";

    protected $logger;
    protected string $prefix = "";
    protected $params = [];
    protected string $gbnCookieName = "";
    protected string $jwksUri = "";
    protected string $expectedIssuer = "";
    protected string $expectedAudience = "";
    protected array $groupMapping = [];

    public function __construct(array $params = [])
    {
        $params["sessionCookieName"] = "mwSessionCookieName";
        $params["sessionCookieOptions"] = [
            "path" => "/",
            "prefix" => $this->prefix,
        ]; // 1.43.5/includes/Request/WebResponse.php#L155
        parent::__construct($params);
        $this->params = $params;

        $config = MediaWikiServices::getInstance()->getMainConfig();
        $this->gbnCookieName =
            $config->get("GbSessionProviderGbnCookieName") ?: "gb_wiki";

        $this->jwksUri = $config->get("GbSessionProviderJWKSUri");
        $this->expectedIssuer = $config->get("GbSessionProviderExpectedIssuer") ?: "https://giantbomb.com";
        $this->expectedAudience = $config->get("GbSessionProviderExpectedAudience") ?: "giantbomb-wiki";
        $this->groupMapping = (array) ($config->get("GbSessionProviderGroupMapping") ?: []);
    }

    protected function postInitSetup()
    {
        // declare this extension's log channel
        $this->logger = LoggerFactory::getInstance("GbSessionProvider");
    }

    // Inspired by https://www.mediawiki.org/wiki/Manual:SessionManager_and_AuthManager/SessionProvider_examples
    // 1. Look for a gb_wiki cookie
    // 2. Verify the cookie content
    //    a) If cookie doesn't exists, the user is not authenticated
    //    b) If cookie fails verification, the user is not authenticated
    //    c) If cookie passes verification, the user is externally authenticated
    // 3. When user is externally authenticated, they automatically get authenticated for Mediawiki
    //    a) Get user info from the cookie; find or create the user into Mediawiki
    //    b) Return a Mediawiki session for this user
    // 4. When user is not authenticated, they automatically are not authenticated with the Mediawiki
    //
    public function provideSessionInfo(WebRequest $request)
    {
        $this->logger->debug("Gb provide session info");

        // 1.
        $cookieData = $this->getGbnCookie($request);

        // 2. a)
        if ($cookieData === null) {
            $this->logger->debug("Expecting a GBN cookie, but none");
            return null;
        }

        // 2. b)
        try {
            $data = $this->decodeVerifyGbnJwt($cookieData);
        } catch (\Throwable $e) {
            $this->logger->error(
                "JWT processing failed: " . $e::class . ": " . $e->getMessage(),
            );
            return null;
        }

        // 2. c)
        if ($data === null) {
            $this->logger->debug(
                "External system no longer authenticated; remove any existing session",
            );
            $this->unpersistSession($request);
            return null;
        }
        $this->logger->debug("current user is considered logged in externally");

        // 3. a)
        try {
            $user = $this->findOrCreateUserFromGbn($data);
        } catch (\Throwable $e) {
            $this->logger->error(
                "User resolution failed: " . $e::class . ": " . $e->getMessage(),
            );
            return null;
        }

        if ($user === null) {
            $this->logger->warning("findOrCreateUserFromGbn returned null");
            return null;
        }
        $this->logger->debug("User resolved: " . $user->getName());
        $userInfo = UserInfo::newFromUser($user, true);

        // 3. b)
        $userSession = $this->createUserSession($request, $userInfo);
        return $userSession;
    }

    public function getGbnCookie($request)
    {
        $data = $request->getCookie($this->gbnCookieName, $this->prefix);
        $this->logger->debug(
            "getGbnCookie: " . ($data !== null ? "found" : "not found"),
        );
        return $data;
    }

    // If verification success, return decoded data
    // If verification fails, return null
    public function decodeVerifyGbnJwt($data)
    {
        $this->logger->debug("decodeVerifyGbnJwt: verifying JWT");

        // based on Firebase example
        $httpClient = new Client();
        $httpFactory = new HttpFactory();
        // Create a cache item pool (can be any PSR-6 compatible cache item pool)
        $cacheItemPool = CacheManager::getInstance('Files', new ConfigurationOption([
            'path' => sys_get_temp_dir() . '/gbsession-jwks-cache',
        ]));
        $keySet = new CachedKeySet(
            $this->jwksUri,
            $httpClient,
            $httpFactory,
            $cacheItemPool,
            null, // $expiresAfter int seconds to set the JWKS to expire
            true, // $rateLimit    true to enable rate limit of 10 RPS on lookup of invalid keys
        );
        $decodedJWTObj = $this->verifyJwt($data, $keySet);

        if ($decodedJWTObj === null) {
            return null;
        }

        // Validate issuer
        if (!isset($decodedJWTObj->iss) || $decodedJWTObj->iss !== $this->expectedIssuer) {
            $this->logger->warning(
                "JWT issuer mismatch: expected " . $this->expectedIssuer .
                ", got " . ($decodedJWTObj->iss ?? "(none)"),
            );
            return null;
        }

        // Validate audience
        if (!isset($decodedJWTObj->aud) || $decodedJWTObj->aud !== $this->expectedAudience) {
            $this->logger->warning(
                "JWT audience mismatch: expected " . $this->expectedAudience .
                ", got " . ($decodedJWTObj->aud ?? "(none)"),
            );
            return null;
        }

        $this->logger->debug(
            "JWT verification successful for sub=" . ($decodedJWTObj->sub ?? "unknown"),
        );
        return $decodedJWTObj;
    }

    // we technically know $data is not null and it's been verified
    public function findOrCreateUserFromGbn($data)
    {
        $this->logger->debug(
            "findOrCreateUserFromGbn: find user by name or create one",
        );
        $username = $data->preferred_username ?? $data->name;
        $email = $data->email;

        $userFactory = MediaWikiServices::getInstance()->getUserFactory();
        $user = $userFactory->newFromName($username);

        if ($user === null) {
            throw new UnexpectedValueException(
                "GbSessionProvider: Unable to create anon user with this username: " .
                    $username,
            );
        }

        if (!$user->isRegistered()) {
            $this->logger->debug(
                "findOrCreateUserFromGbn: new user, store to db",
            );
            $user->setEmail($email);
            $user->confirmEmail();
            $user->addToDatabase();
        } else {
            $this->logger->debug("findOrCreateUserFromGbn: load existing user");
            $user->load();
        }

        if ($user->isRegistered()) {
            $this->syncUserGroups($user, $data);
        } else {
            $this->logger->error(
                "findOrCreateUserFromGbn: user is not registered after creation attempt",
            );
        }
        return $user;
    }

    protected function syncUserGroups($user, $data)
    {
        $userGroupManager = MediaWikiServices::getInstance()->getUserGroupManager();
        $currentGroups = $userGroupManager->getUserGroups($user);

        $premiumClaim = (bool) ($data->premium ?? false);
        $isSubscriber = in_array(self::PREMIUM_GROUP_NAME, $currentGroups);

        if ($premiumClaim && !$isSubscriber) {
            $this->logger->debug("Adding user to subscriber group");
            $userGroupManager->addUserToGroup(
                $user,
                self::PREMIUM_GROUP_NAME,
                null,
                true,
            );
        } elseif (!$premiumClaim && $isSubscriber) {
            $this->logger->debug("Removing user from subscriber group");
            $userGroupManager->removeUserFromGroup(
                $user,
                self::PREMIUM_GROUP_NAME,
            );
        }

        if (!empty($this->groupMapping)) {
            $jwtGroups = (array) ($data->groups ?? []);
            $jwtRoles = (array) ($data->roles ?? []);
            $jwtClaims = array_unique(array_merge($jwtGroups, $jwtRoles));

            foreach ($this->groupMapping as $jwtName => $mwGroup) {
                $inJwt = in_array($jwtName, $jwtClaims);
                $inMw = in_array($mwGroup, $currentGroups);

                if ($inJwt && !$inMw) {
                    $this->logger->debug("Adding user to {$mwGroup} group (JWT claim: {$jwtName})");
                    $userGroupManager->addUserToGroup($user, $mwGroup, null, true);
                } elseif (!$inJwt && $inMw) {
                    $this->logger->debug("Removing user from {$mwGroup} group (JWT claim: {$jwtName})");
                    $userGroupManager->removeUserFromGroup($user, $mwGroup);
                }
            }
        }
    }

    public function createUserSession($request, $userInfo)
    {
        $id = null;
        $persisted = null;
        $forceUse = null;
        if ($this->sessionCookieName === null) {
            $this->logger->debug("-> no existing mediawiki session");
            $id = $this->hashToSessionId($userInfo->getName());
            $persisted = false;
            $forceUse = true;
        } else {
            $this->logger->debug("-> there exists mediawiki session");
            $id = $this->getSessionIdFromCookie($request);
            $persisted = $id !== null;
            $forceUse = false;
        }
        $this->logger->debug(
            "creating new session for " .
                $userInfo->getId() .
                " " .
                $userInfo->getName(),
        );
        return new SessionInfo(SessionInfo::MAX_PRIORITY, [
            "provider" => $this,
            "id" => $id,
            "userInfo" => $userInfo,
            "persisted" => $persisted,
            "forceUse" => $forceUse,
        ]);
    }

    // return decoded object if good
    // else return null
    protected function verifyJwt($token, $keySet)
    {
        $result = null;
        try {
            $this->logger->debug("verifyJwt: decoding token");
            $result = JWT::decode($token, $keySet);
        } catch (\LogicException $e) {
            $this->logger->warning(
                "JWT::decode Logic exception: " . $e->getMessage(),
            );
            return null;
        } catch (UnexpectedValueException $e) {
            $this->logger->warning(
                "JWT::decode Unexpected value: " . $e->getMessage(),
            );
            return null;
        } catch (\Throwable $e) {
            $this->logger->warning(
                "JWT::decode error: " . $e::class . ": " . $e->getMessage(),
            );
            return null;
        }
        return $result;
    }
}
