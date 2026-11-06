<?php

namespace LaravelSoapServer;

/**
 * A SOAP fault detected before the request reaches \SoapServer.
 *
 * @internal
 */
final class SoapRequestFault
{
    public const CLIENT = 'Client';
    public const SERVER = 'Server';
    public const VERSION_MISMATCH = 'VersionMismatch';

    /**
     * SOAP 1.2 equivalents of the SOAP 1.1 fault codes.
     */
    private const SOAP_1_2_CODES = [
        self::CLIENT => 'Sender',
        self::SERVER => 'Receiver',
        self::VERSION_MISMATCH => 'VersionMismatch',
    ];

    public function __construct(
        public readonly string $envelopeNamespace,
        public readonly string $code,
        public readonly string $message,
    ) {
    }

    public function toXml(): string
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $isSoap12 = $this->envelopeNamespace === SoapRequestValidator::SOAP_1_2_ENVELOPE;

        $envelope = $document->createElementNS($this->envelopeNamespace, 'env:Envelope');
        $body = $document->createElementNS($this->envelopeNamespace, 'env:Body');
        $fault = $document->createElementNS($this->envelopeNamespace, 'env:Fault');

        if ($isSoap12) {
            $code = $document->createElementNS($this->envelopeNamespace, 'env:Code');
            $code->appendChild($document->createElementNS($this->envelopeNamespace, 'env:Value', 'env:' . self::SOAP_1_2_CODES[$this->code]));

            $text = $document->createElementNS($this->envelopeNamespace, 'env:Text');
            $text->appendChild($document->createTextNode($this->message));
            $text->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:lang', 'en');

            $reason = $document->createElementNS($this->envelopeNamespace, 'env:Reason');
            $reason->appendChild($text);

            $fault->appendChild($code);
            $fault->appendChild($reason);
        } else {
            $fault->appendChild($document->createElement('faultcode', 'env:' . $this->code));

            $faultString = $document->createElement('faultstring');
            $faultString->appendChild($document->createTextNode($this->message));
            $fault->appendChild($faultString);
        }

        $body->appendChild($fault);
        $envelope->appendChild($body);
        $document->appendChild($envelope);

        return (string) $document->saveXML();
    }
}
