<?php

namespace MediaWiki\Tests\Session;

use MediaWiki\Extension\GbSessionProvider\GbSessionProvider;
use MediaWikiIntegrationTestCase;
use MediaWiki\Tests\Session\SessionProviderTestTrait;
use MediaWiki\MainConfigNames;
use MediaWiki\Context\RequestContext;
use MediaWiki\Request\FauxRequest;
use TestLogger;
use Psr\Log\NullLogger;
use MediaWiki\Config\HashConfig;
use MediaWiki\Session\SessionBackend; // when testing persisting of session to cookie
use MediaWiki\Session\SessionId;
use MediaWiki\Session\SessionManager;
use MediaWiki\Session\SessionInfo;
use MediaWiki\Session\UserInfo;
use MediaWiki\User\User;
use Wikimedia\TestingAccessWrapper;
use Firebase\JWT\JWT;

/**
 * @group Session
 * @group Database
 * @covers \MediaWiki\Extension\GbSessionProvider\GbSessionProvider
 **/
class GbSessionProviderTest extends MediaWikiIntegrationTestCase
{
    use SessionProviderTestTrait;

    // 1. test no cookie, return null
    // 2. test verified cookie, return user session
    // 3. test invalid cookie, return null

    protected function setUp(): void
    {
        parent::setUp();

        $this->overrideConfigValues([
            MainConfigNames::Script => "/index.php",
            MainConfigNames::LanguageCode => "en",
        ]);
    }

    public function testProvideSessionInfoWithoutGbnCookieShouldRemainUnauthenticated()
    {
        $provider = $this->getMockBuilder(GbSessionProvider::class)
            ->onlyMethods(["getGbnCookie"])
            ->getMock();
        $provider
            ->method("getGbnCookie")
            ->with($this->anything())
            ->willReturn(null);

        $config = new HashConfig();
        $config->set("GbSessionProviderGbnCookieName", "gb_wiki");
        $this->initProvider(
            $provider,
            $logger ?? new TestLogger(),
            $config,
            new SessionManager(),
        );

        $request = new FauxRequest();
        $context = new RequestContext();
        $context->setRequest($request);

        $info = $provider->provideSessionInfo($request);

        $this->assertNull($info);
    }

    public function testProvideSessionInfoWithValidClaimShouldReturnAuthenticatedSessionInfo()
    {
        $claim = [
            "preferred_username" => "GBN-test-name",
            "name" => "GBN-test-name",
            "email" => "test-email@example.org",
            "email_verified" => 1,
            "premium" => 1,
            "iat" => 0,
            "exp" => 0,
            "iss" => "iss",
            "aud" => "giantbomb-wiki",
            "sub" => "sub",
        ];
        $provider = $this->getMockBuilder(GbSessionProvider::class)
            ->onlyMethods(["getGbnCookie", "decodeVerifyGbnJwt"])
            ->getMock();
        $provider
            ->method("getGbnCookie")
            ->with($this->anything())
            ->willReturn("stubbed-gb-wiki-cookie");
        $provider
            ->method("decodeVerifyGbnJwt")
            ->with($this->anything())
            ->willReturn((object) $claim);

        $config = new HashConfig();
        $config->set("GbSessionProviderGbnCookieName", "gb_wiki");
        $this->initProvider(
            $provider,
            $logger ?? new TestLogger(),
            $config,
            new SessionManager(),
        );

        $request = new FauxRequest();
        $context = new RequestContext();
        $context->setRequest($request);

        $info = $provider->provideSessionInfo($request);
        $this->assertInstanceOf(SessionInfo::class, $info);
        $this->assertSame(SessionInfo::MAX_PRIORITY, $info->getPriority());
        $userInfo = $info->getUserInfo();
        $user = $userInfo->getUser();

        $this->assertGreaterThan(0, $user->getId(), "Valid user id");
        $this->assertFalse($user->isAnon(), "User is not anonymous");
        $this->assertSame($claim["preferred_username"], $userInfo->getName());
        $this->assertSame($claim["preferred_username"], $user->getName());
        $this->assertSame($claim["email"], $user->getEmail());
        $this->assertTrue(
            $user->isEmailConfirmed(),
            "Confirmed user is neither anonymous and email authentication has a timestamp",
        );
    }

