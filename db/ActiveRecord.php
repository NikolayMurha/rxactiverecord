<?php
/**
 * @copyright Copyright &copy; rainyx, rainyx.com, 2013 - 2017
 * @file ActiveRecord.php
 * @version ${VERSION}
 */

namespace rainyx\rxactiverecord\db;

/**
 * Class ActiveRecord
 * @package rainyx\rxkit\db
 * @author rainyx <atrainyx#gmail.com>
 * @since 1.0
 */
class ActiveRecord extends \yii\db\ActiveRecord  implements ActiveNestedInterface
{

    private $_dirtyRelatedRecords = [];
    private $_unrelatedRecords = [];


    /**
     * @param array $row
     * @return static
     */
    public static function instantiate($row)
    {
        if (isset($row['type']))
        {
            $modelClass = $row['type'];
            return new $modelClass;
        }
        return new static;
    }

    /**
     * 接受Nested Attributes的Relation
     * @return array
     */
    public function acceptNestedAttributesFor() {
        return [];
    }

    public function getAttributeLabel($attribute)
    {
        return parent::getAttributeLabel($attribute);
    }

    /**
     * @param array $values
     * @param bool $safeOnly
     */
    public function setAttributes($values, $safeOnly = true)
    {
        foreach ($values as $name=>$value) {

            if (!method_exists($this, 'get'.ucfirst($name))
                || ($relation = $this->getRelation($name, false)) == null) {
                continue;
            }

            if ($relation->multiple) {

                // Has many
                foreach ($value as $attributes) {
                    // TODO Replace id with primaryKey.
                    if (isset($attributes['id'])) {
                        $this->updateRelatedRecord($name, $attributes);
                    } else {
                        $this->buildUnrelatedRecord($name, $attributes);
                    }
                }

                unset($value[$name]);


            } else {

                // Has one
                $result = parent::__get($name);
                if ($result == null) {
                    $this->buildUnrelatedRecord($name, $value);
                } else {
                    $this->updateRelatedRecord($name, $value);
                }

                unset($values[$name]);

            }

        }

        parent::setAttributes($values, $safeOnly);
    }

    /**
     * @param string $name
     * @param ActiveRecordInterface $record
     */
    public function addUnrelatedRecord($name, $record) {
        if (!isset($this->_unrelatedRecords[$name])) {
            $this->_unrelatedRecords[$name] = [];
        }
        $this->_unrelatedRecords[$name][] = $record;
    }

    /**
     * @param $name
     * @param $record
     */
    public function addDirtyRecord($name, $record)
    {
        if (!isset($this->_dirtyRelatedRecords[$name])) {
            $this->_dirtyRelatedRecords[$name] = [];
        }
        $this->_dirtyRelatedRecords[$name][] = $record;
    }

