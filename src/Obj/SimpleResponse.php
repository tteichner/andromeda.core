<?php
namespace Softwarefactories\AndromedaCore\Obj;

/**
 * Simple response interface
 */
class SimpleResponse
{
    /**
     * @var string
     */
    public string $type = 'text/plain';

    /**
     * The response message / content or file path
     *
     * @var string
     */
    public string $msg = '';

    /**
     * Is a file response?
     *
     * @var bool
     */
    public bool $isFile = false;

    /**
     * the http status code
     *
     * @var int|mixed
     */
    public int $code = -1;

    /**
     * Construct with initial code
     *
     * @param int  $code
     */
    public function __construct(int $code = 0)
    {
        $this->code = $code;
    }
}
