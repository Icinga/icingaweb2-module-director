<?php

namespace Icinga\Module\Director\Web\Form\Windows;

use gipfl\Web\Form;
use Icinga\Exception\ProgrammingError;
use ipl\Html\Contract\FormElement;
use ipl\Html\Text;
use ipl\Html\FormElement\SelectElement;
use ipl\Html\FormElement\SelectOption;
use ipl\Html\FormElement\SubmitElement;
use ipl\Html\FormElement\TextElement;

class FormSerialization
{
    public static function serialize(Form $form): object
    {
        $result = [
            'ResponseType' => 'Form',
            'Title' => $form->getTitle(),
            'Action' => $form->getAction(),
            'Elements' => [],
        ];

        $elements = & $result['Elements'];
        foreach ($form->getElements() as $element) {
            $elements[] = self::serializeFormElement($element);
        }

        return (object) $result;
    }

    protected static function serializeFormElement(FormElement $element): object
    {
        $result = (object) [
            'Name'  => $element->getName(),
            'Type'  => $element->getName(),
            'Label' => $element->getLabel(),
            'Value' => $element->getValue(),
            'Required' => $element->isRequired(),
        ];

        if ($element instanceof SelectElement) {
            $result->Options = self::serializeOptions($element);
            $result->Type = 'select';
        } elseif ($element instanceof TextElement) {
            $result->Type = 'text';
        } elseif ($element instanceof SubmitElement) {
            $result->Type = 'submit';
        } else {
            throw new ProgrammingError(
                'This form cannot be serialized, there is no handler for ' . get_class($element)
            );
        }

        return $result;
    }

    protected static function serializeOptions(SelectElement $element): array
    {
        $result = [];
        $element->ensureAssembled();
        foreach ($element->getContent() as $content) {
            if ($content instanceof SelectOption) {
                $result[] = self::serializeOption($content);
            } else {
                throw new ProgrammingError(
                    'This select option cannot be serialized, there is no handler for ' . get_class($element)
                );
            }
        }
        return $result;
    }

    protected static function serializeOption(SelectOption $option)
    {
        $option->ensureAssembled();
        $content = $option->getContent();
        foreach ($content as $text) {
            if ($text instanceof Text) {
                return (object) [
                    'Value' => $option->getValue(),
                    'Label' => $option->getContent()[0]->render()
                ];
            }
        }

        throw new ProgrammingError('Not an option: ' . json_encode($content));
    }
}
