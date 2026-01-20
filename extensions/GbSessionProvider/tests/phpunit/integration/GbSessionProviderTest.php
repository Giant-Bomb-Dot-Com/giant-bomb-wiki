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

    private function getFactory()
    {
        return $this->getServiceContainer()->getSpecialPageFactory();
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
        $config->set("GbSessionProviderTestModeEnabled", false);
        $config->set("GbSessionProviderGbnCookieName", "gb_wiki");
        $config->set("GbSessionProviderTestJWT", null);
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
        $config->set("GbSessionProviderTestModeEnabled", false);
        $config->set("GbSessionProviderGbnCookieName", "gb_wiki");
        $config->set("GbSessionProviderTestJWT", null);
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
        $this->assertSame($claim["preferred_username"], $userInfo->getName());
        $this->assertSame($claim["preferred_username"], $user->getName());
        $this->assertSame($claim["email"], $user->getEmail());
        $this->assertFalse($user->isAnon(), "User is not anonymous");
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
        $config->set("GbSessionProviderTestModeEnabled", false);
        $config->set("GbSessionProviderGbnCookieName", "gb_wiki");
        $config->set("GbSessionProviderTestJWT", null);
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
}
