<?php

namespace dipl\Html;

use Error as PhpError;
use Exception;

/**
 * Class Error
 *
 * TODO: Eventually allow to (statically) inject a custom error renderer.
 *
 * @package ipl\Html
 */
abstract class Error
{
    /** @var bool */
    protected static $showTraces = true;

    /**
     *
     * @param Exception|PhpError|string $error
     * @return HtmlDocument
     */
    public static function show($error)
    {
        if ($error instanceof Exception) {
            $msg = static::createMessageForException($error);
        } elseif ($error instanceof PhpError) {
            $msg = static::createMessageForException($error);
        } elseif (is_string($error)) {
            $msg = $error;
        } else {
            // TODO: translate?
            $msg = 'Got an invalid error';
        }

        $result = static::renderErrorMessage($msg);
        if (static::showTraces()) {
            $result->add(Html::tag('pre', $error->getTraceAsString()));
        }

        return $result;
    }

    /**
     *
     * @param Exception|PhpError|string $error
     * @return string
     */
    public static function render($error)
    {
        return static::show($error)->render();
    }

    /**
     * @param string
     * @return HtmlDocument
     */
    protected static function renderErrorMessage($message)
    {
        $output = new HtmlDocument();
        $output->add(
            Html::tag('div', ['class' => 'exception'], [
                Html::tag('h1', [
                    Html::tag('i', ['class' => 'icon-bug']),
                    // TODO: Translate? More hints?
                    'Oops, an error occurred!'
                ]),
                Html::tag('pre', $message)
            ])
        );

        return $output;
    }

    /**
     * @param PhpError|Exception $exception
     * @return string
     */
    protected static function createMessageForException($exception)
    {
        $file = preg_split('/[\/\\\]/', $exception->getFile(), -1, PREG_SPLIT_NO_EMPTY);
        $file = array_pop($file);
        return sprintf(
            '%s (%s:%d)',
            $exception->getMessage(),
            $file,
            $exception->getLine()
        );
    }

    /**
     * @param bool|null $show
     * @return bool
     */
    public static function showTraces($show = null)
    {
        if ($show !== null) {
            self::$showTraces = (bool) $show;
        }

        return self::$showTraces;
    }

    /**
     * @param $any
     * @return string
     */
    public static function getPhpTypeName($any)
    {
        if (is_object($any)) {
            return get_class($any);
        } else {
            return gettype($any);
        }
    }
}
