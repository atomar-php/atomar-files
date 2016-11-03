<?php
/**
 * Created by PhpStorm.
 * User: joel
 * Date: 11/2/16
 * Time: 10:08 PM
 */

namespace files\controller;


use atomar\Atomar;
use RedBean_SimpleModel;

class LocalDataStore extends DataStore {
    
    public function upload_url($file, $ttl, $return_as_object = false) {
        $upload = \R::dispense('fileupload');
        $upload->token = md5($file->file_path . $file->id . $ttl);
        $upload->file = $file;
        $upload->ttl = $ttl;
        $upload->created_at = db_date();
        if (store($upload)) {
            if ($return_as_object) {
                return $upload;
            } else {
                return '/!/file_drop/upload?token=' . $upload->token;
            }
        } else {
            return false;
        }
    }

    public function post_process_upload($file) {
        $meta = $this->read_file_meta($file);
        $file->size = $meta['content_length'];
        $file->type = $meta['content_type'];
        return store($file);
    }

    public function read_file_meta($file) {
        // NOTE: finfo_open is not available on older versions of php.
        $path = $this->realpath($file);
        $info = pathinfo($path);
        $meta = array();
        $meta['content_length'] = filesize($path);
        $meta['content_size'] = $info['extension'];
        if (function_exists('mime_content_type')) {
            $meta['content_type'] = mime_content_type($path);
        }
        $meta['path'] = $path;
        return $meta;
    }

    /**
     * Returns the fully qualified path to the file
     *
     * @param RedBean_SimpleModel $file
     * @return string the path to the file
     */
    public function realpath($file) {
        return realpath(Atomar::$config['files'] . $file->file_path);
    }

    public function download($file, $view_in_browser = false) {
        if ($file->id) {
            $path = $this->realpath($file);
            if (file_exists($path)) {
                if ($view_in_browser) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $path);
                    header('Content-Type: ' . $mime);
                    header('Content-Disposition: inline; filename=' . basename($path));
                    readfile($path);
                } else {
                    stream_file($path, array(
                        'name' => $file->name,
                        'content_type' => $file->type,
                        'download' => true
                    ));
                }
            } else {
                header('HTTP/1.1 404 Not Found');
                echo 'Missing file data';
            }
        } else {
            header('HTTP/1.1 404 Not Found');
        }
        exit;
    }
}