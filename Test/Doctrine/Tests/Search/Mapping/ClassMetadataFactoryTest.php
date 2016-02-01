<?php
namespace Doctrine\Tests\Search\Mapping;

use Revinate\SearchBundle\Lib\Search\Mapping\ClassMetadataFactory;
/**
 * Test class for ClassMetadataFactory.
 * Generated by PHPUnit on 2011-12-13 at 08:33:27.
 */
class ClassMetadataFactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ClassMetadataFactory
     */
    protected $classMetadataFactory;

    protected function setUp()
    {
        $this->classMetadataFactory = new ClassMetadataFactory();
    }

    /**
     * @expectedException PHPUnit_Framework_Error
     */
    public function testSetSearchManagerWrongParameter()
    {
       $this->classMetadataFactory->setSearchManager(array());
    }

    public function testSetSearchManager()
    {
       $smMock = $this->getMock('Revinate\\SearchBundle\\Lib\\Search\\SearchManager', array(), array(), '', false);
       $this->classMetadataFactory->setSearchManager($smMock);

       $reflClass = new \ReflectionClass($this->classMetadataFactory);
       $reflProperty = $reflClass->getProperty('sm');
       $reflProperty->setAccessible(true);
       $sm = $reflProperty->getValue($this->classMetadataFactory);

       $this->assertInstanceOf('Revinate\SearchBundle\Lib\Search\SearchManager', $sm);
    }

    /**
     * @expectedException PHPUnit_Framework_Error
     */
    public function testSetConfigurationWrongParameter()
    {
        $this->classMetadataFactory->setConfiguration(array());
    }

    public function testSetConfiguration()
    {
        $configMock = $this->getMock('Revinate\\SearchBundle\\Lib\\Search\\Configuration');

        $this->classMetadataFactory->setConfiguration($configMock);

        $reflClass = new \ReflectionClass($this->classMetadataFactory);
        $reflProperty = $reflClass->getProperty('config');
        $reflProperty->setAccessible(true);
        $config = $reflProperty->getValue($this->classMetadataFactory);

        $this->assertInstanceOf('Revinate\SearchBundle\Lib\Search\Configuration', $config);
    }
}
