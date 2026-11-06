<?php

namespace Tests\LaravelSoapServer;

use Orchestra\Testbench\TestCase;
use Tests\LaravelSoapServer\Stubs\UserService;
use LaravelSoapServer\Soap;
use Illuminate\Foundation\Testing\Concerns\InteractsWithViews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Attributes\WithMigration;
use Orchestra\Testbench\Attributes\WithEnv;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\LaravelSoapServer\Stubs\EmptyService;
use Tests\LaravelSoapServer\Stubs\User;

#[WithEnv('DB_CONNECTION', 'testing')]
#[WithEnv('LOG_CHANNEL', 'null')]
#[WithEnv('APP_DEBUG', 'true')]
#[WithConfig('database.default', 'testing')]
#[WithConfig('view.paths', [__DIR__ . '/views'])]
#[WithMigration]
final class SoapTest extends TestCase
{
    use InteractsWithViews;
    use RefreshDatabase;

    /**
     * Define routes setup.
     *
     * @param  \Illuminate\Routing\Router  $router
     * @return void
     */
    #[\Override]
    protected function defineRoutes($router)
    {
        $router->any('/soap', fn () => Soap::handle(view: 'wsdl', service: UserService::class));
        $router->any('/soap-fluent', fn () => Soap::withView('wsdl')->withService(UserService::class)->handle());
        $router->any('/soap-rpc', fn () => Soap::handle(view: 'wsdl-rpc', service: UserService::class));
        $router->any('/soap-empty-service', fn () => Soap::handle(view: 'wsdl', service: EmptyService::class));
        $router->any('/soap-custom-response', fn () => Soap::handle(view: 'wsdl', service: UserService::class)
            ->setStatusCode(201)
            ->header('X-Custom-Header', 'custom-value'));
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    #[\Override]
    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
    }

    public function test_returns_wsdl(): void
    {
        $response = $this->get('/soap?wsdl');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/xml;charset=utf-8');

        $this->assertIsString($content = $response->getContent());

        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($content));

        $element = $dom->getElementsByTagName('definitions')->item(0);
        $targetNamespace = $element?->getAttribute('targetNamespace');

