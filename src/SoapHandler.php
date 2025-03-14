<?php

namespace LaravelSoapServer;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Contracts\View\View;

class SoapHandler
{
    /**
     * @param array<string, mixed> $viewData
     * @param array<string, mixed> $options https://www.php.net/manual/en/soapserver.construct.php
     */
    public function __construct(
        private View|string|null $view = null,
        private ?string $service = null,
        private array $options = [],
        private ?Request $request = null,
        private array $viewData = [],
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function withView(View|string $view, array $data = []): self
    {
        $this->view = $view;
        $this->viewData = $data;

        return $this;
    }

    public function withRequest(Request $request): self
    {
        $this->request = $request;

        return $this;
    }

    public function withService(string $service): self
    {
        $this->service = $service;

        return $this;
    }

    /**
     * @param array<string, mixed> $options https://www.php.net/manual/en/soapserver.construct.php
     */
    public function withOptions(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @param array<string, mixed> $viewData
     * @param array<string, mixed> $options https://www.php.net/manual/en/soapserver.construct.php
     *
     * @throws \InvalidArgumentException
     */
    public function handle(
        View|string|null $view = null,
        ?string $service = null,
        array $options = [],
        ?Request $request = null,
        array $viewData = [],
    ): Response {
        if (!is_null($view)) {
            $this->view = $view;
        }

        if (!is_null($service)) {
            $this->service = $service;
        }

        if (!is_null($request)) {
            $this->request = $request;
        }

        if (!empty($viewData)) {
            $this->viewData = $viewData;
        }

        if (!empty($options)) {
            $this->options = $options;
        }

        if (is_null($this->service) || is_null($this->view)) {
            throw new \InvalidArgumentException('Service and view are required');
        }

        $this->request ??= request();

        if (is_string($this->view)) {
            $this->view = view(str_replace('.blade.php', '', $this->view), $this->viewData);
        }

        if ($this->request->has('wsdl')) {
            return response()->view($this->view->name(), $this->viewData, Response::HTTP_OK, [
                'Content-Type' => 'text/xml;charset=utf-8',
            ]);
        }

        $wsdlContent = $this->view->render();
        $wsdl = storage_path(sprintf('app%swsdl-%s.xml', DIRECTORY_SEPARATOR, md5($wsdlContent)));

        if (!file_exists($wsdl)) {
            file_put_contents($wsdl, $wsdlContent);
        }

        $server = new \SoapServer($wsdl, array_merge([
            'trace' => true,
            'exceptions' => true,
            'cache_wsdl' => app()->isProduction() ? WSDL_CACHE_MEMORY : WSDL_CACHE_NONE,
            'send_errors' => ! app()->isProduction(),
            'encoding' => 'UTF-8',
        ], $this->options));

        $server->setClass($this->service);

        ob_start();

        try {
            $server->handle($this->request->getContent());

            $soapXml = ob_get_clean();

            return response((string) $soapXml, Response::HTTP_OK, [
                'Content-Type' => 'application/soap+xml;charset=utf-8',
            ]);
        }  catch (\SoapFault $fault) {
            ob_end_clean();
            return response($fault->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR, [
                'Content-Type' => 'text/html',
            ]);
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }
}
