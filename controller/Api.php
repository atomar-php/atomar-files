<?php

namespace files\controller;

use atomar\Atomar;
use atomar\core\ApiController;
use atomar\core\APIError;
use atomar\core\Auth;
use atomar\core\Logger;
use files\FileManager;
use files\LocalDataStore;

/**
 * This api performs most of the grunt work for the file drop component.
 * In order to use this api in your application you'll need to set up a route that points to this api.
 * See the hooks.php file in this module for an example.
 * Class Api
 * @package files\controller
 */
class Api extends ApiController {
    /**
     * @var FileManager
     */
    private $fm;

    /**
     * Initializes an upload from the file drop
     * @param string $hash the md5 sum of the content being uploaded
     * @param int $size the size of the file in bytes
     * @param string $name the name of the file
     * @param int $speed the speed of the network in kbs. default is 5
     * @return array
     */
    function get_init($hash, $size, $name, $speed=5) {
        Auth::has_authentication('files-upload');

        $file = $this->fm->getFileByHash($hash);

        // initiate upload if the file has not been uploaded or is missing
        if (!$file || $file->is_uploaded == '0' || $this->fm->fetchFileMeta($file) == null) {
            if (!$file) {
                $file = \R::dispense('file');
            }

            $file->size = $size;
            $file->is_uploaded = '0';

            // Perform bandwidth profiling
            $speed = $speed * 1000 / 8.0; // convert to bytes/s
            $min_upload_time = intval(Atomar::get_variable('file_drop_min_upload_time', 10)); // give at least 10 seconds to upload
            $estimated_upload_time = ceil($file->size / $speed * 2) + $min_upload_time; // seconds .. give twice the estimated time add add the minimum time to allow http overhead and in case their speed changes.

            // Pre-populate file data
            $file->hash = $hash;
            $file->name = $name;
            $chunks = explode('.', $file->name);
            $file->ext = strtolower(end($chunks));
            $file->file_path = $file->hash . '.' . $file->ext;
            $file->name_searchable = str_replace('_', ' ', $file->name);
            $file->created_at = db_date();
            $file->created_by = Auth::$user;
            $file->data_store = $this->fm->dataStoreName();
            unset($chunks);

            // store new file
            if (store($file)) {
                $upload = $this->fm->generateUpload($file->box(), $estimated_upload_time);
                $upload_url = '/files/api/upload?token=' . $upload->token;
                if ($upload_url) {
                    $response = array(
                        'status' => 'success',
                        'fid' => $file->id,
                        'upload_url' => $upload_url,
                        'message' => 'The upload url has been successfully generated. It will expire in ' . $estimated_upload_time . ' seconds'
                    );
                } else {
                    $response = array(
                        'status' => 'error',
                        'message' => 'The upload url could not be generated.'
                    );
                }
            } else {
                $response = array(
                    'status' => 'error',
                    'message' => 'the file could not be created'
                );
            }
        } else {
            // duplicate file.
            $response = array(
                'status' => 'duplicate',
                'fid' => $file->id,
                'message' => 'This file has already been uploaded'
            );
        }
        return $response;
    }

    /**
     * Confirms the successful upload of a file
     * @param int $fid the file id
     * @return array
     */
    function get_confirm($fid) {
        Auth::has_authentication('files-upload');
        $file = \R::load('file', $fid);
        if ($file->id) {
            $file->is_uploaded = '1';
            store($file);
            if ($this->fm->postProcessUpload($file->box())) {
                $response = array(
                    'status' => 'success',
                    'fid' => $file->id
                );
            } else {
                $response = array(
                    'status' => 'error',
                    'message' => 'the file was uploaded but could not be processed'
                );
            }
        } else {
            $response = array(
                'status' => 'error',
                'message' => 'the file could not be found'
            );
        }
        return $response;
    }

