<?php

namespace MediaWiki\Extension\GiantBombResolve\Test\Rest;

use FauxRequest;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWikiIntegrationTestCase;
use MediaWiki\Extension\GiantBombResolve\Rest\ResolveHandler;

/**
 * @covers \MediaWiki\Extension\GiantBombResolve\Rest\ResolveHandler
 *
 * @group Database
 */
class ResolveHandlerTest extends MediaWikiIntegrationTestCase
{
    use MockServiceDependenciesTrait;

    private function newHandlerWithAskResults(array $results): ResolveHandler
    {
        return new class ($results) extends ResolveHandler {
            /** @var array<string,mixed> */
            private $results;

            public function __construct(array $results)
            {
                $this->results = $results;
                parent::__construct();
            }

            protected function runAskQuery(string $query): array
            {
                return $this->results;
            }
        };
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure the extension configuration is available
        $this->setMwGlobals([
            "wgGiantBombResolveBatchLimit" => 25,
            "wgGiantBombResolveAllowPublic" => true,
            "wgGiantBombResolveFields" => [
                "displaytitle",
                "fullurl",
                "fulltext",
                "namespace",
                "pageid",
                "image",
            ],
        ]);
    }

    public function testParseGuidsValidatesInput(): void
    {
        $handler = new ResolveHandler();
        $request = new FauxRequest(["guids" => "3030-123,3005-456"]);
        $handler->setRequest($request);

        $result = $handler->execute();
        $this->assertInstanceOf(Response::class, $result);
        $data = json_decode($result->getBody()->getContents(), true);
        $this->assertCount(2, $data["guids"]);
        $this->assertSame("3030-123", $data["guids"][0]["guid"]);
        $this->assertSame("2", $result->getHeaderLine("X-GB-Resolve-Count"));
    }

    public function testParseGuidsRejectsInvalid(): void
    {
        $handler = new ResolveHandler();
        $request = new FauxRequest(["guids" => "invalid-guid"]);
        $handler->setRequest($request);
        $this->expectException(HttpException::class);
        $handler->execute();
    }

    public function testMissingAuthWhenPublicDisabled(): void
    {
        $this->overrideConfigValue("GiantBombResolveAllowPublic", false);
        $handler = new ResolveHandler();
        $request = new FauxRequest(["guids" => "3030-1"]);
        $handler->setRequest($request);
        $this->expectException(HttpException::class);
        $handler->execute();
    }

    public function testInternalTokenAllowsAccess(): void
    {
        $this->overrideConfigValue("GiantBombResolveAllowPublic", false);
        $this->overrideConfigValue(
            "GiantBombResolveInternalToken",
            "secret-token",
        );
        $handler = new ResolveHandler();

        $request = new FauxRequest(["guids" => "3030-1"], null, true, [
            "X-GB-Internal-Key" => "secret-token",
        ]);
        $handler->setRequest($request);

        $response = $handler->execute();
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testBatchLimit(): void
    {
        $this->overrideConfigValue("GiantBombResolveBatchLimit", 1);
        $handler = new ResolveHandler();
        $request = new FauxRequest(["guids" => "3030-1,3030-2"]);
        $handler->setRequest($request);
        $this->expectException(HttpException::class);
        $handler->execute();
    }

    public function testParseGuidsAcceptsUuid(): void
    {
        $handler = new ResolveHandler();
        $uuid = "b4e96727-2fd5-4f13-93d3-f105d003387d";
        $request = new FauxRequest(["guids" => $uuid]);
        $handler->setRequest($request);

        $result = $handler->execute();
        $this->assertInstanceOf(Response::class, $result);
        $data = json_decode($result->getBody()->getContents(), true);
        $this->assertCount(1, $data["guids"]);
        $this->assertSame($uuid, $data["guids"][0]["guid"]);
    }

    public function testParseGuidsAcceptsMixedLegacyAndUuid(): void
    {
        $handler = new ResolveHandler();
        $uuid = "b4e96727-2fd5-4f13-93d3-f105d003387d";
        $request = new FauxRequest(["guids" => "3030-123," . $uuid]);
        $handler->setRequest($request);

        $result = $handler->execute();
        $this->assertInstanceOf(Response::class, $result);
        $data = json_decode($result->getBody()->getContents(), true);
        $this->assertCount(2, $data["guids"]);
        $this->assertSame("3030-123", $data["guids"][0]["guid"]);
        $this->assertSame($uuid, $data["guids"][1]["guid"]);
    }

    public function testUuidGuidIncludesAssocIdSentinelAndMappedTypeId(): void
    {
        $uuid = "b4e96727-2fd5-4f13-93d3-f105d003387d";
        $handler = $this->newHandlerWithAskResults([
            "Games/Stubbed" => [
                "displaytitle" => "Stubbed Game",
                "fullurl" => "https://www.giantbomb.com/wiki/Games/Stubbed",
                "fulltext" => "Games/Stubbed",
                "namespace" => 0,
                "pageid" => 1,
                "printouts" => [],
            ],
        ]);
        $request = new FauxRequest(["guids" => $uuid]);
        $handler->setRequest($request);

        $result = $handler->execute();
        $this->assertInstanceOf(Response::class, $result);
        $data = json_decode($result->getBody()->getContents(), true);

        $this->assertSame("ok", $data["guids"][0]["status"]);
        $this->assertSame($uuid, $data["guids"][0]["guid"]);
        $this->assertSame(3030, $data["guids"][0]["assocTypeId"]);
        $this->assertSame(0, $data["guids"][0]["assocId"]);
    }
}
