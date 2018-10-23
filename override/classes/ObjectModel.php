<?php

class ObjectModel extends ObjectModelCore
{
    public function validateFieldsLang($die = true, $errorReturn = false)
    {
        $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');
        $this->id_lang = $defaultLang; //Added
        foreach ($this->def['fields'] as $field => $data) {
            if (empty($data['lang'])) {
                continue;
            }

            $values = $this->$field;

            // If the object has not been loaded in multilanguage, then the value is the one for the current language of the object
            if (!is_array($values)) {
                $values = array($this->id_lang => $values);
            }

            // The value for the default must always be set, so we put an empty string if it does not exists
            if (!isset($values[$defaultLang])) {
                $values[$defaultLang] = '';
            }

            foreach ($values as $id_lang => $value) {
                if (is_array($this->update_fields) && empty($this->update_fields[$field][$id_lang])) {
                    continue;
                }

                $message = $this->validateField($field, $value, $id_lang);
                if ($message !== true) {
                    if ($die) {
                        throw new PrestaShopException($message);
                    }

                    return $errorReturn ? $message : false;
                }
            }
        }
        return true;
    }
}