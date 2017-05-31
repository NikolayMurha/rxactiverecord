<?php
/**
 * @copyright Copyright &copy; rainyx, rainyx.com, 2013 - 2017
 * @file ActiveCollectionProxy.php
 * @version ${VERSION}
 */


namespace rainyx\rxkit\db;

/**
 * Class ActiveCollectionProxy
 * @package rainyx\rxkit\db
 * @property array $combinedRecords
 * @author rainyx <atrainyx#gmail.com>
 * @since 1.0
 */
class ActiveCollectionProxy extends \yii\base\Object implements \ArrayAccess, \Countable, \IteratorAggregate {


    /**
     *
     * @var string
     */
    public $relationName;

    /* @var ActiveQuery */
    public $query;
    /* @var array */

    private $_relatedRecords;
    private $_relatedRecordsLoaded = false;

    public function __construct($relationName, ActiveQuery $query, array $config = [])
    {
        $this->relationName = $relationName;
        $this->query = $query;
        parent::__construct($config);
    }


    public function build($param) {

        /* @var ActiveNestedInterface $primaryModel */
        $primaryModel = $this->query->primaryModel;
        return $primaryModel->buildUnrelatedRecord($this->relationName, $param);
    }

    public function update($param) {

        /* @var ActiveNestedInterface $primaryModel */

        $primaryModel = $this->query->primaryModel;
        $primaryModel->updateRelatedRecord($this->relationName, $param);

    }

    public function offsetExists($offset)
    {
        return isset($this->combinedRecords[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->combinedRecords[$offset];
    }

    public function offsetSet($offset, $value)
    {
        /* @var ActiveNestedInterface $primaryModel */

        $primaryModel = $this->query->primaryModel;

        if (isset($this->combinedRecords[$offset])) {
            $primaryModel->updateRelatedRecord($this->relationName, $value, $offset);
        } else {
            $primaryModel->addUnrelatedRecord($this->relationName, $value);
        }

    }

    public function offsetUnset($offset)
    {
        $this->_loadRelatedRecordsNecessary();
        // TODO Unlink
    }


    public function count()
    {
        return count($this->combinedRecords);
    }

    public function getCombinedRecords() {
        $this->_loadRelatedRecordsNecessary();
        $primaryModel = $this->query->primaryModel;

        $combined = ($this->_relatedRecords + $primaryModel->getUnrelatedRecords($this->relationName));

        return $combined;
    }

    private function _loadRelatedRecordsNecessary() {
        if (!$this->_relatedRecordsLoaded) {
            $this->_relatedRecordsLoaded = true;
            $this->_relatedRecords = $this->query->originalFindFor($this->relationName, $this->query->primaryModel);
        }
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->combinedRecords);
    }
}