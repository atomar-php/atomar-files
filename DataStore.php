<?php
/**
 * Created by PhpStorm.
 * User: joel
 * Date: 11/2/16
 * Time: 10:06 PM
 */

namespace files\controller;


use RedBean_SimpleModel;

abstract class DataStore {
    /**
     * Performs any initialization required by the data store
     */
    public function init() {
        
    }

    /**
     * returns the file meta data
     * @param  RedBean_SimpleModel $file
     * @return array
     */
    public function read_file_meta($file) {
        return array();
    }

    /**
     * generates an upload url for the file
     * @param  RedBean_SimpleModel $file
     * @param  int $ttl
     * @param bool $return_as_object should return the upload object rather than the url if set to true
     * @return string
     */
    public function upload_url($file, $ttl, $return_as_object = false) {
        return '';
    }

    /**
     * Performs any extra actions on a file after it has been uploaded
     *
     * @param RedBean_SimpleModel $file the uploaded file
     * @return boolean true if processing was successful
     */
    public function post_process_upload($file) {
        return true;
    }

    /**
     * returns a download url for the file
     *
     * @param RedBean_SimpleModel $file the file to be downloaded
     * @param int $ttl
     * @return string the download link
     */
    public function download_url($file, $ttl) {
        return '';
    }

    /**
     * prints the raw file data to the client and exists the script.
     *
     * @param RedBean_SimpleModel $file the file to be downloaded
     * @param boolean $view_in_browser if set to true the server will attempt to tell the browser to display the file instead of downloading it.
     */
    public function download($file, $view_in_browser = false) {

    }
}