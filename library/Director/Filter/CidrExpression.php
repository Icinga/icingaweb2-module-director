<?php

namespace Icinga\Module\Director\Filter;

use Icinga\Data\Filter\FilterExpression;
use InvalidArgumentException;

use function array_map;
use function filter_var;
use function inet_pton;
use function pack;
use function preg_match;
use function str_pad;
use function str_split;

class CidrExpression extends FilterExpression
{
    protected $networkAddress;
    protected $broadcastAddress;

    public function __construct($column, $sign, $expression)
    {
        if ($parts = static::splitOptionalCidrString($expression)) {
            list($this->networkAddress, $this->broadcastAddress) = $parts;
        } else {
            throw new InvalidArgumentException("'$expression' isn't valid CIDR notation");
        }

        parent::__construct($column, $sign, $expression);
    }

    public static function isCidrFormat(string $string): bool
    {
        return static::splitOptionalCidrString($string) !== null;
    }

    protected static function splitOptionalCidrString(string $string): ?array
    {
        if (preg_match('#^(.+?)/(\d{1,3})$#', $string, $match)) {
            $address = $match[1];
            $mask = (int) $match[2];

            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && $mask <= 32) {
                $bits = 32;
            } elseif (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && $mask <= 128) {
                $bits = 128;
            } else {
                return null;
            }

            $binaryAddress = inet_pton($address);
            $broadcast = $binaryAddress | static::bitmaskToInverseBinaryMask($mask, $bits);

            return [$binaryAddress, $broadcast];
        }

        return null;
    }

    public function matches($row): bool
    {
        if (! isset($row->{$this->column})) {
            return false;
        }
        $value = inet_pton((string) $row->{$this->column});

        return $value >= $this->networkAddress && $value <= $this->broadcastAddress;
    }

    public static function fromExpression(FilterExpression $filter): CidrExpression
    {
        $sign = $filter->getSign();
        if ($sign !== '=') {
            throw new InvalidArgumentException("'$sign' cannot be applied to CIDR notation");
        }
        return new CidrExpression($filter->getColumn(), $sign, $filter->getExpression());
    }

    protected static function bitmaskToInverseBinaryMask($mask, $maxLen): string
    {
        $binary = str_pad(str_pad('', $mask, '0'), $maxLen, '1');
        $address = '';
        foreach (array_map('bindec', str_split($binary, 8)) as $char) {
            $address .= pack('C*', $char);
        }

        return $address;
    }
}
