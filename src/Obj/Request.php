<?php
namespace Softwarefactories\AndromedaCore\Obj;
use Softwarefactories\AndromedaCore\Int\IHandler;

class Request
{
    /**
     * The http request method
     *
     * @var ?string
     */
    protected ?string $method;

    /**
     * Raw request path
     *
     * @var ?string
     */
    protected ?string $uri;

    /**
     * This endpoint is protected
     *
     * @var bool
     */
    protected ?bool $protected = null;

    /**
     * The handler instance
     *
     * @var IHandler|null
     */
    protected ?IHandler $handler = null;

    /**
     * Name of the handler function
     *
     * @var string
     */
    protected string $function;

    /**
     * The path segments
     *
     * @var array
     */
    protected array $segments = array();

    /**
     * Map with endpoints
     *
     * @var array
     */
    protected array $map;

    /**
     * @param array  $map
     * @param string $method
     * @param string $uri
     */
    public function __construct(array $map, string $method, string $uri)
    {
        // buffer the data
        $this->segments = explode('/', $uri);
        $this->map = $map;
        $this->method = $method;
        $this->uri = $uri;
    }

    /**
     * Getter for protected state
     *
     * @return ?bool
     */
    public function IsProtected(): ?bool
    {
        return $this->protected;
    }

    /**
     * The segment by index or, null
     *
     * @param int $index The segment index
     * @return array|string|null
     */
    public function Segment(int $index = -1): array|string|null
    {
        if ($index == -1) {
            return $this->segments;
        }
        return (isset($this->segments[$index])) ? $this->segments[$index] : null;
    }

    /**
     * The callback method name
     *
     * @return string
     */
    public function CallBack(): string
    {
        return $this->function;
    }

    /**
     * @return IHandler|null
     */
    public function Handler(): ?IHandler
    {
        return $this->handler;
    }
}
