<?php

class Wootook_Core_Form_Validator_FormKey
    extends Wootook_Core_Form_ValidatorAbstract
{
    public function validate(Wootook_Core_Form_FieldAbstract $field, $data)
    {
        $form = $field->getForm();
        $session = $form->getSession();

        if ($session->getFormKey(false) == $data) {
            return true;
        }

        $this->_getSession($field)
            ->addError('CSRF token failure.', $field->getName());

        return false;
    }
}