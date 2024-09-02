<?php
namespace Softwarefactories\AndromedaCore\Obj;

/**
 * Define common system status exchange object
 *
 * This is the default and minimal form of a result object used in all file access systems.
 *
 * @category   Interface object
 * @package    Core
 * @author     Tobias Teichner <webmaster@teichner.biz>
 * @since      File available since v1.0.0
 **/
abstract class Result
{
    /**
     * The overall status
     *
     * @var bool
     */
    private bool $status;

    /**
     * The response message buffer
     *
     * @var array
     */
    private array $message = array();

    /**
     * The result payload
     *
     * @var array|mixed
     */
    private mixed $data = array();

    /**
     * The list of errors
     *
     * @var array
     */
    private array $errors = array();

    /**
     * secondary result values
     *
     * @var mixed
     */
    private mixed $second_data = null;

    /**
     * The sort by property name
     *
     * @var ?string
     */
    private ?string $sort_by = null;

    /**
     * Construct a new instance of the result object
     *
     * @param boolean      $status      The boolean state of the initialised status
     * @param string|array $message     The first message
     * @param array        $values      The initial result data set
     * @param array        $second_data The initial secondary result data set
     */
    public function __construct(bool $status = false, string|array $message = "", mixed $values = array(), array $second_data = array())
    {
        // construction vars
        $this->status = $status;
        $this->setMessage($message);
        $this->setValues($values);
        $this->setSecondaryValues($second_data);
    }

    /**
     * Must be implemented by host
     *
     * @param string $data The text to translate
     * @param string $l    The target language like 'de'
     * @param string $type The origin type of the translation
     * @param string $src  The origin file of the translation
     * @return string   The translated string
     */
    protected abstract function getTranslation(string $data, string $l, string $type = 'code', string $src = ''): string;

    /**
     * Set error message
     *
     * Convenience function to set message and status at once
     * Set the message and the status to false.
     * This method is chainable
     *
     * @param string|array $msg The new message
     * @param ?array       $var An associative array with variable parameters to parse into the translation
     * @return Result
     */
    public function SetErrorMessage(string|array $msg, array $var = null): Result
    {
        $this->setStatus(false);
        $this->setMessage($msg, $var);
        return $this;
    }

    /**
     * Clear messages
     */
    public function ClearMessage(): void
    {
        $this->message = [];
    }

    /**
     * Add or override the current message
     *
     * @param string|array $msg The new message
     * @param ?array       $var An associative array with variable parameters to parse into the translation
     **/
    public function setMessage(string|array $msg, array $var = null): Result
    {
        // only if a message was given write it into the buffer
        if ($msg) {
            if (is_array($msg)) {
                // merge arrays
                $this->message = array_merge($this->message, $msg);
            } else {
                // replace whitespaces
                $msg = preg_replace("/(\n\r)|(\n)|(\r)|(\t)|( {2,})/", " ", $msg);
                $msg = preg_replace("# +#", " ", $msg);
                // append the message
                $this->message[] = array('text' => $msg, 'args' => $var);
            }
        }
        return $this;
    }

    /**
     * Append entry
     *
     * This method adds the given entry to the result data
     * It converts the data storage to an array on demand
     *
     * @param mixed $entry A new entry to the result set
     * @param int   $max   The maximum count of elements
     **/
    public function AddEntry(mixed $entry, int $max = 0): Result
    {
        // create array
        if (!is_array($this->data)) {
            $this->data = array();
        }

        // add entry
        $this->data[] = $entry;

        // if max reached, remove the first element from array
        if ($max > 0) {
            if (count($this->data) > $max) {
                array_shift($this->data);
            }
        }
        return $this;
    }

    /**
     * Add associative entry
     *
     * This method adds a new entry to the result data object
     * It converts the data storage to an array on demand
     *
     * @param string $key   The assoc key
     * @param mixed  $value A new entry to the result set
     **/
    public function AddEntryAssoc(string $key, mixed $value): Result
    {
        // create array on demand
        if (is_object($this->data)) {
            $this->data->{$key} = $value;
        } else {
            if (!is_array($this->data)) {
                $this->data = array();
            }
            $this->data[$key] = $value;
        }
        return $this;
    }

