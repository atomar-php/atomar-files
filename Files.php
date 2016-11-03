<?php
/**
 * Created by PhpStorm.
 * User: joel
 * Date: 11/2/16
 * Time: 10:05 PM
 */

namespace files\controller;

use atomar\Atomar;
use atomar\core\Logger;
use atomar\core\Templator;
use RedBean_SimpleModel;

/**
 * Class Files
 * @package files\controller
 */
class Files {
    /**
     * the data store used for writing files
     *
     * @param
     */
    private static $writer = null;

    /**
     * the data store used for reading files
     *
     * @param
     */
    private static $reader = null;

    /**
     * Sets the data store to use for writing files.
     * @param DataStore $data_store An instance of a class that extends DataStore.
     */
    public static function set_writer($data_store) {
        self::init();
        if (is_a($data_store, 'DataStore')) {
            self::$writer = $data_store;
            self::$writer->init();
        }
    }

    /**
     * initializes the file drop api.
     * This must run at the beginning if each method in the api in order to initialize the data store.
     */
    private static function init() {
        if (self::$writer === null) {
            self::$writer = new LocalDataStore();
            self::$writer->init();
        }
    }

    /**
     * Much like fetch_file except it only returns the meta data without the body content
     *
     * @param RedBean_SimpleModel $file The file to read
     * @return mixed Returns the file object on success and FALSE on failure.
     */
    public static function fetch_file_meta($file) {
        self::init();
        if (!$file->id) return false;
        self::set_reader($file->data_store);

        return self::$reader->read_file_meta($file);
    }

    /**
     * sets the data store to use for reading files
     * This is use internally to easily read files from different data stores.
     *
     * @param string $data_store_class the name of the data store class to load.
     */
    private static function set_reader($data_store_class) {
        if (self::$reader !== null && get_class(self::$reader) == $data_store_class) {
            return;
        } else {
            self::$reader = new $data_store_class();
            self::$reader->init();
        }
    }

    /**
     * Retrieves a download link for the file
     *
     * @param RedBean_SimpleModel $file the file to be downloaded
     * @return string the download link
     */
    public static function get_download_url($file, $ttl = 300) {
        self::init();
        if (!$file->id) return false;
        self::set_reader($file->data_store);

        return self::$reader->download_url($file, $ttl);
    }

    /**
     * Downloads the file
     * this differs from get_download_url in that this method prints the raw file data directly and exits the script
     *
     * @param RedBean_SimpleModel $file the file to be downloaded
     * @param boolean $view_in_browser if set to true the server will attempt to tell the browser to display the file instead of downloading it.
     */
    public static function download_file($file, $view_in_browser = false) {
        self::init();
        if ($file->id) {
            self::set_reader($file->data_store);

            self::$reader->download($file, $view_in_browser);
        } else {
            header('HTTP/1.1 404 Not Found');
        }
        exit;
    }

    /**
     * Imports a file into the system.
     * Use this method if you already have a file on the server that you would like to import.
     * @param string $file_name the name of the file including extension
     * @param string $file_source_path the path to the file that will be imported
     * @param string $file_destination the relative file destination path
     * @return RedBean_SimpleModel
     */
    public static function import_file($file_name, $file_source_path, $file_destination) {
        $hash = md5_file($file_source_path);
        $file = self::get_file_by_hash($hash);
        if (!$file || $file->is_uploaded == '0') {
            // trash broken file
            if (!$file) $file = R::dispense('file');
            // build new file object
            $file->size = filesize($file_source_path); // file size in bytes
            $file->is_uploaded = '1';
            $file->hash = $hash;
            $file->name = $file_name;
            $file->ext = strtolower(end(explode('.', $file->name)));
            $file->file_path = trim($file_destination, '/') . '/' . $file->hash . '.' . $file->ext;
            $file->name_searchable = str_replace('_', ' ', $file->name);
            $file->created_at = db_date();
            $file->created_by = A::$user;
            $file->data_store = self::data_store_type();

            if (store($file)) {
                // move file
                $upload = self::get_upload_url($file, 10, true);
                if ($upload) {
                    // move the file
                    $dest_path = Atomar::$config['files'] . $upload->file->file_path;
                    $dir = dirname($dest_path);
                    if (!is_dir($dir)) mkdir($dir, 0770, true);
                    rename($file_source_path, $dest_path);
                    \R::trash($upload);
                    // finish
                    if (self::post_process_upload($file)) {
                        return $file;
                    } else {
                        return null;
                    }
                }
            } else {
                Logger::log_error($file->errors());
                return null;
            }
        } else {
            return $file;
        }
    }

