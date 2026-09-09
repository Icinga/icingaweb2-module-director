<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Application\Config;
use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\DirectorDatalistEntry;
use ipl\Stdlib\Filter as iplFilter;
use ipl\Web\Compat\CompatController;
use ipl\Web\Filter\QueryString;
use ipl\Web\FormElement\SearchSuggestions;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Serves autocomplete suggestions for datalist entries used as property values
 */
class SuggestionsController extends CompatController
{
    /** @var Db */
    protected $db;

    /**
     * List datalist entries for the property given by uuid, for autocomplete
     *
     * exclude is a comma separated list of entry names to leave out, so an
     * already picked value doesn't show up again in the suggestions
     */
    public function datalistEntryAction(): void
    {
        $excludes = iplFilter::none();
        $uuid = Uuid::fromString($this->params->shiftRequired('uuid'));
        $this->db = Db::fromResourceName(
            Config::module('director')->get('db', 'resource')
        );

        $excludeTerms = [];

        if ($this->params->has('exclude')) {
            $excludeTerms = explode(',', $this->params->get('exclude'));
        }

        if (! empty($excludeTerms)) {
            foreach ($excludeTerms as $excludeTerm) {
                $excludes->add(iplFilter::equal('entry_name', $excludeTerm));
            }
        }

        $suggestions = new SearchSuggestions((function () use ($uuid, $excludes, &$suggestions) {
            foreach ($suggestions->getExcludeTerms() as $excludeTerm) {
                $excludes->add(iplFilter::equal('entry_name', $excludeTerm));
            }

            $query = $this->db->select()
                ->from(['dle' => 'director_datalist_entry'], ['entry_name', 'entry_value', 'allowed_roles'])
                ->join(['dl' => 'director_datalist'], 'dl.id = dle.list_id', [])
                ->join(['dpl' => 'director_property_datalist'], 'dl.uuid = dpl.list_uuid', [])
                ->where(
                    'dpl.property_uuid',
                    Db\DbUtil::quoteBinaryCompat($uuid->getBytes(), $this->db->getDbAdapter())
                );

            $filterString = QueryString::render(iplFilter::all($excludes));
            if ($filterString !== '') {
                $query->addFilter(Filter::fromQueryString($filterString));
            }

            foreach ($this->db->fetchAll($query) as $row) {
                $row = (array) $row;
                if (! DirectorDatalistEntry::isAllowedForCurrentUser($row['allowed_roles'])) {
                    continue;
                }

                yield [
                    'search' => $row['entry_name'],
                    'label'  => $row['entry_value'],
                    'class'  => 'list-entry'
                ];
            }
        })());

        $this->getDocument()->addHtml($suggestions->forRequest($this->getServerRequest()));
    }
}