    /**
     * @param $name
     * @return mixed
     */
    public function getDirtyRecords($name)
    {
        return isset($this->_dirtyRelatedRecords[$name]) ? $this->_dirtyRelatedRecords[$name] : [];
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function getUnrelatedRecords($name)
    {
        return isset($this->_unrelatedRecords[$name]) ? $this->_unrelatedRecords[$name] : [];
    }

    /**
     * @param string $name
     * @param mixed $param
     * @return mixed
     */
    public function buildUnrelatedRecord($name, $param)
    {
        $relation = $this->getRelation($name, false);

        /* @var ActiveRecord $instance */
        $clazz = $relation->modelClass;
        $record = new $clazz;
        $record->setAttributes($param);

        $this->addUnrelatedRecord($name, $record);
        // 如果是hasOne，就添加到related
        if (!$relation->multiple)
        {
            $this->populateRelation($name, $record);
        }
        return $record;
    }

    /**
     * Update related record.
     * @param $name
     * @param $param
     * @param $key=null
     */
    public function updateRelatedRecord($name, $param, $key=null)
    {
        $relatedRecord = null;

        $relation = $this->getRelation($name, false);
        $result = $this->$name;

        if ($relation->multiple) {

            // Has many
            if ($key !== null) {

                if (isset($result[$key])) {
                    $relatedRecord = $result[$key];
                }

            } else {
                foreach ($result as $record) {
                    // TODO Composite key.
                    if (!$record->getIsNewRecord() && $record->id == $param['id']) {
                        $relatedRecord = $record;
                        break;
                    }
                }
            }

        } else {
            // Has one
            if ($result) {
                $relatedRecord = $result;
            }
        }

        if ($relatedRecord) {
            $relatedRecord->setAttributes($param);
            $this->addDirtyRecord($name, $relatedRecord);
        }

    }

    /**
     * @param bool $insert
     * @return bool
     */
    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert))
        {
            if ($insert)
            {
                if (in_array('type', $this->attributes()))
                {
                    $this->setAttribute('type', get_called_class());
                }
            }
            return true;
        }

        return false;
    }

    /**
     * Link or update nested records.
     * @param bool $insert
     * @param array $changedAttributes
     */
    public function afterSave($insert, $changedAttributes)
    {
        /* @var ActiveQuery $relation */
        /* @var ActiveRecord $record */

        foreach ($this->_dirtyRelatedRecords as $relationName=>$records) {
            foreach ($records as $record) {
                $record->save();
            }
        }


        foreach ($this->_unrelatedRecords as $relationName=>$records) {

            $relation = $this->getRelation($relationName, false);

            // Polymorphic relation.
            if ($relation->as) {
                foreach ($records as $record) {
                    $polymorphic = $relation->polymorphic;
                    $record->{$polymorphic[0]} = $this->getClassName();
                    $record->{$polymorphic[1]} = $this->getPrimaryKey();
                    $record->save();
                }
            } else {
                foreach ($records as $record) {
                    $this->link($relationName, $record);
                    $record->save();
                }
            }

        }

        $this->_dirtyRelatedRecords = $this->_unrelatedRecords = [];
        parent::afterSave($insert, $changedAttributes);
    }

    private static function _foreachNestedRecords($records, callable $callable)
    {
        foreach ($records as $relationName=>$records) {
            foreach ($records as $record) {
                $callable($relationName, $record);
            }
        }
    }

    /**
     * Nested validate
     * @param null $attributeNames
     * @param bool $clearErrors
     * @return bool
     */
    public function validate($attributeNames = null, $clearErrors = true)
    {

        $result = parent::validate($attributeNames, $clearErrors);

        // Make nested errors.
        $errors = [];
        static::_foreachNestedRecords($this->_dirtyRelatedRecords + $this->_unrelatedRecords,
            function($relationName, ActiveRecord $record)
            use (&$result, &$errors, $attributeNames, $clearErrors) {
                if (!$record->validate(isset($attributeNames[$relationName]) ? $attributeNames[$relationName] : null,
                    $clearErrors))
                {
                    $result = false;
                    if (!isset($errors[$relationName]))
                    {
                        $errors[$relationName] = ucfirst($relationName). ' has error(s)';
                    }
                }
            }
        );

        if (!empty($errors))
        {
            $this->addErrors($errors);
        }

        return $result;
    }

    /**
     * @return object
     */
    public static function find()
    {
        return \Yii::createObject(ActiveQuery::class, [get_called_class()]);
    }


    public function getClassName() {
        return static::class;
    }

    /**
     * Polymorphic
     * @param string $typeName
     * @param string $typeId
     * @return ActiveQuery
     */
    public function belongsToPolymorphic($typeName='model_class', $typeId='model_id') {
        /* @var $class ActiveRecordInterface */
        /* @var $query ActiveQuery */

        if (!$this->{$typeName}) {
            $this->{$typeName} = ActiveRecord::class;
        }
        $class = $this->{$typeName};
        $query = $class::find();
        $query->primaryModel = $this;
        $query->link = ['id'=>$typeId];
        $query->multiple = false;
        $query->polymorphic = [$typeName, $typeId];
        return $query;
    }

    /**
     * @param $class
     * @param $name
     * @return ActiveQuery
     */
    public function hasOnePolymorphic($class, $name) {
        return $this->hasPolymorphic($class, $name, false);
    }

    /**
     * @param $class
     * @param $name
     * @return ActiveQuery
     */
    public function hasManyPolymorphic($class, $name) {
        return $this->hasPolymorphic($class, $name, true);
    }

    /**
     * @param $class
     * @param $name
     * @param $multiple
     * @return ActiveQuery
     */
    public function hasPolymorphic($class, $name, $multiple) {
        /* @var $class ActiveRecordInterface */
        /* @var $query ActiveQuery */

        $relation = (new $class)->getRelation($name, false);
        $polymorphic = $relation->polymorphic;

        $query = $class::find();
        $query->primaryModel = $this;
        $query->link = [$polymorphic[1]=>'id', get_class($polymorphic[0])];
        $query->multiple = $multiple;
        $query->as = $name;
        $query->polymorphic = $polymorphic;

        return $query;
    }

    /**
     * @param string $name
     * @param array $params
     * @return mixed
     */
    public function __call($name, $params)
    {
        if (strpos($name, 'build') == 0
            && $this->getRelation($relationName = lcfirst(substr($name, 5)), false) != null) {
            $record = $this->buildUnrelatedRecord($relationName, !empty($params) ? $params[0] : []);
            return $record;
        }
        return parent::__call($name, $params);
    }


}