    public function testProvideSessionInfoWithInvalidClaimShouldRemainUnauthenticated()
    {
        $provider = $this->getMockBuilder(GbSessionProvider::class)
            ->onlyMethods(["getGbnCookie", "decodeVerifyGbnJwt"])
            ->getMock();
        $provider
            ->method("getGbnCookie")
            ->with($this->anything())
            ->willReturn("stubbed-gb-wiki-cookie");
        $provider
            ->method("decodeVerifyGbnJwt")
            ->with($this->anything())
            ->willReturn(null); // basically verification failed

        $config = new HashConfig();
        $config->set("GbSessionProviderGbnCookieName", "gb_wiki");
        $this->initProvider(
            $provider,
            $logger ?? new TestLogger(),
            $config,
            new SessionManager(),
        );

        $request = new FauxRequest();
        $context = new RequestContext();
        $context->setRequest($request);

        $info = $provider->provideSessionInfo($request);
        $this->assertNull($info);
    }

    /**
     * @dataProvider provideExistingUserGroup
     **/
    public function testProvideSessionInfoWithExistingUser(
        string $groupName,
        bool $premiumClaim,
        bool $expectedRight,
    ) {
        $existingUser = $this->getMutableTestUser([$groupName])->getUser();

        $claim = [
            "preferred_username" => $existingUser->getName(),
            "name" => $existingUser->getName(),
            "email" => $existingUser->getEmail(),
            "email_verified" => 1,
            "premium" => $premiumClaim,
            "iat" => 0,
            "exp" => 0,
            "iss" => "iss",
            "aud" => "giantbomb-wiki",
            "sub" => $existingUser->getId(),
        ];

        $provider = $this->getMockBuilder(GbSessionProvider::class)
            ->onlyMethods(["getGbnCookie", "decodeVerifyGbnJwt"])
            ->getMock();
        $provider
            ->method("getGbnCookie")
            ->with($this->anything())
            ->willReturn("stubbed-gb-wiki-cookie");
        $provider
            ->method("decodeVerifyGbnJwt")
            ->with($this->anything())
            ->willReturn((object) $claim);

        $config = new HashConfig();
        $config->set("GbSessionProviderGbnCookieName", "gb_wiki");
        $this->initProvider(
            $provider,
            $logger ?? new TestLogger(),
            $config,
            new SessionManager(),
        );

        $request = new FauxRequest();
        $context = new RequestContext();
        $context->setRequest($request);

        $info = $provider->provideSessionInfo($request);
        $this->assertInstanceOf(SessionInfo::class, $info);
        $this->assertSame(SessionInfo::MAX_PRIORITY, $info->getPriority());
        $userInfo = $info->getUserInfo();
        $sessionUser = $userInfo->getUser();

        $this->assertSame(
            $existingUser->getId(),
            $sessionUser->getId(),
            "The existing user making the request and the session user should have the same id",
        );
        $this->assertSame($existingUser->getName(), $sessionUser->getName());
        $this->assertSame($existingUser->getEmail(), $sessionUser->getEmail());
        $this->assertSame(
            $expectedRight,
            $sessionUser->isAllowed(GbSessionProvider::PREMIUM_RIGHT),
        );
    }

    public static function provideExistingUserGroup()
    {
        return [
            [GbSessionProvider::PREMIUM_GROUP_NAME, true, true],
            [GbSessionProvider::PREMIUM_GROUP_NAME, false, false], // downgrade
            ["user", false, false],
            ["user", true, true], // upgrade
        ];
    }

    function testRequestContextDefaultUserRights()
    {
        $context = new RequestContext();
        $authority = $context->getAuthority(); // wrapper for permissions

        $this->assertInstanceOf(User::class, $authority);
        $this->assertTrue(
            $authority->isAnon(),
            "Default authority initializes an anon user",
        );
        $this->assertFalse(
            $authority->isAllowed(GbSessionProvider::PREMIUM_RIGHT),
            "Anonymous user should not have premium right",
        );
    }

    function testRequestContextSubscriberUserRights()
    {
        $user = $this->getTestUser([
            GbSessionProvider::PREMIUM_GROUP_NAME,
        ])->getUser();
        $context = new RequestContext();
        $context->setAuthority($user);
        $authority = $context->getAuthority();
        $this->assertInstanceOf(User::class, $authority);
        $this->assertTrue(
            $authority->isAllowed(GbSessionProvider::PREMIUM_RIGHT),
            "Subscriber user should have premium right",
        );
    }

    /**
     * @dataProvider provideUserGroup
     **/
    public function testUserPremiumRight(string $groupName, bool $expectedRight)
    {
        $user = $this->getTestUser([$groupName])->getUser();
        $this->assertSame(
            $expectedRight,
            $user->isAllowed(GbSessionProvider::PREMIUM_RIGHT),
            "$groupName has the premium right",
        );
    }

    public static function provideUserGroup()
    {
        return [
            [GbSessionProvider::PREMIUM_GROUP_NAME, true],
            ["bureaucrat", true],
            ["sysop", true],
            ["user", false],
        ];
    }
}
