<?php

namespace dipl\Html;

use dipl\Web\Url;
use Icinga\Web\Url as WebUrl;

class Link extends BaseHtmlElement
{
    protected $tag = 'a';

    /** @var Url */
    protected $url;

    /**
     * Link constructor.
     * @param $content
     * @param $url
     * @param null $urlParams
     * @param array|null $attributes
     * @throws \Icinga\Exception\IcingaException
     * @throws \Icinga\Exception\ProgrammingError
     */
    public function __construct($content, $url, $urlParams = null, array $attributes = null)
    {
        $this->setContent($content);
        $this->setAttributes($attributes);
        $this->getAttributes()->registerAttributeCallback('href', array($this, 'getHrefAttribute'));
        $this->setUrl($url, $urlParams);
    }

    /**
     * @param ValidHtml|array|string $content
     * @param Url|string $url
     * @param array $urlParams
     * @param mixed $attributes
     *
     * @return static
     * @throws \Icinga\Exception\IcingaException
     * @throws \Icinga\Exception\ProgrammingError
     */
    public static function create($content, $url, $urlParams = null, array $attributes = null)
    {
        $link = new static($content, $url, $urlParams, $attributes);
        return $link;
    }

    /**
     * @param $url
     * @param $urlParams
     * @throws \Icinga\Exception\ProgrammingError
     */
    public function setUrl($url, $urlParams)
    {
        if ($url instanceof WebUrl) { // Hint: Url is also a WebUrl
            if ($urlParams !== null) {
                $url->addParams($urlParams);
            }

            $this->url = $url;
        } else {
            if ($urlParams === null) {
                $this->url = Url::fromPath($url);
            } else {
                $this->url = Url::fromPath($url, $urlParams);
            }
        }

        $this->url->getParams();
    }

    /**
     * @return Attribute
     * @throws \Icinga\Exception\ProgrammingError
     */
    public function getHrefAttribute()
    {
        return new Attribute('href', $this->getUrl()->getAbsoluteUrl('&'));
    }

    /**
     * @return Url
     */
    public function getUrl()
    {
        // TODO: What if null? #?
        return $this->url;
    }
}
