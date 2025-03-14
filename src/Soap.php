<?php

namespace LaravelSoapServer;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @see \LaravelSoapServer\SoapHandler
 *
 * @method static \LaravelSoapServer\SoapHandler withView(\Illuminate\Contracts\View\View|string $view, array<string, mixed> $data = [])
 * @method static \LaravelSoapServer\SoapHandler withRequest(\Illuminate\Http\Request $request)
 * @method static \LaravelSoapServer\SoapHandler withService(string $service)
 * @method static \LaravelSoapServer\SoapHandler withOptions(array<string, mixed> $options)
 * @method static \LaravelSoapServer\SoapHandler handle(\Illuminate\Contracts\View\View|string|null $view = null, ?string $service = null, array<string, mixed> $options = [], ?\Illuminate\Http\Request $request = null, array<string, mixed> $viewData = [])
 */
class Soap extends Facade
{
    #[\Override]
    protected static function getFacadeAccessor(): string
    {
        return SoapHandler::class;
    }
}