    /**
     * Looks for an existing cloudfile by it's hash.
     * Hashes for all intents and purposes will identify unique data.
     * This is not 100% accurate but the odds of a hash collision is absurdly low.
     *
     * @param string $hash md5 sum of the file
     * @return RedBean_SimpleModel the file or null;
     */
    public static function get_file_by_hash($hash) {
        self::init();
        $file = \R::findOne('file', 'hash=?', array($hash));
        return $file;
    }

    /**
     * returns the name of the data store
     *
     * @return string
     */
    public static function data_store_type() {
        self::init();
        return get_class(self::$writer);
    }

    /**
     * Generate a temporary upload url
     *
     * @param object $file The file bean that will be uploaded
     * @param int $ttl The length of time the upload url will be active. Default is 5 minutes.
     * @param bool $return_as_object will return the upload object instead of just the url if set to true. Default is false
     * @return string The temporary upload url.
     */
    public static function get_upload_url($file, $ttl = 300, $return_as_object = false) {
        self::init();
        if (!$file->id) return false;
        self::set_reader($file->data_store);

        return self::$reader->upload_url($file, $ttl, $return_as_object);
    }

    /**
     * Performs extra operations on the file after it has been uploaded.
     *
     * @param RedBean_SimpleModel $file the file that was uploaded.
     * @return bool
     */
    public static function post_process_upload($file) {
        self::init();
        if (!$file->id) return false;
        self::set_reader($file->data_store);

        try {
            $success = self::$reader->post_process_upload($file);
        } catch (\Exception $e) {
            Logger::log_error($e->getMessage(), $e->getTrace());
            return false;
        }
        return $success;
    }

    /**
     * deploys the drop zone onto the page and includes any necessary files
     *
     * @param string $drop_zone the selector that will be used as the drop zone
     * @param array $options javascript options to configure how the drop zone operates
     */
    public static function deploy($drop_zone, $options = array()) {
        self::init();
        // TODO: extension assets are now handled by referencing the namespace
        Templator::$js[] = Templator::resolve_ext_asset('file_drop/js/spark-md5.min.js');
        Templator::$js[] = Templator::resolve_ext_asset('file_drop/js/jquery.ui.widget.js');
        Templator::$js[] = Templator::resolve_ext_asset('file_drop/js/jquery.iframe-transport.js');
        Templator::$js[] = Templator::resolve_ext_asset('file_drop/js/jquery.fileupload.js');
        Templator::$js[] = Templator::resolve_ext_asset('file_drop/js/filedrop.js');
        Templator::$css[] = Templator::resolve_ext_asset('file_drop/css/filedrop.css');

        // set forced defaults
        $options['initUploadUrl'] = '/!/file_drop/init';
        $options['confirmUploadUrl'] = '/!/file_drop/confirm';

        // build option list
        $js_options = array();
        foreach ($options as $key => $value) {
            if (strpos($key, 'callback') === 0) {
                $js_options[] = $key . ':' . $value;
            } else {
                $js_options[] = $key . ':"' . $value . '"';
            }
        }
        $js_options = '{' . implode(',', $js_options) . '}';

        Templator::$js_onload[] = <<<JAVASCRIPT
$('$drop_zone').filedrop($js_options);
JAVASCRIPT;
    }
}