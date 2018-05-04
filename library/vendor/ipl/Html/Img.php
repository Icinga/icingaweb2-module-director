<?php

namespace dipl\Html;

use dipl\Web\Url;
use Icinga\Web\Url as WebUrl;

class Img extends BaseHtmlElement
{
    protected $tag = 'img';

    /** @var Url */
    protected $url;

    protected $defaultAttributes = array('alt' => '');

    protected function __construct()
    {
    }

    /**
     * @param Url|string $url
     * @param array $urlParams
     * @param array $attributes
     *
     * @return static
     */
    public static function create($url, $urlParams = null, array $attributes = null)
    {
        /** @var Img $img */
        $img = new static();
        $img->setAttributes($attributes);
        $img->getAttributes()->registerAttributeCallback('src', array($img, 'getSrcAttribute'));
        $img->setUrl($url, $urlParams);
        return $img;
    }

    public function setUrl($url, $urlParams)
    {
        if ($url instanceof WebUrl) { // Hint: Url is also a WebUrl
            if ($urlParams !== null) {
                $url->addParams($urlParams);
            }

            $this->url = $url;
        } else {
            if ($urlParams === null) {
                if (is_string($url) && substr($url, 0, 5) === 'data:') {
                    $this->url = $url;
                    return;
                } else {
                    $this->url = Url::fromPath($url);
                }
            } else {
                $this->url = Url::fromPath($url, $urlParams);
            }
        }

        $this->url->getParams();
    }

    /**
     * @return Attribute
     */
    public function getSrcAttribute()
    {
        if (is_string($this->url)) {
            return new Attribute('src', $this->url);
        } else {
            return new Attribute('src', $this->getUrl()->getAbsoluteUrl('&'));
        }
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
