<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Exceptions;

use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\Debug\Exception\OutOfMemoryException;
use Symfony\Component\HttpKernel\Debug\FileLinkFormatter;
use Symfony\Component\Debug\ExceptionHandler as SymfonyExceptionHandler;
/**
 * ExceptionHandler converts an exception to a Response object.
 *
 * It is mostly useful in debug mode to replace the default PHP/XDebug
 * output with something prettier and more useful.
 *
 * As this class is mainly used during Kernel boot, where nothing is yet
 * available, the Response content is always HTML.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ExceptionHandlerJson extends SymfonyExceptionHandler
{
    private $debug;
    private $charset;
    private $handler;
    private $caughtBuffer;
    private $caughtLength;
    private $fileLinkFormat;

    public function __construct($debug = true, $charset = null, $fileLinkFormat = null)
    {
        $this->debug = $debug;
        $this->charset = $charset ?: ini_get('default_charset') ?: 'UTF-8';
        $this->fileLinkFormat = $fileLinkFormat ?: ini_get('xdebug.file_link_format') ?: get_cfg_var('xdebug.file_link_format');
    }

    /**
     * Sends a response for the given Exception.
     *
     * To be as fail-safe as possible, the exception is first handled
     * by our simple exception handler, then by the user exception handler.
     * The latter takes precedence and any output from the former is cancelled,
     * if and only if nothing bad happens in this handling path.
     */
    /*
    public function handle(\Exception $exception)
    {
        if (null === $this->handler || $exception instanceof OutOfMemoryException) {
            $this->sendPhpResponse($exception);

            return;
        }

        $caughtLength = $this->caughtLength = 0;

        ob_start(function ($buffer) {
            $this->caughtBuffer = $buffer;

            return '';
        });

        $this->sendPhpResponse($exception);
        while (null === $this->caughtBuffer && ob_end_flush()) {
            // Empty loop, everything is in the condition
        }
        if (isset($this->caughtBuffer[0])) {
            ob_start(function ($buffer) {
                if ($this->caughtLength) {
                    // use substr_replace() instead of substr() for mbstring overloading resistance
                    $cleanBuffer = substr_replace($buffer, '', 0, $this->caughtLength);
                    if (isset($cleanBuffer[0])) {
                        $buffer = $cleanBuffer;
                    }
                }

                return $buffer;
            });

            echo $this->caughtBuffer;
            $caughtLength = ob_get_length();
        }
        $this->caughtBuffer = null;

        try {
            call_user_func($this->handler, $exception);
            $this->caughtLength = $caughtLength;
        } catch (\Exception $e) {
            if (!$caughtLength) {
                // All handlers failed. Let PHP handle that now.
                throw $exception;
            }
        }
    }
    */
    /**
     * Sends the error associated with the given Exception as a plain PHP response.
     *
     * This method uses plain PHP functions like header() and echo to output
     * the response.
     *
     * @param \Exception|FlattenException $exception An \Exception or FlattenException instance
     */
    /*
    public function sendPhpResponse($exception)
    {
        if (!$exception instanceof FlattenException) {
            $exception = FlattenException::create($exception);
        }

        if (!headers_sent()) {
            header(sprintf('HTTP/1.0 %s', $exception->getStatusCode()));
            foreach ($exception->getHeaders() as $name => $value) {
                header($name.': '.$value, false);
            }
            header('Content-Type: application/json; charset='.$this->charset);
        }

        echo $this->decorate($this->getContent($exception));
    }
    */

    /**
     * Gets the full HTML content associated with the given exception.
     *
     * @param \Exception|FlattenException $exception An \Exception or FlattenException instance
     *
     * @return string The HTML content as a string
     */
    public function getJson($exception)
    {
        if (!$exception instanceof FlattenException) {
            $exception = FlattenException::create($exception);
        }

        return $this->decorate($this->getContent($exception));
    }

    /**
     * Gets the Array content associated with the given exception.
     *
     * @param FlattenException $exception A FlattenException instance
     *
     * @return array The content as a array
     */
    public function getContent(FlattenException $exception)
    {
        switch ($exception->getStatusCode()) {
            case 404:
                $title = 'Sorry, the page you are looking for could not be found.';
                break;
            default:
                $title = 'Whoops, looks like something went wrong.';
        }

        $content = [];
        if ($this->debug) {
            try {
                $count = count($exception->getAllPrevious());
                $total = $count + 1;
                foreach ($exception->toArray() as $position => $e) {
                    $ind = $count - $position + 1;
                    $class = $this->formatClass($e['class']);
                    $json = json_decode($e['message'], true);
                    $message = empty($json) ? nl2br($this->escapeHtml($e['message'])) : $json;

                    $content_item = [
                        'exception' => sprintf('(%d/%d) %s', $ind, $total, $class),
                        'message' => $message
                    ];

                    foreach ($e['trace'] as $trace) {
                        $backtrace['source'] = '';
                        if ($trace['function']) {
                            $backtrace['source'] = sprintf('at %s%s%s(%s) ',
                                $this->formatClass($trace['class']),
                                $trace['type'],
                                $trace['function'],
                                $this->formatArgs($trace['args']));

                            $backtrace['class'] = $trace['class'];
                        }
                        if (isset($trace['file']) && isset($trace['line'])) {
                            $backtrace['source'] .= $this->formatPath($trace['file'], $trace['line']);
                            $backtrace['path'] = $this->formatLink($trace['file'], $trace['line']);
                        }
                        $content_item['backtrace'][] = $backtrace;
                        unset($backtrace);
                    }

                    $content[] = $content_item;
                }
            } catch (\Exception $e) {
                // something nasty happened and we cannot throw an exception anymore
                    $title = sprintf('Exception thrown when handling an exception (%s: %s)', get_class($e), $this->escapeHtml($e->getMessage()));
            }
        }
        $errors[] = [
            'status' => $exception->getStatusCode(),
            'title' => $title
        ];

        $errors = array_merge($errors, $content);
        return ['errors' => $errors];
    }

    private function decorate($content)
    {
        return json_encode($content);
    }

    private function formatClass($class)
    {
        $parts = explode('\\', $class);

        return array_pop($parts);
    }

    private function formatPath($path, $line)
    {
        $file = $this->escapeHtml(preg_match('#[^/\\\\]*+$#', $path, $file) ? $file[0] : $path);

        return sprintf('in %s (line %d)', $file, $line);
    }

    private function formatLink($path, $line)
    {
        $fmt = $this->fileLinkFormat;
        if ($fmt && $link = is_string($fmt) ? strtr($fmt, array('%f' => $path, '%l' => $line)) : $fmt->format($path, $line)) {
            return sprintf('%s', $this->escapeHtml($link));
        }
        return sprintf('%s', $this->escapeHtml($path));
    }

    /**
     * Formats an array as a string.
     *
     * @param array $args The argument array
     *
     * @return string
     */
    private function formatArgs(array $args)
    {
        $result = array();
        foreach ($args as $key => $item) {
            if ('object' === $item[0]) {
                $formattedValue = sprintf('object (%s)', $this->formatClass($item[1]));
            } elseif ('array' === $item[0]) {
                $formattedValue = sprintf('array (%s)', is_array($item[1]) ? $this->formatArgs($item[1]) : $item[1]);
            } elseif ('null' === $item[0]) {
                $formattedValue = 'null';
            } elseif ('boolean' === $item[0]) {
                $formattedValue = strtolower(var_export($item[1], true));
            } elseif ('resource' === $item[0]) {
                $formattedValue = 'resource';
            } else {
                $formattedValue = str_replace("\n", '', $this->escapeHtml(var_export($item[1], true)));
            }

            $result[] = is_int($key) ? $formattedValue : sprintf("'%s' => %s", $key, $formattedValue);
        }

        return implode(', ', $result);
    }

    /**
     * HTML-encodes a string.
     */
    private function escapeHtml($str)
    {
        return $str;
        //return htmlspecialchars($str, ENT_COMPAT | ENT_SUBSTITUTE, $this->charset);
    }
}
