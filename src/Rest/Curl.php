<?php
namespace Softwarefactories\AndromedaCore\Rest;
use Softwarefactories\AndromedaCore\Obj\Result;

/**
 * Implement curl request
 *
 * This class provides general interfaces to handle curl requests.
 *
 * @category   Helper
 * @package    Softwarefactories\AndromedaCore
 * @author     Tobias Teichner <webmaster@teichner.biz>
 * @since      File available since v1.6.6
 **/
class Curl
{
    /**
     * @var string|null
     */
    protected ?string $host;

    /**
     * @var string|null
     */
    protected ?string $url;

    /**
     * @var string
     */
    protected string $method;

    /**
     * @var bool
     */
    protected bool $debug = false;

    /**
     * The cur handler buffer
     *
     * @var
     */
    private $ch;

    /**
     * Additional headers
     *
     * @var array
     */
    private array $header = array();

    /**
     * The request body
     *
     * @var mixed
     */
    private mixed $body = null;

    /**
     * @var Result
     */
    private Result $result;

    /**
     * Curl constructor.
     *
     * @param string       $method  The request method
     * @param string|null  $host    The full hostname with port and protocol
     * @param string|null  $url     The relative url to call
     */
    public function __construct(Result $result, string $method = 'GET', string $host = null, string $url = null)
    {
        $this->url = $url;
        $this->host = $host;
        $this->method = $method;
        $this->result = $result;
    }

    /**
     * Expose the sent body
     *
     * @return null|string
     */
    public function Body()
    {
        return $this->body;
    }

    /**
     * Set custom header to the request
     *
     * @param array  $headers  List of headers
     */
    public function AddHeaders(array $headers): void
    {
        if (count($this->header)) {
            $this->header = array_merge($this->header, $headers);
        } else {
            $this->header = $headers;
        }
    }

    /**
     * Enable/disable debug mode
     *
     * @param mixed $val
     */
    public function SetDebug(mixed $val): void
    {
        $this->debug = (bool)$val;
    }

    /**
     * Set certificate
     * Set the request certificate file for authentication.
     *
     * @param string  $cert      Absolute path to target certificate. Must be combined .pem
     * @param string  $password  The certificate password when defined
     * @param string  $ca_cert   The ca root certificate when defined
     */
    public function SetCertificate(string $cert, string $password='', string $ca_cert=''): void
    {
        $this->init();
        curl_setopt($this->ch, CURLOPT_SSLCERT, $cert);

        // add password on demand
        if ($password) {
            curl_setopt($this->ch, CURLOPT_SSLCERTPASSWD, $password);
        }

        if ($ca_cert) {
            curl_setopt($this->ch, CURLOPT_CAINFO, $ca_cert);
        }
    }

    /**
     * Set http basic auth credentials
     *
     * @param $username
     * @param $password
     */
    public function SetBasicAuth($username, $password): void
    {
        $this->init();
        curl_setopt($this->ch, CURLOPT_USERPWD, $username . ":" . $password);
    }