    /**
     * Set new error
     *
     * Can set an array of errors or a assoc error entry
     *
     * @param string|array $key The key of the message
     * @param string       $val The Value of the message
     * @param ?array       $var An associative array with variable parameters to parse into the translation
     **/
    public function setError($key, $val = '', $var = null)
    {
        if ($val === null) {
            if ($this->errors && isset($this->errors[$key])) {
                unset($this->errors[$key]);
            }
        } else {
            // only if a message was given write it into the buffer
            if (is_array($key)) {
                foreach ($key as $k => $v) {
                    $this->errors[$k] = $v;
                }
            } else if (preg_match('/^[\w.]+$/', $key)) {
                // replace whitespaces
                if (is_array($val)) {
                    if ($var === true) {
                        foreach ($val as $k => $v) {
                            $this->errors[$key . "_" . $k] = $v;
                        }
                    } else {
                        // special case with 1 entry, may happen in case a message is assigned
                        if (count($val) == 1 && isset($val[0])) {
                            $this->errors[$key] = $val[0];
                        } else {
                            foreach ($val as $k => $v) {
                                $this->errors[$key . "_" . $k] = $v;
                            }
                        }
                    }
                } else {
                    // escape
                    $val = preg_replace("/(\n\r)|(\n)|(\r)|(\t)|( {2,})/", " ", $val);
                    $val = preg_replace("# +#", " ", $val);

                    // append the message
                    $this->errors[$key] = array('text' => $val, 'args' => $var);
                }
            }
        }
    }

    /** state count errors
     * get the numeric count of registered errors
     *
     * @return  int     The count of local errors
     **/
    public function GetErrorsCount(): int
    {
        return count(array_keys($this->errors));
    }

    /**
     * Check if this status has errors
     *
     * Returns true if this result object has errors in the buffer.
     *
     * @return  bool    Result if this object has errors
     **/
    public function HasErrors(): bool
    {
        return $this->GetErrorsCount() > 0;
    }

    /**
     * Has a certain error
     *
     * @param string $key
     * @return mixed|null
     */
    public function HasError(string $key): mixed
    {
        return ($this->errors && isset($this->errors[$key])) ? $this->errors[$key] : null;
    }

    /**
     * The amount of values
     * Gives the length of the result data set or, false
     *
     * @return bool|int
     */
    public function Count(): bool|int
    {
        return (is_array($this->data)) ? count($this->data) : false;
    }

    /**
     * Set the current data
     *
     * @param mixed $data   The new data for the values
     * @param bool  $append Add to list
     * @return Result   Return self to allow chaining
     */
    public function SetValues(mixed $data, bool $append = false): Result
    {
        if ($append) {
            if (!$this->data) {
                $this->data = $data;
            } else {
                $this->data = array_merge($this->data, $data);
            }
        } else {
            $this->data = $data;
        }
        return $this;
    }

    /**
     * Override the current data and set status true
     *
     * @param mixed $data   The new data for the values
     * @param bool  $attach Add instead of override
     * @return Result   Return self to allow chaining
     */
    public function SetValidValues(mixed $data, bool $attach = false): Result
    {
        if ($attach) {
            if (!is_array($this->data)) {
                if (!empty($this->data)) {
                    $this->data = [$this->data];
                } else {
                    $this->data = [];
                }
            }

            $this->data[] = $data;
        } else {
            $this->data = $data;
        }

        $this->status = true;
        return $this;
    }

    /**
     * Merge error result
     *
     * Merge with other error result object
     * This method can be chained
     *
     * @param Result $result The other result
     * @param bool   $full   Also merge data on demand
     * @return Result   Return self to allow chaining
     */
    public function SetResponses(Result $result, bool $full = false): Result
    {
        $this->setStatus($result->getStatus());
        $this->setMessage($result->getMessage());
        $this->setError($result->getErrors());

        // include the data also
        if ($full) {
            $this->data = $result->getValues();
            $this->second_data = $result->getSecondaryValues();
        }
        return $this;
    }

    /**
     * Add or override the current data
     *
     * @param mixed $data  The new data set or a key name
     * @param mixed $value The new value when data was a string key
     * @return Result   Return self to allow chaining
     **/
    public function SetSecondaryValues(mixed $data, mixed $value = null): Result
    {
        if ($value != null && is_string($data)) {
            if (!is_array($this->second_data)) {
                $this->second_data = array();
            }
            $this->second_data[$data] = $value;
        } else {
            $this->second_data = $data;
        }
        return $this;
    }

