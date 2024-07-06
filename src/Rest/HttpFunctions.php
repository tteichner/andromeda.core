<?php
namespace Softwarefactories\AndromedaCore\Rest;
use OutCompute\PHPInfo\PHPInfo;

/**
 * Define common request helper functions
 *
 * @category   HelperClass
 * @package    Core
 * @author     Tobias Teichner <webmaster@teichner.biz>
 * @since      File available since v2.18.0
 **/
class HttpFunctions
{
    /**
     * Get php info
     *
     * @return array
     */
    public static function info(): array
    {
        $out_file = sys_get_temp_dir() . '/.phpinfo.log';
        if (file_put_contents($out_file, '') !== false) {
            if (self::CallExecutable('php -i', '/tmp', $out_file)) {
                try {
                    $phpInfo = new PHPInfo();
                    $phpInfo->setText(file_get_contents($out_file));
                    return $phpInfo->get();
                } catch (\Exception $e) {
                    return [
                        'PHP Version' => 'unknown',
                        'error' => $e->getMessage()
                    ];
                }
            }
        }

        return [
            'PHP Version' => 'unknown'
        ];
    }

    /**
     * extract file extension
     *
     * @param  $path   string  A complete file path
     * @return string  The file extension
     **/
    public static function ExtractExtension(string $path): string
    {
        return strtolower(preg_replace('/^.*\.(\w+)$/', '$1', $path));
    }

    /** delete a full folder
     * this method is the recursion helper for DropFolder()
     *
     * @param   $folder string      the path to delete
     * @param   $full   boolean     also remove the root folder
     * @return  boolean The delete status
     **/
    public static function DropFolderRecurse($folder, $full = true)
    {
        if (!is_dir($folder)) {
            return false;
        }

        // add / on demand
        if (substr($folder, strlen($folder) - 1, 1) != '/') {
            $folder .= '/';
        }

        // delete recursively
        if ($dp = opendir($folder)) {
            while (($file = readdir($dp)) !== false) {
                if ($file != "." && $file != "..") {
                    if (is_dir($folder . $file)) {
                        if (!self::DropFolderRecurse($folder . $file . '/')) {
                            return false;
                        } else {
                            !is_dir($folder . $file) || rmdir($folder . $file);
                        }
                    } else if (is_file($folder . $file) || is_link($folder . $file)) {
                        unlink($folder . $file);
                    }
                }
            }
            closedir($dp);
        }

        // delete folder on demand
        if ($full) {
            if (rmdir($folder)) {
                return true;
            } else {
                return false;
            }
        } else {
            return true;
        }
    }

    /**
     * Get mime type of file
     *
     * @param string $file The absolute file path
     * @return  string      The mime type of the file
     */
    public static function MimeType(string $file): string
    {
        if (self::ExtractExtension($file) == 'js') {
            $mime_type = 'application/javascript';
        } else if (self::ExtractExtension($file) == 'css') {
            $mime_type = 'text/css';
        } else {
            $info = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($info, $file);
        }
        return $mime_type;
    }

    /**
     * Get the auth header
     *
     * @return array|bool
     */
    public static function GetHeaderAuth()
    {
        $header = self::GetAllHeaders();
        if ($header['Authorization']) {
            // parse
            $authinfo = explode(':', base64_decode(preg_replace('/^Basic /', '', $header['Authorization'])));
            if (count($authinfo) == 2) {
                return array(
                    'login' => $authinfo[0],
                    'password' => $authinfo[1]
                );
            } else {
                // access denied
                return false;
            }
        } else {
            // access denied
            return false;
        }
    }

