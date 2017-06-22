<?php

namespace ipl\Web;

use Icinga\Application\Icinga;
use Icinga\Exception\ProgrammingError;
use Icinga\Web\Url as WebUrl;
use Icinga\Web\UrlParams;

/**
 * Class Url
 *
 * The main purpose of this class is to get unit tests running on CLI
 * Little code from Icinga\Web\Url has been duplicated, as neither fromPath()
 * nor getRequest() can be extended in a meaningful way at the time of this
 * writing
 *
 * @package ipl\Web
 */
class Url extends WebUrl
{
    public static function fromPath($url, array $params = array(), $request = null)
    {
        if ($request === null) {
            $request = static::getRequest();
        }

        if (! is_string($url)) {
            throw new ProgrammingError(
                'url "%s" is not a string',
                $url
            );
        }

        $self = new static;

        if ($url === '#') {
            return $self->setPath($url);
        }

        $parts = parse_url($url);

        $self->setBasePath($request->getBaseUrl());
        if (isset($parts['path'])) {
            $self->setPath($parts['path']);
        }

        if (isset($parts['query'])) {
            $params = UrlParams::fromQueryString($parts['query'])->mergeValues($params);
        }

        if (isset($parts['fragment'])) {
            $self->setAnchor($parts['fragment']);
        }

        $self->setParams($params);
        return $self;
    }

    /**
     * Create a new Url class representing the current request
     *
     * If $params are given, those will be added to the request's parameters
     * and overwrite any existing parameters
     *
     * @param   UrlParams|array     $params  Parameters that should additionally be considered for the url
     * @param   \Icinga\Web\Request $request A request to use instead of the default one
     *
     * @return  Url
     */
    public static function fromRequest($params = array(), $request = null)
    {
        if ($request === null) {
            $request = static::getRequest();
        }

        $url = new Url();
        $url->setPath(ltrim($request->getPathInfo(), '/'));
        $request->getQuery();

        // $urlParams = UrlParams::fromQueryString($request->getQuery());
        if (isset($_SERVER['QUERY_STRING'])) {
            $urlParams = UrlParams::fromQueryString($_SERVER['QUERY_STRING']);
        } else {
            $urlParams = UrlParams::fromQueryString('');
            foreach ($request->getQuery() as $k => $v) {
                $urlParams->set($k, $v);
            }
        }

        foreach ($params as $k => $v) {
            $urlParams->set($k, $v);
        }
        $url->setParams($urlParams);
        $url->setBasePath($request->getBaseUrl());
        return $url;
    }

    public function setBasePath($basePath)
    {
        if (property_exists($this, 'basePath')) {
            parent::setBasePath($basePath);
        } else {
            return $this->setBaseUrl($basePath);
        }
    }

    protected static function getRequest()
    {
        $app = Icinga::app();
        if ($app->isCli()) {
            return new FakeRequest();
        } else {
            return $app->getRequest();
        }
    }
}
