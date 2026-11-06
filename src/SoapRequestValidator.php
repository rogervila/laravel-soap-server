<?php

namespace LaravelSoapServer;

/**
 * Detects SOAP requests that would make \SoapServer::handle() abort the PHP process.
 *
 * ext-soap reports some request errors (malformed envelope, unknown procedure,
 * missing service method) by emitting a SOAP fault and calling zend_bailout(),
 * which cannot be caught from userland. The request dies before Laravel can send
 * a response, and Laravel's shutdown handler fails with
 * "Call to a member function send() on null".
 *
 * This validator replicates those checks so a SOAP fault can be returned instead.
 * It is deliberately lenient: when in doubt, the request is let through.
 *
 * @internal
 */
final class SoapRequestValidator
{
    public const SOAP_1_1_ENVELOPE = 'http://schemas.xmlsoap.org/soap/envelope/';
    public const SOAP_1_2_ENVELOPE = 'http://www.w3.org/2003/05/soap-envelope';

    private const WSDL_NAMESPACE = 'http://schemas.xmlsoap.org/wsdl/';

    private const WSDL_SOAP_NAMESPACES = [
        'http://schemas.xmlsoap.org/wsdl/soap/',
        'http://schemas.xmlsoap.org/wsdl/soap12/',
    ];

    public function __construct(
        private readonly string $wsdl,
        private readonly string $service,
    ) {
    }

    public function validate(string $request): ?SoapRequestFault
    {
        $document = $this->load($request);

        if (is_null($document)) {
            return new SoapRequestFault(self::SOAP_1_1_ENVELOPE, SoapRequestFault::CLIENT, 'Bad Request');
        }

        if (!is_null($document->doctype)) {
            return new SoapRequestFault(self::SOAP_1_1_ENVELOPE, SoapRequestFault::CLIENT, 'DTD are not supported by SOAP');
        }

        $envelope = $document->documentElement;

        if (is_null($envelope) || $envelope->localName !== 'Envelope') {
            return new SoapRequestFault(self::SOAP_1_1_ENVELOPE, SoapRequestFault::CLIENT, "Bad Request. Can't find Envelope");
        }

        $version = $envelope->namespaceURI;

        if ($version !== self::SOAP_1_1_ENVELOPE && $version !== self::SOAP_1_2_ENVELOPE) {
            return new SoapRequestFault(self::SOAP_1_1_ENVELOPE, SoapRequestFault::VERSION_MISMATCH, 'Wrong Version');
        }

        $body = $this->firstChildElement($envelope, 'Body', $version);

        if (is_null($body)) {
            return new SoapRequestFault($version, SoapRequestFault::CLIENT, 'Body must be present in a SOAP envelope');
        }

        $operations = $this->operations();

        if (is_null($operations)) {
            // The WSDL could not be inspected reliably, let \SoapServer deal with the request.
            return null;
        }

        $call = $this->firstChildElement($body);
        $function = $this->findFunction($operations, $call);

        if (is_null($function)) {
            $message = is_null($call)
                ? 'looks like we got "Body" without function call'
                : sprintf("Procedure '%s' not present", $call->localName);

            return new SoapRequestFault($version, is_null($call) ? SoapRequestFault::CLIENT : SoapRequestFault::SERVER, $message);
        }

        if (!method_exists($this->service, $function) && !method_exists($this->service, '__call')) {
            return new SoapRequestFault($version, SoapRequestFault::SERVER, sprintf("Function '%s' doesn't exist", $function));
        }

        return null;
    }

