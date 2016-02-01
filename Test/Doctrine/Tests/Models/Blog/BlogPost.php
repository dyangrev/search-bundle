<?php

namespace Doctrine\Tests\Models\Blog;

use Revinate\SearchBundle\Lib\Search\Mapping\Annotations as SEARCH;

/**
 * @SEARCH\ElasticSearchable(index="blog", type="post", numberOfShards=1, numberOfReplicas=1)
 *
 */
class BlogPost
{
    const CLASSNAME = __CLASS__;

    public $id;

    /**
     * @SEARCH\Field(boost=2.0)
     */
    public $name;

    /**
     * @SEARCH\Field(boost=1.0)
     */
    public $title;

    public function __construct($name = null)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }
}