    /**
     * Downloads a file
     * @param int $id the file node id
     * @param int $view indicates if the file should be viewed in the browser instead of downloading.
     */
    function get_download($id, $view=null) {
        $node = \R::load('filenode', $id);
        if ($node->id) {
            if ($node->authenticate(Auth::$user->box(), array('read' => 1))) {
                $view = !!$view;
                $this->fm->downloadFile($node->file->box(), $view);
            } else {
                set_error('You do not have permission to view that file');
                header('HTTP/1.1 403 Forbidden', true, 403);
                exit;
            }
        } else {
            header('HTTP/1.1 404 Not Found', true, 404);
            exit;
        }
    }

    /**
     * Deletes a file node.
     * Note: this does not actually delete the file. It only deletes the file node.
     *
     * @param int $id the file node id
     */
    function get_delete($id) {
        $node = \R::load('filenode', $id);
        if ($node->id) {
            if ($node->authenticate(Auth::$user->box(), array('delete' => 1))) {
                \R::trash($node);
                set_success('The file has been deleted');
            } else {
                set_error('You do not have permission to delete that file');
            }
        }
        $this->go_back();
    }

    function get_upload() {

    }

    /**
     * Receives upload data
     * @param string $token the upload token
     */
    function post_upload($token) {
        Logger::log_notice('uploading file with token', $token);
        // TODO: the FileManager should handle fetching an upload object
        $upload = \R::findOne('fileupload', 'token=?', array($token));
        if ($upload) {
            Logger::log_notice('found upload request');
            // validate upload
            if (time() - strtotime($upload->created_at) > $upload->ttl) {
                Logger::log_warning('Files:PUT: upload expired', array(
                    'fileupload' => $upload->export(),
                    'request' => $_REQUEST
                ));
                \R::trash($upload);
                return new UploadError('Upload token has expired');
            }
            Logger::log_notice('preparing output');
            // prepare output
            $output = Atomar::$config['files'] . $upload->file->file_path;
            $dir = dirname($output);
            try {
                if (!is_dir($dir)) {
                    mkdir($dir, 0770, true);
                }
                Logger::log_notice('opening file');
                // write the file
                if (!($putdata = fopen("php://input", "r"))) {
                    Logger::log_error('Files:PUT: failed to get PUT data', array(
                        'fileupload' => $upload->export(),
                        'request' => $_REQUEST
                    ));
                    \R::trash($upload);
                    return new UploadError('Failed to read PUT data');
                }
                Logger::log_notice('opening output', $output);
                if (!($fp = fopen($output, 'w'))) {
                    Logger::log_error('Files:PUT: failed to open output file', array(
                        'output' => $output,
                        'fileupload' => $upload->export(),
                        'request' => $_REQUEST
                    ));
                    \R::trash($upload);
                    return new UploadError('Failed to open output file');
                }
                Logger::log_notice('writing data');
                while ($data = fread($putdata, 1024)) {
                    fwrite($fp, $data);
                }
                fclose($fp);
                fclose($putdata);
                Logger::log_notice('done writing file');
                \R::trash($upload);
                return true;
            } catch (\Exception $e) {
                // likely this is a file system error. e.g. not writable
                Logger::log_error('CFileDropAPI:PUT: The file could not be created', $e->getMessage());
                \R::trash($upload);
                return new UploadError('Failed to write output file');
            }
        } else {
            Logger::log_warning('CFileDropAPI:PUT: invalid upload request', $_REQUEST);
            return new UploadError('non-registered upload request');
        }
    }

    /**
     * Allows you to perform any additional actions before post requests are processed
     * @param array $matches
     */
    protected function setup_post($matches = array()) {
        $this->fm = new FileManager(new LocalDataStore());
    }

    /**
     * Allows you to perform any additional actions before get requests are processed
     * @param array $matches
     */
    protected function setup_get($matches = array()) {
        $this->fm = new FileManager(new LocalDataStore());
    }

}

class UploadError extends APIError {}