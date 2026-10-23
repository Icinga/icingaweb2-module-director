<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Forms;

use Exception;
use Icinga\Application\Icinga;
use Icinga\Module\Director\Db;
use Icinga\Web\Notification;
use Icinga\Web\Session;
use Icinga\Web\Url;
use ipl\Web\Common\CsrfCounterMeasure;
use ipl\Web\Compat\CompatForm;

class ActivityLogNoteForm extends CompatForm
{
    use CsrfCounterMeasure;

    /**
     * @param Db $db
     * @param int[] $ids
     * @param string|null $note Shown in the field, only known for a single entry
     */
    public function __construct(
        protected Db $db,
        protected array $ids,
        protected ?string $note = null
    ) {
    }

    protected function assemble(): void
    {
        // Has to come before the first element
        $this->applyDefaultElementDecorators();
        $isBulk = count($this->ids) > 1;
        if (! $isBulk) {
            // The detail view has its own Note row, an empty label block would only take space
            $decorators = $this->getDefaultElementDecorators();
            unset($decorators['Label'], $decorators['LabelGroup']);
            $this->setDefaultElementDecorators($decorators);
        }

        $this->addCsrfCounterMeasure(Session::getSession()->getId());

        $options = [
            'rows'  => 3,
            'value' => $this->note,
        ];
        if ($isBulk) {
            $options['label'] = $this->translate('Note');
            $options['description'] = $this->translate(
                'Saved for every selected change. Leave it empty to keep the existing notes.'
            );
        }

        $this->addElement('textarea', 'note', $options);
        $this->addElement('submit', 'submit', [
            'label' => $this->translate('Save note')
        ]);
    }

    protected function onSuccess(): void
    {
        $note = trim((string) $this->getValue('note'));
        $isSingle = count($this->ids) === 1;
        if ($note === '' && ! $isSingle) {
            // One empty field must not wipe the notes of many entries
            $this->redirectBack($this->translate('No note entered, the notes were left as they are'));

            return;
        }

        $ids = $this->fetchExistingIds();
        $this->storeNotes($ids, $note);

        if ($note === '') {
            $this->redirectBack($this->translate('The note has been removed'));
        } elseif (count($ids) === 1) {
            $this->redirectBack($this->translate('The note has been saved'));
        } else {
            $this->redirectBack(sprintf($this->translate('The note has been saved for %d entries'), count($ids)));
        }
    }

    /**
     * Show the message and load the page again, so a reload does not send the form twice
     *
     * @param string $message
     */
    protected function redirectBack(string $message): void
    {
        Notification::success($message);
        Icinga::app()->getFrontController()->getResponse()->redirectAndExit(Url::fromRequest());
    }

    /**
     * Entries that were cleaned up since the page loaded can't hold a note
     *
     * @return int[]
     */
    protected function fetchExistingIds(): array
    {
        $db = $this->db->getDbAdapter();

        return array_map('intval', $db->fetchCol(
            $db->select()->from('director_activity_log', 'id')->where('id IN (?)', $this->ids)
        ));
    }

    /**
     * Write the note for each entry, an empty note removes it
     *
     * @param int[] $ids
     * @param string $note
     *
     * @throws Exception
     */
    protected function storeNotes(array $ids, string $note): void
    {
        $db = $this->db->getDbAdapter();

        $db->beginTransaction();
        try {
            $this->db->storeActivityLogNotes($ids, $note);
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();

            throw $e;
        }
    }
}
