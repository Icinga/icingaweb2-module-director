<?php

namespace Icinga\Module\Director\Windows;

use JsonSerializable;

class MenuEntry implements JsonSerializable
{
    /**
     * @readonly
     * @var string
     */
    public $label;

    /**
     * @readonly
     * @var string
     */
    public $description;

    /**
     * @readonly
     * @var string
     */
    public $url;

    /**
     * @readonly
     * @var ?string
     */
    public $icon;

    public function __construct(string $label, string $description, string $url, ?string $icon = null)
    {
        $this->label = $label;
        $this->description = $description;
        $this->url = $url;
        $this->icon = $icon;
    }

    public function jsonSerialize(): object
    {
        return (object) [
            'Label'       => $this->label,
            'Description' => $this->description,
            'Url'         => $this->url,
            'Icon'        => $this->icon,
        ];
    }
}
