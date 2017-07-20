<?php

namespace ipl\Html;

use ipl\Web\Url;
use Icinga\Web\Url as WebUrl;

class Img extends BaseElement
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
        $img->attributes()->registerCallbackFor('src', array($img, 'getSrcAttribute'));
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
                $this->url = Url::fromPath($url);
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
        return new Attribute('src', $this->getUrl()->getAbsoluteUrl('&'));
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
