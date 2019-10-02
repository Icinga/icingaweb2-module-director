<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class DirectorDatafieldCategoryForm extends DirectorObjectForm
{
    protected $objectName = 'Data field category';

    protected $listUrl = 'director/data/fieldcategories';

    public function setup()
    {
        $this->addHtmlHint(
            $this->translate(
                'Data field categories allow to structure Data Fields. Fields with'
                . ' a category will be shown grouped by category.'
            )
        );

        $this->addElement('text', 'category_name', [
            'label'       => $this->translate('Category name'),
            'description' => $this->translate(
                'The unique name of the category used for grouping your custom Data Fields.'
            ),
            'required'    => true,
        ]);

        $this->addElement('text', 'description', [
            'label'       => $this->translate('Description'),
        ]);

        $this->setButtons();
    }
}
