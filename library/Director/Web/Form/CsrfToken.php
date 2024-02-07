<?php

namespace Icinga\Module\Director\Web\Form;

class CsrfToken
{
    /**
     * Check whether the given token is valid
     *
     * @param string $token Token
     *
     * @return bool
     */
    public static function isValid($token)
    {
        if (strpos($token, '|') === false) {
            return false;
        }

        list($seed, $token) = explode('|', $token);

        if (!is_numeric($seed)) {
            return false;
        }

        return $token === hash('sha256', self::getSessionId() . $seed);
    }

    /**
     * Create a new token
     *
     * @return string
     */
    public static function generate()
    {
        $seed = mt_rand();
        $token = hash('sha256', self::getSessionId() . $seed);

        return sprintf('%s|%s', $seed, $token);
    }

    /**
     * Get current session id
     *
     * TODO: we should do this through our App or Session object
     *
     * @return string
     */
    protected static function getSessionId()
    {
        return session_id();
    }
}
