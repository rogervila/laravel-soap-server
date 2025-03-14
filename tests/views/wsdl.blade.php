<?xml version="1.0" encoding="UTF-8"?>
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
