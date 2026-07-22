<?php

namespace justinholtweb\lightning\tests\integration;

use Codeception\Util\ReflectionHelper;
use Craft;
use craft\test\TestCase;
use craft\web\Controller;
use justinholtweb\lightning\controllers\ApiController;
use justinholtweb\lightning\Plugin;
use yii\web\BadRequestHttpException;
use yii\web\MethodNotAllowedHttpException;

class ApiControllerTest extends TestCase
{
    private ApiController $controller;
    private StubPageSpeedService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Plugin::getInstance()->setSettings(['apiKey' => 'test-api-key']);

        // Swap the plugin's PageSpeed component for one with a stubbed HTTP layer.
        $this->service = new StubPageSpeedService();
        $this->service->responses = [file_get_contents(dirname(__DIR__) . '/_fixtures/psi-response.json')];
        Plugin::getInstance()->set('pageSpeed', $this->service);

        $this->controller = new ApiController('api', Plugin::getInstance());
    }

    /**
     * Present the current request as a JSON POST carrying the given body params.
     *
     * The request object is mutated rather than rebuilt: constructing a fresh
     * `craft\web\Request` under CLI blocks reading `php://input`.
     */
    private function postJson(array $bodyParams, string $accept = 'application/json'): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $request = Craft::$app->getRequest();
        $request->setBodyParams($bodyParams);
        $request->setAcceptableContentTypes([$accept => ['q' => 1, 'i' => 0]]);
    }

    public function testRunsBothStrategiesByDefault(): void
    {
        $this->postJson(['url' => 'https://example.com']);

        $data = $this->controller->actionRunAudit()->data;

        $this->assertTrue($data['success']);
        $this->assertSame('https://example.com', $data['data']['url']);
        $this->assertSame(86, $data['data']['mobile']['performanceScore']);
        $this->assertSame(86, $data['data']['desktop']['performanceScore']);
    }

    public function testRunsASingleStrategyWhenRequested(): void
    {
        $this->postJson(['url' => 'https://example.com', 'strategy' => 'desktop']);

        $data = $this->controller->actionRunAudit()->data;

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('desktop', $data['data']);
        $this->assertArrayNotHasKey('mobile', $data['data']);
        $this->assertCount(1, $this->service->requestedUrls);
        $this->assertStringContainsString('strategy=desktop', $this->service->requestedUrls[0]);
    }

    public function testReturnsAFailureResponseWhenAnAuditErrors(): void
    {
        $this->service->responses = [json_encode(['error' => ['message' => 'API key not valid.']])];
        $this->postJson(['url' => 'https://example.com']);

        $data = $this->controller->actionRunAudit()->data;

        $this->assertFalse($data['success']);
        $this->assertSame('API key not valid.', $data['error']);
        $this->assertArrayNotHasKey('data', $data);
    }

    public function testReportsErrorsRaisedByASingleStrategyRun(): void
    {
        $this->service->responses = [false];
        $this->postJson(['url' => 'https://example.com', 'strategy' => 'mobile']);

        $data = $this->controller->actionRunAudit()->data;

        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Failed to connect', $data['error']);
    }

    public function testRejectsAnUnknownStrategy(): void
    {
        $this->postJson(['url' => 'https://example.com', 'strategy' => 'tablet']);

        $data = $this->controller->actionRunAudit()->data;

        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Invalid strategy', $data['error']);
        $this->assertSame([], $this->service->requestedUrls, 'An unknown strategy must not reach the API.');
    }

    public function testRejectsAnEmptyStrategy(): void
    {
        $this->postJson(['url' => 'https://example.com', 'strategy' => '']);

        $this->assertFalse($this->controller->actionRunAudit()->data['success']);
        $this->assertSame([], $this->service->requestedUrls);
    }

    /**
     * @dataProvider invalidUrlProvider
     */
    public function testRejectsUrlsThePsiApiCannotAudit(string $url): void
    {
        $this->postJson(['url' => $url]);

        $data = $this->controller->actionRunAudit()->data;

        $this->assertFalse($data['success']);
        $this->assertStringContainsString('valid http', $data['error']);
        $this->assertSame([], $this->service->requestedUrls, 'An unauditable URL must not reach the API.');
    }

    public static function invalidUrlProvider(): array
    {
        return [
            'local file' => ['file:///etc/passwd'],
            'javascript' => ['javascript:alert(1)'],
            'no scheme' => ['example.com'],
            'not a url' => ['not a url at all'],
            'empty' => [''],
        ];
    }

    public function testAcceptsPlainHttpUrls(): void
    {
        $this->postJson(['url' => 'http://example.com']);

        $this->assertTrue($this->controller->actionRunAudit()->data['success']);
    }

    public function testRequiresAUrl(): void
    {
        $this->postJson([]);

        $this->expectException(BadRequestHttpException::class);
        $this->controller->actionRunAudit();
    }

    public function testRequiresAPostRequest(): void
    {
        $this->postJson(['url' => 'https://example.com']);
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->expectException(MethodNotAllowedHttpException::class);
        $this->controller->actionRunAudit();
    }

    public function testRequiresAJsonRequest(): void
    {
        $this->postJson(['url' => 'https://example.com'], 'text/html');

        $this->expectException(BadRequestHttpException::class);
        $this->controller->actionRunAudit();
    }

    public function testTheEndpointIsNotAnonymouslyAccessible(): void
    {
        // Craft normalizes the `false` declared on the controller to a bitmask.
        $this->assertSame(
            Controller::ALLOW_ANONYMOUS_NEVER,
            ReflectionHelper::readPrivateProperty($this->controller, 'allowAnonymous')
        );
    }
}
