@php echo '<?xml version="1.0" encoding="UTF-8"?>'; @endphp
<definitions name="UserService" targetNamespace="{{ url()->current() }}"
    xmlns:tns="{{ url()->current() }}"
    xmlns:xsd="http://www.w3.org/2001/XMLSchema"
    xmlns:soap="http://schemas.xmlsoap.org/wsdl/soap/"
    xmlns="http://schemas.xmlsoap.org/wsdl/">

    <message name="CountUsersInput">
        <part name="domain" type="xsd:string"/>
    </message>
    <message name="CountUsersOutput">
        <part name="total" type="xsd:int"/>
    </message>

    <portType name="UserServicePortType">
        <operation name="CountUsers">
            <input message="tns:CountUsersInput"/>
            <output message="tns:CountUsersOutput"/>
        </operation>
    </portType>

    <binding name="UserServiceBinding" type="tns:UserServicePortType">
        <soap:binding style="rpc" transport="http://schemas.xmlsoap.org/soap/http"/>
        <operation name="CountUsers">
            <soap:operation soapAction="{{ url()->current() }}/CountUsers"/>
            <input>
                <soap:body use="literal" namespace="{{ url()->current() }}"/>
            </input>
            <output>
                <soap:body use="literal" namespace="{{ url()->current() }}"/>
            </output>
        </operation>
    </binding>

    <service name="UserService">
        <port name="UserServicePort" binding="tns:UserServiceBinding">
            <soap:address location="{{ url()->current() }}"/>
        </port>
    </service>

</definitions>
