<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Revinate\SearchBundle\Lib\Search\Mapping\Driver;

use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\Common\Persistence\Mapping\Driver\AnnotationDriver as AbstractAnnotationDriver;
use Revinate\SearchBundle\Lib\Search\Exception\Driver as DriverException;
use Revinate\SearchBundle\Lib\Search\Mapping\Annotations as Search;

/**
 * The AnnotationDriver reads the mapping metadata from docblock annotations.
 *
 * @link        www.doctrine-project.org
 * @since       1.0
 * @author      Mike Lohmann <mike.h.lohmann@googlemail.com>
 */
class AnnotationDriver extends AbstractAnnotationDriver
{
    /**
     * {@inheritDoc}
     */
    protected $entityAnnotationClasses = array(
        'Revinate\\SearchBundle\\Lib\\Search\\Mapping\\Annotations\\Searchable' => 1,
        'Revinate\\SearchBundle\\Lib\\Search\\Mapping\\Annotations\\ElasticSearchable' => 2,
        'Revinate\\SearchBundle\\Lib\\Search\\Mapping\\Annotations\\ElasticRoot' => 3,
    );

    protected $entityRootAnnotationClass = 'Revinate\\SearchBundle\\Lib\\Search\\Mapping\\Annotations\\ElasticRoot';

    protected $entityIdAnnotationClass = 'Revinate\\SearchBundle\\Lib\\Search\\Mapping\\Annotations\\Id';

    protected $entityTimeSeriesFieldAnnotationClass = 'Revinate\\SearchBundle\\Lib\\Search\\Mapping\\Annotations\\TimeSeriesField';

    protected $entityParentFieldAnnotationClass = 'Revinate\\SearchBundle\\Lib\\Search\\Mapping\\Annotations\\ParentField';

    protected $entityVersionFieldAnnotationClass = 'Revinate\\SearchBundle\\Lib\\Search\\Mapping\\Annotations\\VersionField';

    protected $entityParamAnnotationClass = 'Revinate\\SearchBundle\\Lib\\Search\\Mapping\\Annotations\\Parameter';

    /**
     * Document fields annotation classes, ordered by precedence.
     */
    protected $entityFieldAnnotationClasses = array(
        'Revinate\\SearchBundle\\Lib\\Search\\Mapping\\Annotations\\Id',        //Only here for convenience
        'Revinate\\SearchBundle\\Lib\\Search\\Mapping\\Annotations\\Parameter', //Only here for convenience
        'Revinate\\SearchBundle\\Lib\\Search\\Mapping\\Annotations\\Field',
        'Revinate\\SearchBundle\\Lib\\Search\\Mapping\\Annotations\\TimeSeriesField',
        'Revinate\\SearchBundle\\Lib\\Search\\Mapping\\Annotations\\ElasticField',
        'Revinate\\SearchBundle\\Lib\\Search\\Mapping\\Annotations\\ParentField',
        'Revinate\\SearchBundle\\Lib\\Search\\Mapping\\Annotations\\VersionField',
    );

    /**
     * @param string $className
     * @param ClassMetadata|\Revinate\SearchBundle\Lib\Search\Mapping\ClassMetadata $metadata
     *
     * @throws \ReflectionException
     */
    public function loadMetadataForClass($className, ClassMetadata $metadata)
    {
        $reflClass = $metadata->getReflectionClass();

        if (!$reflClass) {
            $reflClass = new \ReflectionClass((string)$className);
        }

        $reflProperties = $reflClass->getProperties();
        $reflMethods = $reflClass->getMethods();

        $this->extractClassAnnotations($reflClass, $metadata);
        $this->extractPropertiesAnnotations($reflProperties, $metadata);
        $this->extractMethodsAnnotations($reflMethods, $metadata);
    }


    /**
     * This function extracts the class annotations for search from the given reflected class and writes
     * them into metadata.
     *
     * @param \ReflectionClass $reflClass
     * @param ClassMetadata|\Revinate\SearchBundle\Lib\Search\Mapping\ClassMetadata $metadata
     *
     * @return ClassMetadata|\Revinate\SearchBundle\Lib\Search\Mapping\ClassMetadata
     *
     * @throws DriverException\ClassIsNotAValidDocumentException|DriverException\PropertyDoesNotExistsInMetadataException
     */
    private function extractClassAnnotations(\ReflectionClass $reflClass, ClassMetadata $metadata)
    {
        $documentsClassAnnotations = array();
        foreach ($this->reader->getClassAnnotations($reflClass) as $annotation) {
            foreach ($this->entityAnnotationClasses as $annotationClass => $index) {
                if ($annotation instanceof $this->entityRootAnnotationClass) {
                    $metadata->addRootMapping($annotation);
                    break;
                } elseif ($annotation instanceof $annotationClass) {
                    $documentsClassAnnotations[$index] = $annotation;
                    break;
                }
            }
        }

        if (!$documentsClassAnnotations) {
            throw new DriverException\ClassIsNotAValidDocumentException($metadata->getName());
        }

        //choose only one (the first one)
        $annotationClass = reset($documentsClassAnnotations);
        $reflClassAnnotations = new \ReflectionClass($annotationClass);
        $metadata = $this->addValuesToMetadata(
            $reflClassAnnotations->getProperties(),
            $metadata,
            $annotationClass
        );

        return $metadata;
    }

