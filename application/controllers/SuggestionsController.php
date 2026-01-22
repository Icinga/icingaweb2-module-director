<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Application\Config;
use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Db;
use ipl\Stdlib\Filter as iplFilter;
use ipl\Web\Compat\CompatController;
use ipl\Web\Filter\QueryString;
use ipl\Web\FormElement\SearchSuggestions;
use ipl\Web\FormElement\TermInput;
use ipl\Web\FormElement\TermInput\TermSuggestions;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class SuggestionsController extends CompatController
{
    /** @var Db */
    protected $db;

    /** @var UuidInterface */
    private UuidInterface $uuid;

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

            $query = $this->db->select()->from(['dle' => 'director_datalist_entry'], ['entry_name', 'entry_value'])
                ->join(['dl' => 'director_datalist'], 'dl.id = dle.list_id', [])
                ->join(['dpl' => 'director_property_datalist'], 'dl.uuid = dpl.list_uuid', [])
                ->where('dpl.property_uuid', $uuid->getBytes());

            $filterString = QueryString::render(iplFilter::all($excludes));
            if ($filterString !== '') {
                $query->addFilter(Filter::fromQueryString($filterString));
            }

            foreach ($this->db->fetchPairs($query) as $name => $value) {
                yield [
                    'search' => $name,
                    'label'  => $value,
                    'class'  => 'list-entry'
                ];
            }
        })());

        $this->getDocument()->addHtml($suggestions->forRequest($this->getServerRequest()));
    }
}
