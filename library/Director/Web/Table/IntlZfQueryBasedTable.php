<?php

namespace Icinga\Module\Director\Web\Table;

use DateTime;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use IntlDateFormatter;
use Locale;

abstract class IntlZfQueryBasedTable extends ZfQueryBasedTable
{
    protected function getDateFormatter()
    {
        return (new IntlDateFormatter(
            Locale::getDefault(),
            IntlDateFormatter::FULL,
            IntlDateFormatter::NONE
        ));
    }

    /**
     * @param  int $timestamp
     */
    protected function renderDayIfNew($timestamp)
    {
        $day = $this->getDateFormatter()->format((new DateTime())->setTimestamp($timestamp));

        if ($this->lastDay !== $day) {
            $this->nextHeader()->add(
                $this::th($day, [
                    'colspan' => 2,
                    'class'   => 'table-header-day'
                ])
            );

            $this->lastDay = $day;
            $this->nextBody();
        }
    }

    protected function getTime(int $timeStamp)
    {
        $timeFormatter = $this->getDateFormatter();

        $timeFormatter->setPattern(
            in_array(Locale::getDefault(), ['en_US', 'en_US.UTF-8']) ? 'h:mm:ss a' : 'H:mm:ss'
        );

        return $timeFormatter->format((new DateTime())->setTimestamp($timeStamp));
    }
}
