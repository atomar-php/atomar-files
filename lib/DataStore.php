<?php

namespace files;


use model\File;
use model\Fileupload;

interface DataStore {
    /**
     * Performs any initialization required by the data store
     */
    public function init();

    /**
     * returns the file meta data
     * @param  File $file
     * @return array
     */
    public function getMeta(File $file);

    /**
     * Generates an upload object for the file.
     *
     * @param  File $file the file to be uploaded
     * @param  int $ttl the time before this upload expires
     * @return null|Fileupload the upload bean
     */
    public function generateUpload(File $file, int $ttl);

    /**
     * Performs any extra actions on a file after it has been uploaded
     *
     * @param File $file the uploaded file
     * @return boolean true if processing was successful
     */
    public function postProcessUpload(File $file);

    /**
     * returns a download url for the file
     *
     * @param File $file the file to be downloaded
     * @param int $ttl
     * @return string the download link
     */
    public function getDownloadURL(File $file, int $ttl);

    /**
     * prints the raw file data to the client and exists the script.
     *
     * @param File $file the file to be downloaded
     * @param boolean $view_in_browser if set to true the server will attempt to tell the browser to display the file instead of downloading it.
     */
    public function download(File $file, bool $view_in_browser = false);
}