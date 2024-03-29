<?php

/**
 *
 * @uses Wootook_Object
 * @uses Legacies_Empire
 * @uses Wootook_Empire_Model_User
 */
abstract class Wootook_Core_Entity_SubTable
    extends Wootook_Core_Database_Resource
{
    private $_isLoaded = false;
    protected $_idFieldNames = array();

    protected $_eventPrefix = 'entity.sub-table';

    protected function _save()
    {
        if ($this->_isLoaded !== false) {
            $fields = array();
            $values = array();
            $idFields = array();
            foreach ($this->getAllDatas() as $field => $value) {
                if (in_array($field, $this->_idFieldNames)) {
                    $idFields[] = "{$field}=:{$field}";
                } else {
                    $fields[] = "{$field}=:{$field}";
                }
                $values[$field] = $value;
            }
            $idFields = '(' . implode(') AND (', $idFields) . ')';
            $fields = implode(', ', $fields);

            $database = Wootook_Database::getSingleton();
            $sql =<<<SQL_EOF
UPDATE {$database->getTable(self::getTableName())}
    SET {$fields}
    WHERE {$idFields}
SQL_EOF;
            $statement = $database->prepare($sql);

            $statement->execute($values);
        } else {
            $datas = $this->getAllDatas();
            $fields = implode(', ', array_keys($datas));

            $tokens = array();
            foreach ($datas as $field => $value) {
                $tokens[] = ":{$field}";
            }
            $tokens = implode(', ', $tokens);

            $database = Wootook_Database::getSingleton();
            $sql =<<<SQL_EOF
INSERT INTO {$database->getTable(self::getTableName())} ($fields)
    VALUES ({$tokens})
SQL_EOF;
            $statement = $database->prepare($sql);
            $statement->execute($datas);
        }

        return $this;
    }

    protected function _load()
    {
        $idValues = func_get_arg(0);
        $database = Wootook_Database::getSingleton();

        $idFields = array();
        foreach ($this->_idFieldNames as $field) {
            $idFields[] = "{$field}=:{$field}";
        }
        $idFields = '(' . implode(') AND (', $idFields) . ')';

        $sql =<<<SQL_EOF
SELECT * FROM {$database->getTable(self::getTableName())}
    WHERE $idFields
    LIMIT 1
SQL_EOF;
        $statement = $database->prepare($sql);
        $statement->execute($idValues);

        $datas = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($datas) || empty($datas)) {
            throw new Wootook_Core_Exception_DataAccessException('Could not load data: this id combination could not be found.');
        }

        $this->_data = $datas;
        $this->_isLoaded = true;

        return $this;
    }

    protected function _delete()
    {
        $database = Wootook_Database::getSingleton();

        $idFields = array();
        $idValues = array();
        foreach ($this->_idFieldNames as $field) {
            $idFields[] = "{$field}=:{$field}";
            $idValues[$field] = $this->getData($field);
        }
        $idFields = '(' . implode(') AND (', $idFields) . ')';

        $sql =<<<SQL_EOF
DELETE {$database->getTable(self::getTableName())}
    WHERE $idFields
    LIMIT 1
SQL_EOF;
        $statement = $database->prepare($sql);
        $statement->execute($idValues);

        $this->_data = array();
        $this->_isLoaded = false;

        return $this;
    }
}