    /**
     * Update a single secondary values entry
     *
     * @param int|string $key
     * @param mixed      $data
     * @return Result   Return self to allow chaining
     */
    public function SetSecondaryValue(int|string $key, mixed $data): Result
    {
        if (!is_array($this->second_data)) {
            $this->second_data = array();
        }
        if ($key == -1) {
            $this->second_data[] = $data;
        } else {
            $this->second_data[$key] = $data;
        }
        return $this;
    }

    /**
     * Get the data by key
     *
     * Get a node of the plain buffered values. Supports array and object values
     *
     * @param int|string $key A certain value part to return
     * @return  mixed       The property or value when inside the storage, otherwise null
     **/
    public function getValue(int|string $key): mixed
    {
        if (is_array($this->data) && isset($this->data[$key])) {
            return $this->data[$key];
        } else if (is_object($this->data) && $this->data && isset($this->data->{$key})) {
            return $this->data->{$key};
        } else {
            return null;
        }
    }

    /**
     * Get secondary value
     *
     * @param int|string $key
     * @return mixed|null
     */
    public function getSecondaryValue(int|string $key): mixed
    {
        if (is_array($this->second_data) && isset($this->second_data[$key])) {
            return $this->second_data[$key];
        } else if ($this->second_data && isset($this->second_data->{$key})) {
            return $this->second_data->{$key};
        } else {
            return null;
        }
    }

    /**
     * Get the data sorted by
     *
     * Get the plain buffered value
     *
     * @param string $sort_by Set to certain existing key to enable sorted response or false
     * @return  mixed       The property or value when inside the storage, otherwise null
     **/
    public function getValues(string $sort_by = ''): mixed
    {
        // sort the assoc result if possible and required
        if ($sort_by && is_array($this->data)) {
            $this->sort_by = $sort_by;
            usort($this->data, array($this, '_sort'));
        }
        return $this->data;
    }

    /**
     * The sort helper for list of objects or arrays
     *
     * @param array|object $a
     * @param array|object $b
     * @return int
     */
    public function _sort(array|object $a, array|object $b): int
    {
        if (is_array($a)) {
            return ($a[$this->sort_by] == $b[$this->sort_by]) ? 0 : (($a[$this->sort_by] < $b[$this->sort_by]) ? -1 : 1);
        } else {
            return ($a->{$this->sort_by} == $b->{$this->sort_by}) ? 0 : (($a->{$this->sort_by} < $b->{$this->sort_by}) ? -1 : 1);
        }
    }

    /** get the data
     * get all the data as assoc array
     *
     * @param string|int $format     The result format type
     * @param string     $l          The result language
     * @param bool       $empty_null When true, empty values will be skipped
     * @return  array       The associative representation of this object
     */
    public function GetAssoc(string|int $format = FAA_RESULT_TYPE_PLAIN, string $l = '', bool $empty_null = false): array
    {
        $res = array(
            'msg' => $this->getMessage($format, $l),
            'status' => $this->getStatus(),
            'errors' => $this->getErrors($format, $l),
            'errors_count' => $this->getErrorsCount(),
            'values' => $this->getValues(),
            'secondary' => $this->getSecondaryValues()
        );

        if ($empty_null) {
            if (is_countable($res['secondary']) && !count($res['secondary'])) {
                unset($res['secondary']);
            }
            if (is_countable($res['errors']) && !count($res['errors'])) {
                unset($res['errors']);
            }
        }

        return $res;
    }

    /** get the data
     * get the whole secondary data set
     **/
    public function getSecondaryValues()
    {
        return $this->second_data;
    }

    /**
     * add or override the current status
     * This method can be chained
     *
     * @param bool $status The new status
     **/
    public function setStatus(bool $status): Result
    {
        $this->status = ($status === true) ? true : $status;
        return $this;
    }

    /**
     * Get current status
     *
     * @return bool
     */
    public function GetStatus(): bool
    {
        return $this->status;
    }

