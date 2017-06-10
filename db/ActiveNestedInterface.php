<?php
/**
 * @copyright Copyright &copy; rainyx, rainyx.com, 2013 - 2017
 * @file ActiveNestedInterface.php
 * @version ${VERSION}
 */

namespace rainyx\rxactiverecord\db;

/**
 * Interface ActiveNestedInterface
 * @package rainyx\rxkit\db
 * @author rainyx <atrainyx#gmail.com>
 * @since 1.0
 */
interface ActiveNestedInterface {
    /**
     * 添加一个关联对象，该对象在PrimaryModel::afterSave 后 link
     * @param string $name
     * @param ActiveRecordInterface $record
     * @return void
     */
    public function addUnrelatedRecord($name, $record);

    /**
     * 获取一个Relation的所有未关联对象
     * @param string $name
     * @return array
     */
    public function getUnrelatedRecords($name);

    /**
     * 创建一个未关联对象，并指定attributes
     * @param string $name
     * @param mixed $param attributes|ActiveRecordInterface
     * @return ActiveRecordInterface
     */
    public function buildUnrelatedRecord($name, $param);

    /**
     * 更新一个关联对象
     * @param string $name
     * @param mixed $param attributes|ActiveRecordInterface
     * @param null $key
     * @return void
     */
    public function updateRelatedRecord($name, $param, $key=null);
}