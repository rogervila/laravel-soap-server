<p align="center"><img width="200" src="https://i.ibb.co/Jw0LrQzY/lavarse-las-manos.png" alt="Laravel SOAP Server" /></p>

[![Status](https://github.com/rogervila/laravel-soap-server/workflows/test/badge.svg)](https://github.com/rogervila/laravel-soap-server/actions)
[![StyleCI](https://github.styleci.io/repos/211657121/shield?branch=main)](https://github.styleci.io/repos/211657121)
[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=rogervila_laravel-soap-server&metric=alert_status)](https://sonarcloud.io/dashboard?id=rogervila_laravel-soap-server)
[![Coverage](https://sonarcloud.io/api/project_badges/measure?project=rogervila_laravel-soap-server&metric=coverage)](https://sonarcloud.io/dashboard?id=rogervila_laravel-soap-server)

[![Latest Stable Version](https://poser.pugx.org/rogervila/laravel-soap-server/v/stable)](https://packagist.org/packages/rogervila/laravel-soap-server)
[![Total Downloads](https://poser.pugx.org/rogervila/laravel-soap-server/downloads)](https://packagist.org/packages/rogervila/laravel-soap-server)
[![License](https://poser.pugx.org/rogervila/laravel-soap-server/license)](https://packagist.org/packages/rogervila/laravel-soap-server)

# Laravel SOAP Server

## About

Laravel SOAP Server is a package that simplifies the creation of SOAP web services in Laravel. It provides a base setup for developing SOAP services, including WSDL generation and request handling.

## Installation

To install the package, use Composer:

```bash
composer require rogervila/laravel-soap-server
```

## Requirements

This package requires **Laravel 12 or 13** and works best with Laravel API routes.

Make sure your application has API routes enabled. If they are not, enable them in `bootstrap/app.php`:

```bash
php artisan install:api
```

This will create the `routes/api.php` file and configure the application to use it.

## Usage

### Defining a Service

Create a service class that will handle the SOAP requests. For example, a `UserService` class:

```php
namespace App\Services;

use stdClass;

class UserService
{
    public function createUser(stdClass $request): array
    {
        return [
            'status' => '201',
            'message' => 'User created successfully',
            'data' => [
                'id' => 123,
                'name' => $request->name,
                'email' => $request->email,
                'created_at' => now()->toDateTimeString(),
            ],
        ];
    }
}
```

### Creating a WSDL View

Create a Blade view for the WSDL definition. For example, `resources/views/wsdl.blade.php`:

```xml
@php echo '<?xml version="1.0" encoding="UTF-8"?>'; @endphp
<definitions name="UserService" targetNamespace="{{ url()->current() }}"
    xmlns:tns="{{ url()->current() }}"
    xmlns:xsd="http://www.w3.org/2001/XMLSchema"
    xmlns:soap="http://schemas.xmlsoap.org/wsdl/soap/"
    xmlns="http://schemas.xmlsoap.org/wsdl/">

    <!-- Types definition -->
    <types>
        <xsd:schema targetNamespace="{{ url()->current() }}">
            <!-- CreateUser Request Type -->
            <xsd:element name="CreateUserRequest">
                <xsd:complexType>
                    <xsd:sequence>
                        <xsd:element name="name" type="xsd:string" minOccurs="1" maxOccurs="1"/>
                        <xsd:element name="email" type="xsd:string" minOccurs="1" maxOccurs="1"/>
                    </xsd:sequence>
                </xsd:complexType>
            </xsd:element>

            <!-- CreateUser Response Type -->
            <xsd:element name="CreateUserResponse">
                <xsd:complexType>
                    <xsd:sequence>
                        <xsd:element name="status" type="xsd:string" minOccurs="1" maxOccurs="1"/>
                        <xsd:element name="message" type="xsd:string" minOccurs="1" maxOccurs="1"/>
                        <xsd:element name="data" minOccurs="0" maxOccurs="1">
                            <xsd:complexType>
                                <xsd:sequence>
                                    <xsd:element name="id" type="xsd:integer"/>
                                    <xsd:element name="name" type="xsd:string"/>
                                    <xsd:element name="email" type="xsd:string"/>
                                    <xsd:element name="created_at" type="xsd:dateTime"/>
                                </xsd:sequence>
                            </xsd:complexType>
                        </xsd:element>
                    </xsd:sequence>
                </xsd:complexType>
            </xsd:element>
        </xsd:schema>
    </types>

    <!-- Message definitions -->
    <message name="CreateUserInput">
        <part name="parameters" element="tns:CreateUserRequest"/>
    </message>
    <message name="CreateUserOutput">
        <part name="parameters" element="tns:CreateUserResponse"/>
    </message>

    <!-- Port Type definitions -->
    <portType name="UserServicePortType">
        <operation name="CreateUser">
            <input message="tns:CreateUserInput"/>
            <output message="tns:CreateUserOutput"/>
        </operation>
    </portType>

    <!-- Binding definitions -->
    <binding name="UserServiceBinding" type="tns:UserServicePortType">
        <soap:binding style="document" transport="http://schemas.xmlsoap.org/soap/http"/>
        <operation name="CreateUser">
            <soap:operation soapAction="{{ url()->current() }}/CreateUser"/>
            <input>
                <soap:body use="literal"/>
            </input>
            <output>
                <soap:body use="literal"/>
            </output>
        </operation>
    </binding>

    <!-- Service definition -->
    <service name="UserService">
        <port name="UserServicePort" binding="tns:UserServiceBinding">
            <soap:address location="{{ url()->current() }}"/>
        </port>
    </service>

</definitions>
```

> [!NOTE]
> Echoing the XML declaration via `@php echo '<?xml version="1.0" encoding="UTF-8"?>'; @endphp` prevents Blade parse errors when `short_open_tag = On` in `php.ini`.

### Defining Routes

Define a route to handle the SOAP requests. This **must** be defined in `routes/api.php` or a route group that does not have CSRF protection enabled.

For example, in `routes/api.php`:

```php
use App\Services\UserService;
use LaravelSoapServer\Soap;

/**
 * GET http://localhost:8000/api/user-soap-service?wsdl Returns the WSDL definition
 * POST http://localhost:8000/api/user-soap-service Handles the SOAP requests
 */
Route::any('/user-soap-service', function () {
    return Soap::handle(view: 'wsdl', service: UserService::class);
});

// Or using the fluent API
/**
 * GET http://localhost:8000/api/user-soap-service-fluent?wsdl Returns the WSDL definition
 * POST http://localhost:8000/api/user-soap-service-fluent Handles the SOAP requests
 */
Route::any('/user-soap-service-fluent', function () {
    return Soap::withView('wsdl')
        ->withService(UserService::class)
        // ->withRequest(request()) // Optional: defaults to current request
        // ->withOptions([]) // Optional: defaults to []
        ->handle();
});
```

### Testing the Service

#### Using a Feature Test

You can test the service by writing a feature test:

```php
namespace Tests\Feature;

use Tests\TestCase;

class SoapTest extends TestCase
{
    public function test_create_user()
    {
        $name = 'John Doe';
        $email = 'john.doe@example.com';
        $url = url('/api/user-soap-service');

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

        $response = $this->call('POST', $url, [], [], [], [
            'HTTP_SOAPACTION' => 'CreateUser',
            'CONTENT_TYPE' => 'text/xml;charset=utf-8',
            'CONTENT_LENGTH' => strlen($payload),
        ], $payload);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/soap+xml;charset=utf-8');
        $response->assertSee('User created successfully');
        $response->assertSee($name);
        $response->assertSee($email);
    }
}
```

#### Using cURL or Postman

You can test the SOAP endpoint using cURL or Postman:

```bash
curl -X POST http://localhost:8000/api/user-soap-service \
  -H "Content-Type: text/xml;charset=utf-8" \
  -H "SOAPAction: CreateUser" \
  -d '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:soap="http://localhost:8000/api/user-soap-service">
    <soapenv:Header/>
    <soapenv:Body>
        <soap:CreateUserRequest>
            <name>John Doe</name>
            <email>john.doe@example.com</email>
        </soap:CreateUserRequest>
    </soapenv:Body>
</soapenv:Envelope>'
```

> [!IMPORTANT]
> - **Target Namespace**: The `xmlns:soap` attribute in the XML envelope must match the exact URL (`targetNamespace`) generated by the WSDL (`{{ url()->current() }}`). If they do not match, the SOAP server will not locate the procedure and will return a SOAP fault with HTTP status code `500` and the message `Procedure '...' not present`.
> - **HTTP Status Codes**: Under the SOAP 1.1/1.2 over HTTP specification, successful responses return HTTP status code `200 OK`. Custom or application status codes (such as `201`) should be returned within the SOAP XML payload (e.g., `<status>201</status>`).

### Customizing the Response

`Soap::handle()` returns an `Illuminate\Http\Response` instance, so its status code and headers can be changed before returning it:

```php
Route::any('/user-soap-service', function () {
    return Soap::handle(view: 'wsdl', service: UserService::class)
        ->setStatusCode(201)
        ->header('X-Custom-Header', 'custom-value');
});
```

> [!WARNING]
> Many SOAP clients expect `200 OK` for successful responses and `500 Internal Server Error` for faults. Change the status code only if your clients support it.

## License

Laravel SOAP Server is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

Icons made by <a href="https://www.flaticon.es/iconos-gratis/manos" title="justicon">justicon</a> from <a href="https://www.flaticon.com/" title="Flaticon">www.flaticon.com</a>