    /** get the error messages
     * Translate errors or get plain data.
     *
     * @param string|int $format The result format type
     * @param string     $l      The result language
     * @return  array|string    Either the plain set of errors or a translated message
     */
    public function getErrors(string|int $format = FAA_RESULT_TYPE_PLAIN, string $l = ''): array|string
    {
        if ($format === FAA_RESULT_TYPE_PLAIN) {
            return $this->errors;
        } else if ($format == FAA_RESULT_TYPE_LIST) {
            // get as formatted ol list
            $msg = '<ol>';
            foreach ($this->errors as $key => $val) {
                $msg .= '<li>' . $this->map($val, $l) . '</li>';
            }
            $msg .= '</ol>';
            return $msg;
        } else if ($format == FAA_RESULT_TYPE_LIST_UL) {
            // get as formatted ol list
            $msg = '<ul>';
            foreach ($this->errors as $key => $val) {
                $msg .= '<li>' . $this->map($val, $l) . '</li>';
            }
            $msg .= '</ul>';
            return $msg;
        } else if ($format == FAA_RESULT_TYPE_ADVANCED) {
            // get as ordered list and do not sript tags
            $msg = '<ol>';
            foreach ($this->errors as $key => $val) {
                $msg .= '<li><dl><dt>' . $key . '</dt><dd>' . $this->map($val, $l) . '</dd></dl></li>';
            }
            $msg .= '</ol>';
            return $msg;
        } else if ($format == FAA_RESULT_TYPE_TXT) {
            // get as plain text with line breaks
            $msg = '';
            foreach ($this->errors as $key => $val) {
                if ($val) {
                    $msg .= $this->map($val, $l) . "\n";
                }
            }
            return $msg;
        } else if ($format == FAA_RESULT_TYPE_INDEXED) {
            // get as indexed array
            return array_values($this->errors);
        } else if ($format == FAA_RESULT_TYPE_TRANSLATED) {
            // get as formatted ol list
            $res = $this->errors;
            foreach ($res as $key => $val) {
                $res[$key] = $this->map($val, $l);
            }
            return $res;
        } else {
            return $this->errors;
        }
    }

    /**
     * Get the messages
     * Translate errors or get plain data.
     *
     * @param int     $format     The result format type
     * @param ?string $l          The result language
     * @param bool    $allow_html Allow html in values
     * @return  array|string    Either the plain set of errors or a translated message
     */
    public function getMessage(int $format = FAA_RESULT_TYPE_PLAIN, ?string $l = null, bool $allow_html = false): array|string
    {
        $msg = '';
        if ($format == FAA_RESULT_TYPE_TXT) {
            // get as plaintext
            for ($i = 0; $i < count($this->message); $i++) {
                $msg .= $this->map($this->message[$i], $l, $allow_html) . "\n";
            }
        } else if ($format == FAA_RESULT_TYPE_LIST) {
            // get as formatted ol list
            $msg = '<ol>';
            for ($i = 0; $i < count($this->message); $i++) {
                $msg .= '<li>' . $this->map($this->message[$i], $l, $allow_html) . '</li>';
            }
            $msg .= '</ol>';
        } else if ($format == FAA_RESULT_TYPE_ADVANCED) {
            // get as formatted ol list and do not script tags
            $msg = '<ol>';
            for ($i = 0; $i < count($this->message); $i++) {
                $msg .= '<li>' . $this->map($this->message[$i], $l, $allow_html) . '</li>';
            }
            $msg .= '</ol>';
        } else if ($format == FAA_RESULT_TYPE_PLAIN) {
            // get plain
            $msg = $this->message;
        } else if ($format == FAA_RESULT_TYPE_TRANSLATED) {
            // get as formatted ol list
            $res = $this->message;
            foreach ($res as $key => $val) {
                $res[$key] = $this->map($val, $l, $allow_html);
            }
            return $res;
        } else {
            $msg = 'Wrong format requested';
        }
        return $msg;
    }

    /**
     * map and translate data
     * create a readable result string from a given set of result templates
     *
     * @param array   $assoc A combined text with arguments to map
     * @param ?string $l     The target translation language code or false in plain mode
     * @return  string      The mapped translated template with parsed args
     **/
    private function map(array $assoc, ?string $l = null, bool $allow_html = false): string
    {
        // map and translate errors on demand
        if ($l) {
            $assoc['text'] = $this->getTranslation($assoc['text'], $l);
        }

        // map the arguments
        $text = $assoc['text'];
        if (isset($assoc['args']) && is_array($assoc['args'])) {
            foreach ($assoc['args'] as $key => $val) {
                if (strpos($key, ':')) {
                    $kf = explode(':', $key);
                    $key = $kf[0];
                    switch ($kf[1]) {
                        case 'price':
                        {
                            $val = (is_numeric($val)) ? number_format($val * 1, 2, ',', '.') : 'NaN';
                            break;
                        }
                    }
                }

                // map data but only in encoded form
                $text = str_replace($key, $allow_html ? $val : htmlentities($val, ENT_QUOTES, 'UTF-8', false), $text);
            }
        }

        return $text;
    }
}
