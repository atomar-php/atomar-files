<?php

namespace files\controller;

/**
 * Implements hook_permission()
 */
function permission() {
    // These permissions control what features of the REST api can be accessed
    return array(
        'files-download',
        'files-upload'
    );
}

function pre_process_boot() {

}

function post_process_boot() {

}

function pre_process_page() {

}

/**
 * Implements hook_menu()
 */
function menu() {
    // return an array of menu items
    return array();
}

/**
 * Implements hook_url()
 */
function url() {
    // Below is an example route to the file drop api.
    // Note: this route is commented out here so the application can perform any needed authentication and customize the api end point.
    return array(
//        '/api/(?P<api>[a-zA-Z\_-]+)/?(\?.*)?' => 'files\controller\Api'
    );
}

/**
 * Implements hook_libraries()
 */
function libraries() {
    return array (
        'DataStore.php',
        'LocalDataStore.php',
        'FileDropAPI.php'
    );
}

/**
 * Implements hook_cron()
 */
function cron() {
    // execute execute cron operations here
}

/**
 * Implements hook_twig_function()
 */
function twig_function() {
    // return an array of key value pairs.
    // key: twig_function_name
    // value: actual_function_name
    // You may use object functions as well
    // e.g. ObjectClass::actual_function_name
    return array();
}