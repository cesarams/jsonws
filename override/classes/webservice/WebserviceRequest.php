<?php

class WebserviceRequest extends WebserviceRequestCore
{
    protected function executeEntityPost()
    {
        if ($_GET['output_format'] === 'JSON') {
            $this->saveEntityFromJson(201);
        }
        else {
            parent::executeEntityPost();
        }
    }

    protected function executeEntityPut()
    {
        if ($_GET['output_format'] === 'JSON') {
            $this->saveEntityFromJson(200);
        }
        else {
            parent::executeEntityPut();
        }
    }

    protected function saveEntityFromJson($successReturnCode)
    {
        try {
            $json = json_decode($this->_inputXml);
        } catch (Exception $error) {
            $this->setError(500, 'JSON error : ' . $error->getMessage() . "\n" . 'JSON length : ' . strlen($this->_inputXml) . "\n" . 'Original JSON : ' . $this->_inputXml, 127);
            return;
        }

        $jsonEntities = get_object_vars($json);
        $object = null;
        $ids = array();

        foreach ($jsonEntities as $entity) {
            // To cast in string allow to check null values
            if ((string) $entity->id != '') {
                $ids[] = (int) $entity->id;
            }
        }

        if ($this->method == 'PUT') {
            $ids2 = array();
            $ids2 = array_unique($ids);
            if (count($ids2) != count($ids)) {
                $this->setError(400, 'id is duplicate in request', 89);
                return false;
            }
            if (count($jsonEntities) != count($ids)) {
                $this->setError(400, 'id is required when modifying a resource', 90);
                return false;
            }
        } elseif ($this->method == 'POST' && count($ids) > 0) {
            $this->setError(400, 'id is forbidden when adding a new resource', 91);
            return false;
        }

        foreach ($jsonEntities as $jsonEntity) {
            $attributes = $jsonEntity;

            if ($this->method == 'POST') {
                $object = new $this->resourceConfiguration['retrieveData']['className']();
            } elseif ($this->method == 'PUT') {
                $object = new $this->resourceConfiguration['retrieveData']['className']((int) $attributes->id);
                if (!$object->id) {
                    $this->setError(404, 'Invalid ID', 92);
                    return false;
                }
            }
            $this->objects[] = $object;
            $i18n = false;
            // attributes
            foreach ($this->resourceConfiguration['fields'] as $fieldName => $fieldProperties) {
                $sqlId = $fieldProperties['sqlId'];

                if ($fieldName == 'id') {
                    $sqlId = $fieldName;
                }
                if (isset($attributes->$fieldName) && isset($fieldProperties['sqlId']) && (!isset($fieldProperties['i18n']) || !$fieldProperties['i18n'])) {
                    if (isset($fieldProperties['setter'])) {
                        // if we have to use a specific setter
                        if (!$fieldProperties['setter']) {
                            // if it's forbidden to set this field
                            $this->setError(400, 'parameter "' . $fieldName . '" not writable. Please remove this attribute of this JSON', 93);
                            return false;
                        } else {
                            $object->{$fieldProperties['setter']}((string) $attributes->$fieldName);
                        }
                    } elseif (property_exists($object, $sqlId)) {
                        $object->$sqlId = (string) $attributes->$fieldName;
                    } else {
                        $this->setError(400, 'Parameter "' . $fieldName . '" can\'t be set to the object "' . $this->resourceConfiguration['retrieveData']['className'] . '"', 123);
                    }
                } elseif (isset($fieldProperties['required']) && $fieldProperties['required'] && !$fieldProperties['i18n']) {
                    $this->setError(400, 'parameter "' . $fieldName . '" required', 41);
                    return false;
                } elseif ((!isset($fieldProperties['required']) || !$fieldProperties['required']) && property_exists($object, $sqlId)) {
                    $object->$sqlId = null;
                }
                if (isset($fieldProperties['i18n']) && $fieldProperties['i18n']) {
                    $i18n = true;

                    if (isset($attributes->$fieldName) && is_array($attributes->$fieldName)) {
                        foreach ($attributes->$fieldName as $lang) {
                            $object->{$fieldName}[(int) $lang->id] = (string) $lang->value;
                        }
                    }
                    else {
                        $object->{$fieldName} = (string) $attributes->$fieldName;
                    }
                }
            }

            // Apply the modifiers if they exist
            foreach ($this->resourceConfiguration['fields'] as $fieldName => $fieldProperties) {
                if (isset($fieldProperties['modifier']) && isset($fieldProperties['modifier']['modifier']) && $fieldProperties['modifier']['http_method'] & constant('WebserviceRequest::HTTP_' . $this->method)) {
                    $object->{$fieldProperties['modifier']['modifier']}();
                }
            }

            if (!$this->hasErrors()) {
                if ($i18n && ($retValidateFieldsLang = $object->validateFieldsLang(false, true)) !== true) {
                    $this->setError(400, 'Validation error: "' . $retValidateFieldsLang . '"', 84);
                    return false;
                } elseif (($retValidateFields = $object->validateFields(false, true)) !== true) {
                    $this->setError(400, 'Validation error: "' . $retValidateFields . '"', 85);
                    return false;
                } else {
                    // Call alternative method for add/update
                    $objectMethod = ($this->method == 'POST' ? 'add' : 'update');
                    if (isset($this->resourceConfiguration['objectMethods']) && array_key_exists($objectMethod, $this->resourceConfiguration['objectMethods'])) {
                        $objectMethod = $this->resourceConfiguration['objectMethods'][$objectMethod];
                    }
                    $result = $object->{$objectMethod}();
                    if ($result) {
                        if (isset($attributes->associations)) {
                            foreach (get_object_vars($attributes->associations) as $associationKey => $associationValue) {
                                // associations
                                if (isset($this->resourceConfiguration['associations'][$associationKey])) {
                                    $assocItems = $associationValue;
                                    $values = array();
                                    foreach ($assocItems as $assocItem) {
                                        $fields = get_object_vars($assocItem);
                                        $entry = array();
                                        foreach ($fields as $fieldName => $fieldValue) {
                                            $entry[$fieldName] = (string) $fieldValue;
                                        }
                                        $values[] = $entry;
                                    }
                                    $setter = $this->resourceConfiguration['associations'][$associationKey]['setter'];
                                    if (!is_null($setter) && $setter && method_exists($object, $setter) && !$object->$setter($values)) {
                                        $this->setError(500, 'Error occurred while setting the ' . $associationKey . ' value', 85);
                                        return false;
                                    }
                                } elseif ($associationKey != 'i18n') {
                                    $this->setError(400, 'The association "' . $associationKey . '" does not exists', 86);
                                    return false;
                                }
                            }
                        }
                        $assoc = Shop::getAssoTable($this->resourceConfiguration['retrieveData']['table']);
                        if ($assoc !== false && $assoc['type'] != 'fk_shop') {
                            // PUT nor POST is destructive, no deletion
                            $sql = 'INSERT IGNORE INTO `' . bqSQL(_DB_PREFIX_ . $this->resourceConfiguration['retrieveData']['table'] . '_' . $assoc['type']) . '` (id_shop, `' . bqSQL($this->resourceConfiguration['fields']['id']['sqlId']) . '`) VALUES ';
                            foreach (self::$shopIDs as $id) {
                                $sql .= '(' . (int) $id . ',' . (int) $object->id . ')';
                                if ($id != end(self::$shopIDs)) {
                                    $sql .= ', ';
                                }
                            }
                            Db::getInstance()->execute($sql);
                        }
                    } else {
                        $this->setError(500, 'Unable to save resource', 46);
                    }
                }
            }
        }
        if (!$this->hasErrors()) {
            $this->objOutput->setStatus($successReturnCode);
            return true;
        }
    }
}