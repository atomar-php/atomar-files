<?php

namespace model;
use RedBean_SimpleModel;

/**
 * Represents a single file in a data store.
 * Files are low level objects that manage the data of a file. 
 * 
 * In general these will never be used outside of this module.
 */
class File extends \atomar\core\BeanModel {
    
    /**
     * Attaches this file to a RedBean_SimpleModel.
     * The result is a new FileNode. Files may have many nodes each which associate the file to another RedBean_SimpleModel.
     *
     * @param RedBean_SimpleModel $model the bean model to which the file node will be linked to.
     * @return Filenode a new file node bean.
     */
    public function link_to(RedBean_SimpleModel $model) {
        $node = \R::dispense('filenode');
        $node->file = $this->bean;
        $model->sharedFilenodeList[] = $node;
        store($model);
        return $node;
    }
}