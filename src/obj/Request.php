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
     * @var null
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

        // try to find matching handler
        if ($this->map && is_array($this->map) && !empty($this->map)) {
            foreach($this->map as $ep) {
                if ($ep->method == $method) {
                    if (preg_match($ep->regex, $uri)) {

                        if (in_array($ep->handler, ['Base', 'Derivative', 'Asset', 'FileParser'])) {
                            $this->function = $ep->function;
                            $this->protected = $ep->protected;
                            if ($ep->handler === 'Base') {
                                $this->handler = new Base($this);
                            } else if ($ep->handler === 'Derivative') {
                                $this->handler = new Derivative($this);
                            } else if ($ep->handler === 'Asset') {
                                $this->handler = new Asset($this);
                            } else if ($ep->handler === 'FileParser') {
                                $this->handler = new FileParser($this);
                            }
                        } else {
                            error_log('ERROR Class not found FAA\\Handler\\' . $ep->handler);
                        }
                    }
                }
            }
        }
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
     * @param int $index        The segment index
     * @return string[]|string
     */
    public function Segment(int $index = -1)
    {
        if ($index == -1) {
            return $this->segments;
        }
        return (isset($this->segments[$index])) ? $this->segments[$index] : null;
    }

    /**
     * The callback method name
     *
     * @return null
     */
    public function CallBack()
    {
        return $this->function;
    }

    /**
     * @return IHandler|null
     */
    public function Handler()
    {
        return $this->handler;
    }

    /**
     * The host
     *
     * @return string|null
     */
    public function Host()
    {
        return (preg_match('/^[a-z\-]+$/', $this->Segment(2))) ? $this->Segment(2) : null;
    }

    /**
     * The asset id
     *
     * @return string|null
     */
    public function Asset()
    {
        return (preg_match('/^[a-z0-9\-]+$/', $this->Segment(4))) ? $this->Segment(4) : null;
    }
}
