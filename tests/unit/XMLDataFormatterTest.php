<?php

namespace SilverStripe\RestfulServer\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\RestfulServer\DataFormatter\XMLDataFormatter;
use Exception;

class XMLDataFormatterTest extends SapphireTest
{
    /**
     * Tests {@link Convert::xml2array()}
     */
    public function testConvertStringToArray()
    {
        $inputXML = <<<XML
<?xml version="1.0"?>
<!DOCTYPE results [
  <!ENTITY long "SOME_SUPER_LONG_STRING">
]>
<results>
    <result>My para</result>
    <result>Ampersand &amp; is retained and not double encoded</result>
</results>
XML
        ;
        $expected = [
            'result' => [
                'My para',
                'Ampersand & is retained and not double encoded'
            ]
        ];
        $formatter = new XMLDataFormatter();
        $actual = $formatter->convertStringToArray($inputXML);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Tests {@link Convert::xml2array()} if an exception the contains a reference to a removed <!ENTITY />
     */
    public function testConvertStringToArrayEntityException()
    {
        $inputXML = <<<XML
        <?xml version="1.0"?>
        <!DOCTYPE results [
            <!ENTITY long "SOME_SUPER_LONG_STRING">
        ]>
        <results>
            <result>Now include &long; lots of times to expand the in-memory size of this XML structure</result>
            <result>&long;&long;&long;</result>
        </results>
        XML;
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('String could not be parsed as XML');
        $formatter = new XMLDataFormatter();
        $formatter->convertStringToArray($inputXML);
    }

    /**
     * Tests {@link Convert::xml2array()} if an exception the contains a reference to a multiple removed <!ENTITY />
     */
    public function testConvertStringToArrayMultipleEntitiesException()
    {
        $inputXML = <<<XML
        <?xml version="1.0"?>
        <!DOCTYPE results [<!ENTITY long "SOME_SUPER_LONG_STRING"><!ENTITY short "SHORT_STRING">]>
        <results>
            <result>Now include &long; and &short; lots of times</result>
            <result>&long;&long;&long;&short;&short;&short;</result>
        </results>
        XML;
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('String could not be parsed as XML');
        $formatter = new XMLDataFormatter();
        $formatter->convertStringToArray($inputXML);
    }
}