    /**
     * Extract the property annotations.
     *
     * @param \ReflectionProperty[] $reflProperties
     * @param ClassMetadata|\Revinate\SearchBundle\Lib\Search\Mapping\ClassMetadata $metadata
     *
     * @return ClassMetadata|\Revinate\SearchBundle\Lib\Search\Mapping\ClassMetadata
     */
    private function extractPropertiesAnnotations(array $reflProperties, ClassMetadata $metadata)
    {
        foreach ($reflProperties as $reflProperty) {
            foreach ($this->reader->getPropertyAnnotations($reflProperty) as $annotation) {
                foreach ($this->entityFieldAnnotationClasses as $fieldAnnotationClass) {
                    if ($annotation instanceof $fieldAnnotationClass) {
                        if ($annotation instanceof $this->entityIdAnnotationClass) {
                            $metadata->setIdentifier($reflProperty->name);
                        } elseif ($annotation instanceof $this->entityTimeSeriesFieldAnnotationClass) {
                            $metadata->setTimeSeriesField($reflProperty->name);
                        } elseif ($annotation instanceof $this->entityParamAnnotationClass) {
                            $metadata->addParameterMapping($reflProperty, $annotation);
                        } elseif ($annotation instanceof $this->entityParentFieldAnnotationClass) {
                            $metadata->setParentField($reflProperty->name);
                        } elseif ($annotation instanceof $this->entityVersionFieldAnnotationClass) {
                            /** @var Search\VersionField $annotation */
                            $metadata->setVersionField($reflProperty->name);
                            $metadata->setVersionType($annotation->versionType);
                        } else {
                            $metadata->addFieldMapping($reflProperty, $annotation);
                        }
                        continue 2;
                    }
                }
            }
        }

        $id = $metadata->getIdentifier();
        if (empty($id)) {
            throw new \Exception(__METHOD__ . ': Id field must be defined!');
        }

        if ($metadata->timeSeriesScale && !$metadata->getTimeSeriesField()) {
            throw new \Exception(__METHOD__ . ': TimeSeriesField must be defined for a time series index!');
        }

        return $metadata;
    }

    /**
     * Extract the methods annotations.
     *
     * @param \ReflectionMethod[] $reflMethods
     * @param ClassMetadata|\Revinate\SearchBundle\Lib\Search\Mapping\ClassMetadata $metadata
     *
     * @return ClassMetadata|\Revinate\SearchBundle\Lib\Search\Mapping\ClassMetadata
     */
    private function extractMethodsAnnotations(array $reflMethods, ClassMetadata $metadata)
    {
        foreach ($reflMethods as $reflMethod) {
            foreach ($this->reader->getMethodAnnotations($reflMethod) as $annotation) {
                foreach ($this->entityFieldAnnotationClasses as $fieldAnnotationClass) {
                    if ($annotation instanceof $fieldAnnotationClass) {
                        $metadata->addFieldMapping($reflMethod, $annotation);
                        continue 2;
                    }
                }
            }
        }

        return $metadata;
    }

    /**
     * @param \ReflectionProperty[] $reflectedClassProperties
     * @param ClassMetadata|\Revinate\SearchBundle\Lib\Search\Mapping\ClassMetadata $metadata
     * @param string $class
     *
     * @return ClassMetadata|\Revinate\SearchBundle\Lib\Search\Mapping\ClassMetadata
     *
     * @throws DriverException\PropertyDoesNotExistsInMetadataException
     */
    private function addValuesToMetadata(array $reflectedClassProperties, ClassMetadata $metadata, $class)
    {
        foreach ($reflectedClassProperties as $reflectedProperty) {
            $propertyName = $reflectedProperty->getName();

            if (false === property_exists($metadata, $propertyName)) {
                throw new DriverException\PropertyDoesNotExistsInMetadataException($reflectedProperty->getName());
            } else {
                if (!is_null($class->$propertyName)) {
                    $metadata->$propertyName = $class->$propertyName;
                }
            }
        }

        return $metadata;
    }
}