    /**
     * Download a file to path
     *
     * @param string  $file_path  Absolute path to target file (must be writable)
     * @return  Result      A system result object
     */
    public function Download(string $file_path): Result
    {
        $this->init();
        $fp = fopen($file_path, 'w+');

        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_TIMEOUT, 50);
        curl_setopt($this->ch, CURLOPT_FILE, $fp);
        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true);

        $res = $this->exec();
        fclose($fp);
        if ($res->getStatus() && filesize($file_path) > 0) {
            $res->SetValidValues($file_path);
        } else {
            unlink($file_path);
            $res->SetErrorMessage('system.msg.file_not_written', array('{path}' => $file_path));
        }

        return $res;
    }

    /**
     * Upload the given file
     *
     * Does a file put/post request depending on the set method and loads the file to the target
     *
     * @param string  $file_path  Absolute path to target file (must be readable)
     * @return  Result      A system result object
     */
    public function Upload(string $file_path, string $arg_name = 'file_contents'): Result
    {
        $this->init();
        if (function_exists('curl_file_create')) { // php 5.5+
            $cFile = curl_file_create($file_path);
        } else {
            $cFile = '@' . realpath($file_path);
        }
        curl_setopt($this->ch, CURLOPT_POST, true);
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, array($arg_name => $cFile));

        return $this->exec();
    }

    /**
     * Submit call
     *
     * Send the request to the remote endpoint
     *
     * @param   null|object|array     $obj        The Request payload
     * @param   bool         $json       When true, send as json
     * @return  Result          A system result object
     *                  The regular values will contain the response body, when json than parsed
     *                  The secondary values may contain the http response code
     */
    public function Send($obj = null, bool $json = true): Result
    {
        $this->init();

        // check if we have a payload
        if ($obj) {
            if ($json) {
                if ($res = json_encode($obj, JSON_UNESCAPED_SLASHES)) {
                    $this->body = $res;
                    if ($this->method == 'POST' && count($this->header)) {
                        curl_setopt($this->ch, CURLOPT_POST, true);
                        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $this->body);
                        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $this->header);
                    } else {
                        $this->header = array(
                            'Content-Type: application/json',
                            'Content-Length: ' . strlen($this->body)
                        );
                        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, $this->method);
                        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $this->header);
                        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $this->body);
                    }
                } else {
                    $this->result->SetErrorMessage('system.exception', ['{msg}' => json_last_error()]);
                    return $this->result;
                }
            } else {
                if (count($this->header)) {
                    curl_setopt($this->ch, CURLOPT_HTTPHEADER, $this->header);
                }
                if ($this->method == 'POST') {
                    $this->body = http_build_query($obj);
                    curl_setopt($this->ch, CURLOPT_POST, true);
                    curl_setopt($this->ch, CURLOPT_POSTFIELDS, $this->body);
                } else if ($this->method == 'GET' && !empty($obj)) {
                    // Add as query string
                    curl_setopt($this->ch, CURLOPT_URL, $this->host . $this->url . '?=' . http_build_query($obj));
                } else {
                    curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, $this->method);
                }
            }
        } else {
            if (count($this->header)) {
                curl_setopt($this->ch, CURLOPT_HTTPHEADER, $this->header);
            }
            curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, $this->method);
        }

        return $this->exec();
    }

    /**
     * Execute the call to remote source
     *
     * @return Result
     */
    private function exec(): Result
    {
        $verbose = null;
        if ($this->debug) {
            curl_setopt($this->ch, CURLOPT_VERBOSE, true);
            $verbose = fopen('php://temp', 'w+');
            curl_setopt($this->ch, CURLOPT_STDERR, $verbose);
        }

        // execute the command
        $this->result->setStatus(true);
        $result = curl_exec($this->ch);

        // debug log, curl error
        if (curl_errno($this->ch)) {
            // MSG: Request to {url} failed with internal code: {resp}
            $this->result->SetErrorMessage('system.lib.curl.error_msg', array('{resp}' => curl_error($this->ch), '{url}' => $this->host . $this->url));
        } else {
            $http = (int)curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
            if ($http < 200 || $http > 299) {
                // MSG: Request to {url} failed with code: {http_code}
                $this->result->SetErrorMessage('system.lib.curl.error', array('{code}' => $http, '{url}' => $this->host . $this->url));
                $this->result->SetErrorMessage('system.exception', array('{msg}' => $result));

                // fat::("system.fetch_id_error")::fat
                $this->result->setValues($result);
            } else {
                // check if this is a json content type
                $contentType = curl_getinfo($this->ch, CURLINFO_CONTENT_TYPE);

                // Decode the json response
                if (strpos($contentType, 'text/json') !== false || strpos($contentType, 'application/json') !== false) {
                    if ($dec = json_decode($result, true)) {
                        $this->result->SetValidValues($dec);
                    } else {
                        $this->result->SetErrorMessage('system.exception', ['{msg}' => json_last_error()]);
                    }
                } else {
                    $this->result->setValues($result);
                }
            }
            $this->result->SetSecondaryValues($http);
        }

        if ($verbose) {
            rewind($verbose);
            $verboseLog = stream_get_contents($verbose);
            error_log($verboseLog);
            error_log($this->body);
            fclose($verbose);
        }

        curl_close($this->ch);
        $this->ch = null;
        return $this->result;
    }

    private function init()
    {
        // Set the basics
        if (!$this->ch) {
            $this->ch = curl_init();
            curl_setopt($this->ch, CURLOPT_USERAGENT, 'FileAccessAndromeda/3.20.16');
            curl_setopt($this->ch, CURLOPT_VERBOSE, false);
            curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true);
        }

        // Set the url
        curl_setopt($this->ch, CURLOPT_URL, $this->host . $this->url);
    }
}