    private function load(string $xml): ?\DOMDocument
    {
        if (trim($xml) === '') {
            return null;
        }

        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadXML($xml, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $loaded ? $document : null;
    }

    private function firstChildElement(\DOMElement $parent, ?string $localName = null, ?string $namespace = null): ?\DOMElement
    {
        foreach ($parent->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if (is_null($localName)) {
                return $child;
            }

            if ($child->localName === $localName && $child->namespaceURI === $namespace) {
                return $child;
            }
        }

        return null;
    }

    /**
     * Returns the function name \SoapServer would call, mirroring ext-soap's find_function().
     *
     * @param list<array{name: string, input: ?string, style: string, part: ?array{name: string, element: ?string, namespace: ?string}}> $operations
     */
    private function findFunction(array $operations, ?\DOMElement $call): ?string
    {
        if (is_null($call)) {
            foreach ($operations as $operation) {
                if ($operation['style'] === 'document' && is_null($operation['part'])) {
                    return $operation['name'];
                }
            }

            return null;
        }

        $name = (string) $call->localName;

        // Lookup by operation (or input) name, as done by ext-soap's get_function().
        foreach ($operations as $operation) {
            $matchesName = strcasecmp($operation['name'], $name) === 0
                || (!is_null($operation['input']) && strcasecmp($operation['input'], $name) === 0);

            if ($matchesName && ($operation['style'] !== 'document' || is_null($operation['part']))) {
                return $operation['name'];
            }
        }

        // Lookup by request element, as done by ext-soap's get_doc_function().
        foreach ($operations as $operation) {
            $part = $operation['part'];

            if ($operation['style'] !== 'document' || is_null($part)) {
                continue;
            }

            if (is_null($part['element'])) {
                if ($part['name'] === $name) {
                    return $operation['name'];
                }

                continue;
            }

            if ($part['element'] === $name && $part['namespace'] === $call->namespaceURI) {
                return $operation['name'];
            }
        }

        return null;
    }

    /**
     * Extracts the SOAP operations from the WSDL, or null when it cannot be inspected reliably.
     *
     * @return ?list<array{name: string, input: ?string, style: string, part: ?array{name: string, element: ?string, namespace: ?string}}>
     */
    private function operations(): ?array
    {
        $document = $this->load($this->wsdl);
        $definitions = $document?->documentElement;

        if (is_null($definitions)
            || $definitions->localName !== 'definitions'
            || $definitions->namespaceURI !== self::WSDL_NAMESPACE
            || $definitions->getElementsByTagNameNS(self::WSDL_NAMESPACE, 'import')->length > 0
        ) {
            return null;
        }

        $messages = [];

        foreach ($definitions->getElementsByTagNameNS(self::WSDL_NAMESPACE, 'message') as $message) {
            $part = $this->firstChildElement($message, 'part', self::WSDL_NAMESPACE);

            $messages[$message->getAttribute('name')] = is_null($part) ? null : [
                'name' => $part->getAttribute('name'),
                'element' => $part->hasAttribute('element') ? $this->localName($part->getAttribute('element')) : null,
                'namespace' => $part->hasAttribute('element') ? $this->namespace($part, $part->getAttribute('element')) : null,
            ];
        }

        $inputs = [];

        foreach ($definitions->getElementsByTagNameNS(self::WSDL_NAMESPACE, 'portType') as $portType) {
            foreach ($portType->getElementsByTagNameNS(self::WSDL_NAMESPACE, 'operation') as $operation) {
                $input = $this->firstChildElement($operation, 'input', self::WSDL_NAMESPACE);

                if (is_null($input)) {
                    continue;
                }

                $inputs[$operation->getAttribute('name')] = $input;
            }
        }

        $operations = [];

        foreach ($definitions->getElementsByTagNameNS(self::WSDL_NAMESPACE, 'binding') as $binding) {
            $soapBinding = $this->soapChild($binding, 'binding');

            if (is_null($soapBinding)) {
                continue;
            }

            $bindingStyle = $soapBinding->getAttribute('style') ?: 'document';

            foreach ($binding->getElementsByTagNameNS(self::WSDL_NAMESPACE, 'operation') as $operation) {
                $name = $operation->getAttribute('name');
                $input = $inputs[$name] ?? null;
                $message = is_null($input) ? null : $this->localName($input->getAttribute('message'));

                if (!is_null($message) && !array_key_exists($message, $messages)) {
                    // Message defined elsewhere (e.g. an imported document).
                    return null;
                }

                $operations[] = [
                    'name' => $name,
                    'input' => $input?->getAttribute('name') ?: null,
                    'style' => $this->soapChild($operation, 'operation')?->getAttribute('style') ?: $bindingStyle,
                    'part' => is_null($message) ? null : $messages[$message],
                ];
            }
        }

        return $operations === [] ? null : $operations;
    }

    private function soapChild(\DOMElement $parent, string $localName): ?\DOMElement
    {
        foreach (self::WSDL_SOAP_NAMESPACES as $namespace) {
            $child = $this->firstChildElement($parent, $localName, $namespace);

            if (!is_null($child)) {
                return $child;
            }
        }

        return null;
    }

    private function localName(string $qualifiedName): string
    {
        $position = strrpos($qualifiedName, ':');

        return $position === false ? $qualifiedName : substr($qualifiedName, $position + 1);
    }

    private function namespace(\DOMElement $context, string $qualifiedName): ?string
    {
        $position = strpos($qualifiedName, ':');
        $prefix = $position === false ? null : substr($qualifiedName, 0, $position);

        return $context->lookupNamespaceURI($prefix);
    }
}
