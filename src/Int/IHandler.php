<?php
namespace Softwarefactories\AndromedaCore\Int;
use Softwarefactories\AndromedaCore\Obj\{SimpleResponse, Request};

interface IHandler
{
    /**
     * @param Request $request
     */
    public function __construct(Request $request);

    /**
     * @return string
     */
    public function Name(): string;

    /**
     * @return SimpleResponse
     */
    public function Execute() : SimpleResponse;
}