        $this->assertEquals(url()->current(), $targetNamespace);
    }

    public function test_returns_soap_response(): void
    {
        $name = fake()->name();
        $email = fake()->email();
        $url = url('/soap');

        $payload = <<<XML
        <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:soap="$url">
            <soapenv:Header/>
            <soapenv:Body>
                <soap:CreateUserRequest>
                    <name>$name</name>
                    <email>$email</email>
                </soap:CreateUserRequest>
            </soapenv:Body>
        </soapenv:Envelope>
        XML;

        $response = $this->call('POST', $url, [], [], [], array_merge($_SERVER, [
            'HTTP_SOAPACTION' => 'CreateUser',
            'CONTENT_TYPE' => 'text/xml;charset=utf-8',
            'CONTENT_LENGTH' => strlen($payload),
        ]), $payload);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/soap+xml;charset=utf-8');

        $this->assertDatabaseHas('users', [
            'name' => $name,
            'email' => $email,
        ]);

        $this->assertIsString($content = $response->getContent());

        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($content));

        $statusElement = $dom->getElementsByTagName('status')->item(0);
        $this->assertEquals('201', $statusElement?->nodeValue);

        $messageElement = $dom->getElementsByTagName('message')->item(0);
        $this->assertEquals('User created successfully', $messageElement?->nodeValue);

        $nameElement = $dom->getElementsByTagName('name')->item(0);
        $this->assertEquals($name, $nameElement?->nodeValue);

        $emailElement = $dom->getElementsByTagName('email')->item(0);
        $this->assertEquals($email, $emailElement?->nodeValue);
    }

    public function test_returns_soap_response_using_fluent_api(): void
    {
        $name = fake()->name();
        $email = fake()->email();
        $url = url('/soap-fluent');

        $payload = <<<XML
        <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:soap="$url">
            <soapenv:Header/>
            <soapenv:Body>
                <soap:CreateUserRequest>
                    <name>$name</name>
                    <email>$email</email>
                </soap:CreateUserRequest>
            </soapenv:Body>
        </soapenv:Envelope>
        XML;

        $response = $this->call('POST', $url, [], [], [], array_merge($_SERVER, [
            'HTTP_SOAPACTION' => 'CreateUser',
            'CONTENT_TYPE' => 'text/xml;charset=utf-8',
            'CONTENT_LENGTH' => strlen($payload),
        ]), $payload);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/soap+xml;charset=utf-8');

        $this->assertDatabaseHas('users', [
            'name' => $name,
            'email' => $email,
        ]);

        $this->assertIsString($content = $response->getContent());

        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($content));

        $statusElement = $dom->getElementsByTagName('status')->item(0);
        $this->assertEquals('201', $statusElement?->nodeValue);

        $messageElement = $dom->getElementsByTagName('message')->item(0);
        $this->assertEquals('User created successfully', $messageElement?->nodeValue);

        $nameElement = $dom->getElementsByTagName('name')->item(0);
        $this->assertEquals($name, $nameElement?->nodeValue);

        $emailElement = $dom->getElementsByTagName('email')->item(0);
        $this->assertEquals($email, $emailElement?->nodeValue);
    }

    public function test_response_status_and_headers_can_be_customized(): void
    {
        $name = fake()->name();
        $email = fake()->email();
        $url = url('/soap-custom-response');

        $response = $this->soapCall($url, $this->createUserEnvelope($url, $name, $email));

        $response->assertStatus(201);
        $response->assertHeader('X-Custom-Header', 'custom-value');
        $response->assertHeader('Content-Type', 'application/soap+xml;charset=utf-8');

        $this->assertDatabaseHas('users', [
            'name' => $name,
            'email' => $email,
        ]);
    }

    public function test_returns_soap_1_2_response(): void
    {
        $name = fake()->name();
        $email = fake()->email();
        $url = url('/soap');

        $response = $this->soapCall($url, $this->createUserEnvelope($url, $name, $email, self::SOAP_1_2), 'application/soap+xml;charset=utf-8');

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'name' => $name,
            'email' => $email,
        ]);

        $this->assertIsString($content = $response->getContent());

        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($content));
        $this->assertEquals(self::SOAP_1_2, $dom->documentElement?->namespaceURI);
        $this->assertEquals('201', $dom->getElementsByTagName('status')->item(0)?->nodeValue);
    }

    public function test_returns_rpc_response(): void
    {
        User::query()->create(['name' => 'John Doe', 'email' => 'john@example.com', 'password' => 'secret']);
        $url = url('/soap-rpc');

        $payload = <<<XML
        <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:soap="$url">
            <soapenv:Body>
                <soap:countUsers>
                    <domain>example.com</domain>
                </soap:countUsers>
            </soapenv:Body>
        </soapenv:Envelope>
        XML;

        $response = $this->soapCall($url, $payload);

        $response->assertStatus(200);

        $this->assertIsString($content = $response->getContent());

        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($content));
        $this->assertEquals('1', $dom->getElementsByTagName('total')->item(0)?->nodeValue);
    }

    public function test_unknown_rpc_operation_returns_soap_fault(): void
    {
        $url = url('/soap-rpc');

        $payload = <<<XML
        <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:soap="$url">
            <soapenv:Body>
                <soap:DeleteUsers/>
            </soapenv:Body>
        </soapenv:Envelope>
        XML;

        $response = $this->soapCall($url, $payload);

        $this->assertSoapFault($response, 'Server', "Procedure 'DeleteUsers' not present");
    }

    public function test_wrong_namespace_returns_soap_fault(): void
    {
        $email = fake()->email();

        $response = $this->soapCall(url('/soap'), $this->createUserEnvelope('http://wrong.example.com/soap', fake()->name(), $email));

        $this->assertSoapFault($response, 'Server', "Procedure 'CreateUserRequest' not present");
        $this->assertDatabaseMissing('users', ['email' => $email]);
    }

    public function test_wrong_namespace_returns_soap_1_2_fault(): void
    {
        $email = fake()->email();

        $response = $this->soapCall(url('/soap'), $this->createUserEnvelope('http://wrong.example.com/soap', fake()->name(), $email, self::SOAP_1_2));

        $response->assertStatus(500);

        $this->assertIsString($content = $response->getContent());

        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($content));
        $this->assertEquals(self::SOAP_1_2, $dom->documentElement?->namespaceURI);
        $this->assertEquals('env:Receiver', $dom->getElementsByTagNameNS(self::SOAP_1_2, 'Value')->item(0)?->nodeValue);
        $this->assertEquals("Procedure 'CreateUserRequest' not present", $dom->getElementsByTagNameNS(self::SOAP_1_2, 'Text')->item(0)?->nodeValue);
        $this->assertDatabaseMissing('users', ['email' => $email]);
    }

    public function test_unknown_operation_returns_soap_fault(): void
    {
        $url = url('/soap');

        $payload = <<<XML
        <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:soap="$url">
            <soapenv:Body>
                <soap:DeleteUserRequest>
                    <id>1</id>
                </soap:DeleteUserRequest>
            </soapenv:Body>
        </soapenv:Envelope>
        XML;

        $response = $this->soapCall($url, $payload);

        $this->assertSoapFault($response, 'Server', "Procedure 'DeleteUserRequest' not present");
    }

    public function test_missing_service_method_returns_soap_fault(): void
    {
        $url = url('/soap-empty-service');

        $response = $this->soapCall($url, $this->createUserEnvelope($url, fake()->name(), fake()->email()));

        $this->assertSoapFault($response, 'Server', "Function 'CreateUser' doesn't exist");
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidEnvelopeProvider(): array
    {
        return [
            'empty body' => ['', 'Bad Request'],
            'malformed xml' => ['<soapenv:Envelope', 'Bad Request'],
            'not an envelope' => ['<foo/>', "Bad Request. Can't find Envelope"],
            'wrong version' => ['<Envelope xmlns="http://example.com/envelope"><Body/></Envelope>', 'Wrong Version'],
            'missing body' => ['<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Header/></soapenv:Envelope>', 'Body must be present in a SOAP envelope'],
            'empty body element' => ['<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body/></soapenv:Envelope>', 'looks like we got "Body" without function call'],
            'dtd' => ['<!DOCTYPE foo [<!ENTITY bar "baz">]><soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body/></soapenv:Envelope>', 'DTD are not supported by SOAP'],
        ];
    }

    #[DataProvider('invalidEnvelopeProvider')]
    public function test_invalid_envelope_returns_soap_fault(string $payload, string $message): void
    {
        $response = $this->soapCall(url('/soap'), $payload);

        $this->assertSoapFault($response, $message === 'Wrong Version' ? 'VersionMismatch' : 'Client', $message);
    }

    public function test_invalid_arguments(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Soap::handle(view: null, service: null);
    }

    public function test_invalid_view(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Soap::handle(view: uniqid(), service: UserService::class);
    }

    private const SOAP_1_1 = 'http://schemas.xmlsoap.org/soap/envelope/';
    private const SOAP_1_2 = 'http://www.w3.org/2003/05/soap-envelope';

    private function createUserEnvelope(string $namespace, string $name, string $email, string $envelope = self::SOAP_1_1): string
    {
        return <<<XML
        <soapenv:Envelope xmlns:soapenv="$envelope" xmlns:soap="$namespace">
            <soapenv:Header/>
            <soapenv:Body>
                <soap:CreateUserRequest>
                    <name>$name</name>
                    <email>$email</email>
                </soap:CreateUserRequest>
            </soapenv:Body>
        </soapenv:Envelope>
        XML;
    }

    /**
     * @return \Illuminate\Testing\TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function soapCall(string $url, string $payload, string $contentType = 'text/xml;charset=utf-8'): \Illuminate\Testing\TestResponse
    {
        return $this->call('POST', $url, [], [], [], array_merge($_SERVER, [
            'HTTP_SOAPACTION' => 'CreateUser',
            'CONTENT_TYPE' => $contentType,
            'CONTENT_LENGTH' => strlen($payload),
        ]), $payload);
    }

    /**
     * @param \Illuminate\Testing\TestResponse<\Symfony\Component\HttpFoundation\Response> $response
     */
    private function assertSoapFault(\Illuminate\Testing\TestResponse $response, string $code, string $message): void
    {
        $response->assertStatus(500);
        $response->assertHeader('Content-Type', 'application/soap+xml;charset=utf-8');

        $this->assertIsString($content = $response->getContent());

        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($content));
        $this->assertEquals(self::SOAP_1_1, $dom->documentElement?->namespaceURI);
        $this->assertEquals('env:' . $code, $dom->getElementsByTagName('faultcode')->item(0)?->nodeValue);
        $this->assertEquals($message, $dom->getElementsByTagName('faultstring')->item(0)?->nodeValue);
    }
}
