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
use Firebase\JWT\Key;
use Firebase\JWT\CachedKeySet;
use UnexpectedValueException;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Phpfastcache\CacheManager;

class GbSessionProvider extends ImmutableSessionProviderWithCookie
{
    // const string PREMIUM_GROUP_NAME = 'subscriber';

    protected $logger;
    protected string $prefix = "";
    protected bool $testFlag = false;
    protected $params = [];
    protected string $gbnCookieName = "";
    protected string $jwksUri = "";

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
        $this->testFlag = $this->setTestFlag(
            $config->get("GbSessionProviderTestModeEnabled"),
        );
        $this->gbnCookieName =
            $config->get("GbSessionProviderGbnCookieName") ?: "gb_wiki";

        $this->jwksUri = $config->get("GbSessionProviderJWKSUri");
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
    //    a) Get user info from the cookie and upsert the user into Mediawiki
    //    b) Return a Mediawiki session for this user
    // 4. When user is not authenticated, they automatically are not authenticated with the Mediawiki
    //
    // For testing, in in LocalSettings.php set
    // * the boolean config $wgGbSessionProviderTestModeEnabled
    // * set a JWT string for $wgGbSessionProviderTestJWT
    public function provideSessionInfo(WebRequest $request)
    {
        $this->logger->debug("Gb provide session info");

        if ($this->testFlag) {
            $this->logger->debug("Inserting a dummy GBN cookie");
            $this->prepareDummyCookies($request);
        }

        // 1.
        $cookieData = $this->getGbnCookie($request);

        // 2. a)
        if ($cookieData === null) {
            $this->logger->debug("Expecting a GBN cookie, but none");
            return null;
        }

        // 2. b)
        $data = $this->decodeVerifyGbnJwt($cookieData);

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
        $user = $this->createUserFromGbn($data);
        $this->logger->debug("new user " . print_r($user, true));

        if ($user === null) {
            $this->logger->debug("no new user yet?");
        }
        $this->logger->debug("create UserInfo from the new usr");
        $userInfo = UserInfo::newFromUser($user, true);
        $this->logger->debug("user_info " . print_r($userInfo, true));

        // 3. b)
        $userSession = $this->createUserSession($request, $userInfo);
        return $userSession;
    }

    public function prepareDummyCookies(WebRequest $request)
    {
        $this->logger->debug("prepare dummy cookies");
        $this->logger->debug(
            "GbSessionProviderTest JWT " .
                print_r(
                    $this->getConfig()->get("GbSessionProviderTestJWT"),
                    true,
                ),
        );
        $testJWT = $this->getConfig()->get("GbSessionProviderTestJWT");
        $wikiCookie = $request->getCookie("gb_wiki", $this->prefix);
        if ($wikiCookie === null) {
            $this->logger->info("\tno existing gb_wiki cookie");
            $response = $request->response();
            $response->setCookie("gb_wiki", $testJWT, time() + 3600 * 24 * 7, [
                "prefix" => $this->prefix,
                "path" => "/",
            ]);
        }
    }

    public function getGbnCookie($request)
    {
        $this->logger->debug(
            ">>> get GBN external authentication cookie named " .
                $this->gbnCookieName,
        );
        $data = $request->getCookie($this->gbnCookieName, $this->prefix);
        $this->logger->debug("gb_wiki data => " . print_r($data, true));
        return $data;
    }

    // If verification success, return decoded data
    // if verifcation fials, return null
    public function decodeVerifyGbnJwt($data)
    {
        $this->logger->info(">>> JWT decode with " . print_r($data, true));
        // should it check the data?

        // based on Firebase example
        $httpClient = new Client();
        $httpFactory = new HttpFactory();
        // Create a cache item pool (can be any PSR-6 compatible cache item pool)
        $cacheItemPool = CacheManager::getInstance("files");
        $keySet = new CachedKeySet(
            $this->jwksUri,
            $httpClient,
            $httpFactory,
            $cacheItemPool,
            null, // $expiresAfter int seconds to set the JWKS to expire
            true, // $rateLimit    true to enable rate limit of 10 RPS on lookup of invalid keys
        );
        $decodedJWTObj = null;
        try {
            $decodedJWTObj = JWT::decode($data, $keySet);
        } catch (LogicException $e) {
            $this->logger->info("Logic exception error " . $e->getMessage());
            return null;
        } catch (UnexpectedValueException $e) {
            $this->logger->info("Unexpected value " . $e->getMessage());
            return null;
        } catch (Exception $e) {
            $this->logger->info("Catch all exception " . $e->getMessage());
            return null;
        }

        $this->logger->debug(
            "Verification successful; " . print_r($decodedJWTObj, true),
        );
        return $decodedJWTObj;
    }

    // uses UserFactory and I think it does a find or create.
    // we technically know $data is not null and it's been verified
    public function createUserFromGbn($data)
    {
        $this->logger->debug(
            ">>> Create the user from GBN data and assign or update group",
        );

        $username = $data->preferred_username;
        $email = $data->email;
        $emailVerified = (bool) $data->email_verified;
        $premiumClaim = (bool) $data->premium;

        $userFactory = MediaWikiServices::getInstance()->getUserFactory();
        $user = $userFactory->newFromName($username);

        if ($user === null) {
            throw new UnexpectedValueException(
                "unable to create a user with this user name, sub " . $subject,
            );
        }

        $user->setEmail($email);
        $user->confirmEmail();

        $premiumGroupName = "subscriber"; // self::PREMIUM_GROUP_NAME;

        if ($user->isRegistered()) {
            $userGroupManager = MediaWikiServices::getInstance()->getUserGroupManager();
            if ($premiumClaim) {
                $this->logger->debug("premium is true so adding to group");
                $groupAdded = $userGroupManager->addUserToGroup(
                    $user,
                    $premiumGroupName,
                    null,
                    true,
                ); // last flag, is upsert? should it?
            } else {
                $this->logger->debug("premium is false so removing to group");
                $groups = $userGroupManager->getUserImplicitGroups($user);
                if (in_array($premiumGroupName, $groups)) {
                    $this->logger->debug("\t-> member of, so kicking out");
                    $groupKicked = $userGroupManager->removeUserFromGroup(
                        $user,
                        $premiumGroupName,
                    );
                } else {
                    $this->logger->debug("\tnot a member");
                }
            }
        } else {
            $this->logger->debug("user is not registered yet");
        }
        return $user;
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

    // Expect config to be a bool; default to false when not bool
    public function setTestFlag($value): bool
    {
        if (is_string($value)) {
            return false;
        }
        return !!((bool) $value);
    }
}