    /**
     * Define get all headers like provided in apache
     * Example from https://www.popmartian.com/tipsntricks/2015/07/14/howto-use-php-getallheaders-under-fastcgi-php-fpm-nginx-etc/
     *
     * @return array
     */
    public static function GetAllHeaders()
    {
        if (!function_exists('getallheaders')) {
            $headers = [];
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }
            return $headers;
        } else {
            return getallheaders();
        }
    }

    /** Execute a system command
     * Uses installed executable to perform a system call
     *
     * @param   $cmd        string  the command to execute
     * @param   $folder     string  The working directory
     * @param   $outfile    string  optional output file of the call result
     * @return  bool        The result code
     **/
    public static function CallExecutable($cmd, $folder, $outfile = '/var/log/php-fpm.log')
    {
        // setup custom env vars
        $env = array('PATH' => '/usr/local/bin:/usr/bin:/bin', 'HOME' => '/var/www');

        // setup process
        $descriptors = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('file', $outfile, 'a')
        );
        $process = proc_open($cmd, $descriptors, $pipes, $folder, $env);

        // test if started
        if (is_resource($process)) {
            usleep(500);

            // close the pipes and get return status code
            fclose($pipes[0]);

            // Get the response contents
            $cnt = stream_get_contents($pipes[1]);
            if ($cnt && isset($cnt[0])) {
                $dat = "\nOutput of '.$cmd.' start:\n#########################################\n" .
                    $cnt .
                    "\n#########################################\nOutput of '.$cmd.' end";
                file_put_contents($outfile, $dat, FILE_APPEND);
            }

            // close error pipe and process data further
            fclose($pipes[1]);
            usleep(500);
            $return_value = proc_close($process);
            usleep(500);

            // check the result code and the return values from executor stdout
            return $return_value == 0;
        }
        return false;
    }

    /**
     * Get request body content
     *
     * This method provides reading access payload of the request
     * It can decode json and also handle zipped content
     *
     * @param   $parsed                 boolean     When set to true, return as parsed json payload
     * @return  object|array|null    The payload as string or a system result object
     **/
    public static function GetBody($parsed = true)
    {
        // parse only for PUT and POST requests
        if (strtoupper($_SERVER['REQUEST_METHOD']) !== 'OPTIONS' && strtoupper($_SERVER['REQUEST_METHOD']) !== 'GET') {
            $json = file_get_contents('php://input');
            if (isset($_SERVER['HTTP_CONTENT_ENCODING']) && $_SERVER['HTTP_CONTENT_ENCODING'] == 'gzip') {
                // check if native decode is available
                if (function_exists('gzdecode')) {
                    $json = gzdecode($json);
                } else {
                    // add to temp file
                    $p = FAA_PATHS_TMPABS . md5(microtime()) . '.json';
                    if (file_put_contents($p . '.gz', $json)) {
                        // unzip temp file
                        system('gunzip ' . escapeshellarg($p . '.gz') . ', ' . escapeshellarg($p), $return);
                        if ($return == 0) {
                            // parse data
                            $json = file_get_contents($p);
                        } else {
                            error_log('Failed to gz deflate inbound payload!');
                        }

                        // cleanup temp files
                        unlink($p);
                        unlink($p . '.gz');
                    }
                }
            }

            // return as result object
            if ($parsed) {
                $body = json_decode($json, true);
            } else {
                $body = $json;
            }
        } else {
            $body = null;
        }

        return $body;
    }

    /** set http response code header
     * set a basic http response code, wrap function http_response_code() if it is not existing
     *
     * @param  $code (integer)       The http response code
     **/
    public static function SetResponseCode($code)
    {
        if (!function_exists('http_response_code')) {
            switch ($code) {
                case 100:
                    $text = 'Continue';
                    break;
                case 101:
                    $text = 'Switching Protocols';
                    break;
                case 200:
                    $text = 'OK';
                    break;
                case 201:
                    $text = 'Created';
                    break;
                case 202:
                    $text = 'Accepted';
                    break;
                case 203:
                    $text = 'Non-Authoritative Information';
                    break;
                case 204:
                    $text = 'No Content';
                    break;
                case 205:
                    $text = 'Reset Content';
                    break;
                case 206:
                    $text = 'Partial Content';
                    break;
                case 300:
                    $text = 'Multiple Choices';
                    break;
                case 301:
                    $text = 'Moved Permanently';
                    break;
                case 302:
                    $text = 'Moved Temporarily';
                    break;
                case 303:
                    $text = 'See Other';
                    break;
                case 304:
                    $text = 'Not Modified';
                    break;
                case 305:
                    $text = 'Use Proxy';
                    break;
                case 400:
                    $text = 'Bad Request';
                    break;
                case 401:
                    $text = 'Unauthorized';
                    break;
                case 402:
                    $text = 'Payment Required';
                    break;
                case 403:
                    $text = 'Forbidden';
                    break;
                case 404:
                    $text = 'Not Found';
                    break;
                case 405:
                    $text = 'Method Not Allowed';
                    break;
                case 406:
                    $text = 'Not Acceptable';
                    break;
                case 407:
                    $text = 'Proxy Authentication Required';
                    break;
                case 408:
                    $text = 'Request Time-out';
                    break;
                case 409:
                    $text = 'Conflict';
                    break;
                case 410:
                    $text = 'Gone';
                    break;
                case 411:
                    $text = 'Length Required';
                    break;
                case 412:
                    $text = 'Precondition Failed';
                    break;
                case 413:
                    $text = 'Request Entity Too Large';
                    break;
                case 414:
                    $text = 'Request-URI Too Large';
                    break;
                case 415:
                    $text = 'Unsupported Media Type';
                    break;
                case 500:
                    $text = 'Internal Server Error';
                    break;
                case 501:
                    $text = 'Not Implemented';
                    break;
                case 502:
                    $text = 'Bad Gateway';
                    break;
                case 503:
                    $text = 'Service Unavailable';
                    break;
                case 504:
                    $text = 'Gateway Time-out';
                    break;
                case 505:
                    $text = 'HTTP Version not supported';
                    break;
                default:
                    $text = 'Unknown http status code "' . htmlentities($code) . '"';
                    $code = 500;
                    break;
            }
            $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
            header($protocol . ' ' . $code . ' ' . $text);
        } else {
            http_response_code($code);
        }
    }
}
