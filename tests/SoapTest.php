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
}
