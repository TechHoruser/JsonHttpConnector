<?php

namespace mortalswat\JsonHttpConnector;

/**
 * Class JsonHttpResponse
 * @package mortalswat\JsonHttpConnector
 */
class JsonHttpResponse
{
    /** @var int */
    private $code;
    /** @var array */
    private $content;

    /**
     * JsonHttpResponse constructor.
     * @param array $content
     * @param int $code
     */
    public function __construct(array $content, $code = 0)
    {
        $this->code = $code;
        $this->content = $content;
    }

    /**
     * @return int
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @return array
     */
    public function getContent()
    {
        return $this->content;
    }
}